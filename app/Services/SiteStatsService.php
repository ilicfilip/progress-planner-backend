<?php

namespace App\Services;

use App\Models\RegisteredSite;
use App\Models\SiteStat;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SiteStatsService
{
    private const REQUEST_TIMEOUT = 15; // seconds
    private const PLUGIN_SLUG = 'progress-planner/progress-planner.php';

    /**
     * Fetch stats for a single site
     */
    public function fetchSiteStats(RegisteredSite $site): void
    {
        $siteUrl = rtrim($site->site_url, '/');

        // If no license key, test with fake key to detect if plugin is active
        if (empty($site->license_key)) {
            $this->detectPluginWithoutLicense($site, $siteUrl);
            return;
        }

        $apiUrl = $siteUrl . '/wp-json/?rest_route=/progress-planner/v1/get-stats/' . $site->license_key;

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->get($apiUrl);

            if (!$response->successful()) {
                $this->updateSiteStat(
                    $site,
                    false,
                    null,
                    null,
                    'HTTP ' . $response->status() . ': ' . $response->body()
                );
                return;
            }

            $data = $response->json();

            // Check if API returned an error object
            if (isset($data['code'])) {
                $this->updateSiteStat(
                    $site,
                    false,
                    null,
                    $data,
                    $data['message'] ?? 'API returned error code: ' . $data['code']
                );
                return;
            }

            // Check if plugins array exists
            if (!isset($data['plugins']) || !is_array($data['plugins'])) {
                $this->updateSiteStat(
                    $site,
                    false,
                    null,
                    $data,
                    'Invalid API response structure'
                );
                return;
            }

            // Extract plugin version
            $pluginVersion = $this->extractPluginVersion($data['plugins']);

            $this->updateSiteStat(
                $site,
                true,
                $pluginVersion,
                $data,
                null
            );

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->updateSiteStat(
                $site,
                false,
                null,
                null,
                'Connection timeout or refused: ' . $e->getMessage()
            );
        } catch (\Exception $e) {
            $this->updateSiteStat(
                $site,
                false,
                null,
                null,
                'Exception: ' . $e->getMessage()
            );
            Log::error('Failed fetching stats for ' . $site->site_url . ': ' . $e->getMessage());
        }
    }

    /**
     * Detect if plugin is active without license key by testing with fake key
     */
    private function detectPluginWithoutLicense(RegisteredSite $site, string $siteUrl): void
    {
        $testApiUrl = $siteUrl . '/wp-json/?rest_route=/progress-planner/v1/get-stats/123';

        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->get($testApiUrl);

            $statusCode = $response->status();

            // If 404, plugin endpoint doesn't exist (plugin not active)
            if ($statusCode === 404) {
                $this->updateSiteStat(
                    $site,
                    false,
                    null,
                    null,
                    'Plugin not active (404 response)'
                );
                return;
            }

            $data = $response->json();

            // Check if we got the "invalid parameter" error, which means plugin IS active
            if (isset($data['code']) && $data['code'] === 'rest_invalid_param') {
                $this->updateSiteStat(
                    $site,
                    true,
                    null,
                    $data,
                    'Plugin active but no license key (not opted in)'
                );
                return;
            }

            // Any other response
            $this->updateSiteStat(
                $site,
                false,
                null,
                $data,
                'No license key - unexpected response: ' . json_encode($data)
            );

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->updateSiteStat(
                $site,
                false,
                null,
                null,
                'Connection timeout or refused: ' . $e->getMessage()
            );
        } catch (\Exception $e) {
            $this->updateSiteStat(
                $site,
                false,
                null,
                null,
                'Exception: ' . $e->getMessage()
            );
            Log::error('Failed detecting plugin for ' . $site->site_url . ': ' . $e->getMessage());
        }
    }

    /**
     * Extract plugin version from plugins array
     */
    private function extractPluginVersion(array $plugins): ?string
    {
        foreach ($plugins as $plugin) {
            if (isset($plugin['plugin']) && $plugin['plugin'] === self::PLUGIN_SLUG) {
                return $plugin['version'] ?? null;
            }
        }

        return null;
    }

    /**
     * Update or create site stat record
     */
    private function updateSiteStat(
        RegisteredSite $site,
        bool $apiAvailable,
        ?string $pluginVersion,
        ?array $rawResponse,
        ?string $errorMessage
    ): void {
        SiteStat::updateOrCreate(
            ['registered_site_id' => $site->id],
            [
                'api_available' => $apiAvailable,
                'plugin_version' => $pluginVersion,
                'raw_response' => $rawResponse,
                'error_message' => $errorMessage,
                'last_fetched_at' => now(),
            ]
        );
    }

    /**
     * Fetch stats for all sites
     */
    public function fetchAllSiteStats(): array
    {
        $sites = RegisteredSite::all();
        $results = [
            'total' => $sites->count(),
            'successful' => 0,
            'failed' => 0,
        ];

        foreach ($sites as $site) {
            $this->fetchSiteStats($site);

            // Refresh the relationship to get updated stats
            $site->load('siteStat');

            if ($site->siteStat && $site->siteStat->api_available) {
                $results['successful']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }
}
