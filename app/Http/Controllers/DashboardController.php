<?php

namespace App\Http\Controllers;

use App\Services\ProgressPlannerService;
use App\Services\SiteStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DashboardController extends Controller
{
    public function __construct(
        private ProgressPlannerService $progressPlannerService,
        private SiteStatsService $siteStatsService
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
            // Fetch and sync registered sites
            $sites = $this->progressPlannerService->fetchAndCacheRegisteredSites(true);
            $this->progressPlannerService->syncRegisteredSitesToDatabase($sites);

            // Fetch stats for all sites
            $results = $this->siteStatsService->fetchAllSiteStats();

            return redirect()
                ->route('dashboard')
                ->with('success', sprintf(
                    'Data refreshed successfully! Total: %d, Successful: %d, Failed: %d',
                    $results['total'],
                    $results['successful'],
                    $results['failed']
                ));
        } catch (\Exception $e) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Failed to refresh data: ' . $e->getMessage());
        }
    }
}
