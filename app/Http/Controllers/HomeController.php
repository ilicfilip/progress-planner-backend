<?php

namespace App\Http\Controllers;

use App\Models\RegisteredSite;
use App\Models\SiteStat;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index()
    {
        // Total registered sites
        $totalSites = RegisteredSite::count();

        // Total active sites (API available)
        $activeSites = SiteStat::where('api_available', true)->count();

        // Active sites without license key (detected via fake key test)
        $activeSitesNoLicense = SiteStat::where('api_available', true)
            ->whereHas('registeredSite', function ($query) {
                $query->where(function ($q) {
                    $q->whereNull('license_key')
                      ->orWhere('license_key', '');
                });
            })
            ->count();

        // Active sites with license key
        $activeSitesWithLicense = $activeSites - $activeSitesNoLicense;

        // Plugin version distribution (for active sites only)
        $versionStats = SiteStat::where('api_available', true)
            ->whereNotNull('plugin_version')
            ->where('plugin_version', '!=', '')
            ->select('plugin_version', DB::raw('count(*) as count'))
            ->groupBy('plugin_version')
            ->orderBy('count', 'desc')
            ->get();

        // Count sites with unknown version
        $unknownVersionCount = SiteStat::where('api_available', true)
            ->where(function ($query) {
                $query->whereNull('plugin_version')
                      ->orWhere('plugin_version', '');
            })
            ->count();

        // Build version data array
        $versionData = collect();

        // Add known versions
        foreach ($versionStats as $stat) {
            $versionData->push([
                'version' => $stat->plugin_version,
                'count' => $stat->count,
            ]);
        }

        // Add unknown version if any
        if ($unknownVersionCount > 0) {
            $versionData->push([
                'version' => 'Unknown/Not Detected',
                'count' => $unknownVersionCount,
            ]);
        }

        // Calculate percentages for pie chart
        $totalActive = $activeSites;
        $versionData = $versionData->map(function ($stat) use ($totalActive) {
            return [
                'version' => $stat['version'],
                'count' => $stat['count'],
                'percentage' => $totalActive > 0
                    ? round(($stat['count'] / $totalActive) * 100, 1)
                    : 0
            ];
        });

        return view('home', [
            'totalSites' => $totalSites,
            'activeSites' => $activeSites,
            'activeSitesWithLicense' => $activeSitesWithLicense,
            'activeSitesNoLicense' => $activeSitesNoLicense,
            'versionData' => $versionData,
        ]);
    }
}
