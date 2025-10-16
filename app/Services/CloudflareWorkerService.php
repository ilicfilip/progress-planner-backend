<?php

namespace App\Services;

use App\Models\RegisteredSite;
use App\Models\SiteSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareWorkerService
{
    private const CACHE_PREFIX_IN_PROGRESS = 'html_fetch_in_progress';
    private const CACHE_PREFIX_PENDING = 'html_fetch_pending';
    private const CACHE_TTL = 3600; // 1 hour
    private const FETCH_DELAY = 60; // Wait 60 seconds before fetching results
    private const REQUEST_TIMEOUT = 45; // seconds

    private string $workerUrl;

    public function __construct()
    {
        $this->workerUrl = config('services.cloudflare.worker_url');
    }

    /**
     * Queue domains for fetching via Cloudflare Worker
     */
    public function queueDomains(array $domains): bool
    {
        if (empty($domains)) {
            return false;
        }

        // Filter out domains that are already in progress
        $inProgressDomains = $this->getInProgressDomains();
        $domainsToFetch = array_diff($domains, $inProgressDomains);

        if (empty($domainsToFetch)) {
            Log::info('All domains already in progress, skipping queue');
            return false;
        }

        try {
            // Send domains to Cloudflare Worker
            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->post($this->workerUrl . '/fetch-domains', [
                    'domains' => $domainsToFetch,
                ]);

            if (!$response->successful()) {
                Log::error('Failed to queue domains with CF Worker', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            // Mark domains as in progress
            $this->addInProgressDomains($domainsToFetch);

            // Add to pending fetch queue with timestamp
            $this->addPendingDomains($domainsToFetch);

            Log::info('Successfully queued domains for HTML fetch', [
                'count' => count($domainsToFetch),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Exception queuing domains: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch HTML content for domains from Cloudflare Worker
     */
    public function fetchHtmlContent(array $domains): array
    {
        $results = [
            'successful' => [],
            'failed' => [],
        ];

        foreach ($domains as $domain) {
            try {
                $response = Http::timeout(self::REQUEST_TIMEOUT)
                    ->get($this->workerUrl . '/get-results', [
                        'domain' => $domain,
                    ]);

                if (!$response->successful()) {
                    Log::error('Failed to fetch HTML for domain', [
                        'domain' => $domain,
                        'status' => $response->status(),
                    ]);
                    $results['failed'][] = $domain;
                    continue;
                }

                $data = $response->json();

                if (isset($data[$domain])) {
                    $this->storeSnapshot($domain, $data[$domain]);
                    $results['successful'][] = $domain;

                    // Remove from pending and in-progress
                    $this->removePendingDomain($domain);
                    $this->removeInProgressDomain($domain);
                } else {
                    Log::warning('No HTML content returned for domain', [
                        'domain' => $domain,
                    ]);
                    $results['failed'][] = $domain;
                }
            } catch (\Exception $e) {
                Log::error('Exception fetching HTML for domain', [
                    'domain' => $domain,
                    'error' => $e->getMessage(),
                ]);
                $results['failed'][] = $domain;
            }
        }

        return $results;
    }

    /**
     * Get domains that are ready to be fetched (pending for >= 60 seconds)
     */
    public function getReadyDomains(): array
    {
        $pending = Cache::get(self::CACHE_PREFIX_PENDING, []);

        if (empty($pending)) {
            return [];
        }

        $readyDomains = [];
        $now = now()->timestamp;

        foreach ($pending as $domain => $timestamp) {
            if (($now - $timestamp) >= self::FETCH_DELAY) {
                $readyDomains[] = $domain;
            }
        }

        return $readyDomains;
    }

    /**
     * Store HTML snapshot for a domain
     */
    private function storeSnapshot(string $domain, string $htmlContent): void
    {
        $site = RegisteredSite::where('site_url', 'LIKE', '%' . $domain . '%')->first();

        if (!$site) {
            Log::warning('No registered site found for domain', ['domain' => $domain]);
            return;
        }

        SiteSnapshot::updateOrCreate(
            [
                'registered_site_id' => $site->id,
                'domain' => $domain,
            ],
            [
                'html_content' => $htmlContent,
            ]
        );

        Log::info('Stored HTML snapshot for domain', ['domain' => $domain]);
    }

    /**
     * Get list of domains currently being fetched
     */
    private function getInProgressDomains(): array
    {
        return Cache::get(self::CACHE_PREFIX_IN_PROGRESS, []);
    }

    /**
     * Add domains to in-progress list
     */
    private function addInProgressDomains(array $domains): void
    {
        $inProgress = $this->getInProgressDomains();
        $updated = array_unique(array_merge($inProgress, $domains));
        Cache::put(self::CACHE_PREFIX_IN_PROGRESS, $updated, self::CACHE_TTL);
    }

    /**
     * Remove domain from in-progress list
     */
    private function removeInProgressDomain(string $domain): void
    {
        $inProgress = $this->getInProgressDomains();
        $updated = array_diff($inProgress, [$domain]);

        if (empty($updated)) {
            Cache::forget(self::CACHE_PREFIX_IN_PROGRESS);
        } else {
            Cache::put(self::CACHE_PREFIX_IN_PROGRESS, $updated, self::CACHE_TTL);
        }
    }

    /**
     * Add domains to pending fetch list with timestamp
     */
    private function addPendingDomains(array $domains): void
    {
        $pending = Cache::get(self::CACHE_PREFIX_PENDING, []);
        $timestamp = now()->timestamp;

        foreach ($domains as $domain) {
            $pending[$domain] = $timestamp;
        }

        Cache::put(self::CACHE_PREFIX_PENDING, $pending, self::CACHE_TTL);
    }

    /**
     * Remove domain from pending list
     */
    private function removePendingDomain(string $domain): void
    {
        $pending = Cache::get(self::CACHE_PREFIX_PENDING, []);
        unset($pending[$domain]);

        if (empty($pending)) {
            Cache::forget(self::CACHE_PREFIX_PENDING);
        } else {
            Cache::put(self::CACHE_PREFIX_PENDING, $pending, self::CACHE_TTL);
        }
    }

    /**
     * Get all domains from registered sites
     */
    public function getAllDomains(): array
    {
        return RegisteredSite::all()
            ->map(function ($site) {
                return parse_url($site->site_url, PHP_URL_HOST);
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Check if domain should be fetched (not fetched in last hour)
     */
    public function shouldFetchDomain(string $domain): bool
    {
        $snapshot = SiteSnapshot::where('domain', $domain)
            ->orderBy('updated_at', 'desc')
            ->first();

        if (!$snapshot) {
            return true;
        }

        // Fetch if last update was more than 1 hour ago
        return $snapshot->updated_at->lt(now()->subHour());
    }

    /**
     * Get domains that need fetching
     */
    public function getDomainsToFetch(bool $force = false): array
    {
        $allDomains = $this->getAllDomains();

        if ($force) {
            return $allDomains;
        }

        return array_filter($allDomains, function ($domain) {
            return $this->shouldFetchDomain($domain);
        });
    }
}
