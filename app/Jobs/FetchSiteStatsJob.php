<?php

namespace App\Jobs;

use App\Models\RegisteredSite;
use App\Services\SiteStatsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchSiteStatsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public RegisteredSite $site
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SiteStatsService $siteStatsService): void
    {
        $siteStatsService->fetchSiteStats($this->site);
    }
}
