<?php

namespace App\Http\Controllers;

use App\Jobs\FetchSiteStatsJob;
use App\Models\RegisteredSite;
use App\Services\ProgressPlannerService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private ProgressPlannerService $progressPlannerService
    ) {}

    /**
     * Display the dashboard with all registered sites
     */
    public function index()
    {
        $sites = $this->progressPlannerService->getAllSites();

        return view('dashboard', [
            'sites' => $sites,
        ]);
    }

    /**
     * Refetch data from Progress Planner API
     */
    public function refetch(Request $request)
    {
        try {
            // Fetch and sync registered sites (this is fast)
            $sites = $this->progressPlannerService->fetchAndCacheRegisteredSites(true);
            $this->progressPlannerService->syncRegisteredSitesToDatabase($sites);

            // Clean up any local/staging sites
            $cleanedUpCount = $this->progressPlannerService->cleanupLocalSites();

            // Dispatch jobs to fetch stats for each site in the background
            $registeredSites = $this->progressPlannerService->getAllSites();

            foreach ($registeredSites as $site) {
                FetchSiteStatsJob::dispatch($site);
            }

            $message = sprintf(
                'Data refresh started! %d sites synced. %d background jobs queued to fetch stats.',
                count($sites),
                $registeredSites->count()
            );

            if ($cleanedUpCount > 0) {
                $message .= sprintf(' %d local/staging site(s) removed.', $cleanedUpCount);
            }

            return redirect()
                ->route('registered-sites')
                ->with('success', $message);
        } catch (\Exception $e) {
            return redirect()
                ->route('registered-sites')
                ->with('error', 'Failed to refresh data: ' . $e->getMessage());
        }
    }

    /**
     * Preview HTML snapshot for a site
     */
    public function previewHtml(RegisteredSite $site)
    {
        $snapshot = $site->latestSnapshot;

        if (!$snapshot || !$snapshot->html_content) {
            abort(404, 'No HTML snapshot found for this site');
        }

        // Return the raw HTML content
        return response($snapshot->html_content)
            ->header('Content-Type', 'text/html')
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }
}
