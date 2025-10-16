<?php

namespace App\Services;

use App\Models\RegisteredSite;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProgressPlannerService
{
    private const API_URL = 'https://progressplanner.com/wp-json/progress-planner-saas/v1/registered-sites';
    private const API_TOKEN = 'ebd6a96d7320c0d1dd5e819098676f08';
    private const CACHE_KEY = 'registered_sites_data';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Fetch and cache registered sites data
     */
    public function fetchAndCacheRegisteredSites(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return $this->fetchRegisteredSites();
        });
    }

    /**
     * Fetch registered sites from API
     */
    private function fetchRegisteredSites(): array
    {
        try {
            $response = Http::timeout(30)->get(self::API_URL, [
                'token' => self::API_TOKEN,
            ]);

            if (!$response->successful()) {
                Log::error('Failed to fetch registered sites', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();

            if (!is_array($data)) {
                Log::error('Invalid JSON format from registered sites API');
                return [];
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Exception fetching registered sites: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Sync registered sites to database
     */
    public function syncRegisteredSitesToDatabase(array $sites): void
    {
        foreach ($sites as $siteData) {
            $this->createOrUpdateSite($siteData);
        }
    }

    /**
     * Check if site URL should be excluded (local/staging sites)
     */
    private function shouldExcludeSite(string $siteUrl): bool
    {
        $excludePatterns = [
            '.test',
            '.local',
            'localhost',
            'playground.wordpress.net',
        ];

        foreach ($excludePatterns as $pattern) {
            if (stripos($siteUrl, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create or update a registered site
     */
    private function createOrUpdateSite(array $siteData): void
    {
        $siteUrl = rtrim($siteData['site_url'] ?? '', '/');

        if (empty($siteUrl)) {
            return;
        }

        // Skip local/staging sites
        if ($this->shouldExcludeSite($siteUrl)) {
            Log::info('Skipping local/staging site: ' . $siteUrl);
            return;
        }

        $lastEmailedAt = $siteData['last_emailed_at'] ?? '';
        $lastEmailedDate = $this->convertWeekToDate($lastEmailedAt);

        RegisteredSite::updateOrCreate(
            ['site_url' => $siteUrl],
            [
                'license_key' => $siteData['license_key'] ?? null,
                'last_emailed_at' => $lastEmailedAt,
                'last_emailed_date' => $lastEmailedDate,
                'raw_data' => $siteData,
            ]
        );
    }

    /**
     * Convert YYYYWW format to date
     * e.g., 202507 => "2025-02-10" (Monday of that week)
     */
    private function convertWeekToDate(string $weekString): ?string
    {
        if (empty($weekString) || $weekString === '0') {
            return null;
        }

        if (strlen($weekString) < 6) {
            return null;
        }

        try {
            $year = (int) substr($weekString, 0, 4);
            $week = (int) substr($weekString, 4, 2);

            if ($year < 2000 || $year > 2100 || $week < 1 || $week > 53) {
                return null;
            }

            // Create date from ISO week
            $date = Carbon::now()
                ->setISODate($year, $week, 1); // Monday of the week

            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning('Failed to convert week to date: ' . $weekString);
            return null;
        }
    }

    /**
     * Clean up local/staging sites from database
     */
    public function cleanupLocalSites(): int
    {
        $excludePatterns = [
            '.test',
            '.local',
            'localhost',
            'playground.wordpress.net',
        ];

        $deletedCount = 0;

        foreach ($excludePatterns as $pattern) {
            $deleted = RegisteredSite::where('site_url', 'LIKE', '%' . $pattern . '%')->delete();
            $deletedCount += $deleted;
        }

        if ($deletedCount > 0) {
            Log::info("Cleaned up {$deletedCount} local/staging site(s)");
        }

        return $deletedCount;
    }

    /**
     * Get all registered sites from database
     */
    public function getAllSites()
    {
        return RegisteredSite::with('siteStat', 'latestSnapshot')->get();
    }
}
