<?php

namespace App\Console\Commands;

use App\Services\ProgressPlannerService;
use App\Services\SiteStatsService;
use Illuminate\Console\Command;

class FetchProgressPlannerData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'progress-planner:fetch {--force : Force refresh cached data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Progress Planner registered sites and their stats';

    /**
     * Execute the console command.
     */
    public function handle(ProgressPlannerService $progressPlannerService, SiteStatsService $siteStatsService)
    {
        $this->info('Starting Progress Planner data fetch...');

        // Step 1: Fetch registered sites
        $this->info('Fetching registered sites...');
        $forceRefresh = $this->option('force');
        $sites = $progressPlannerService->fetchAndCacheRegisteredSites($forceRefresh);

        if (empty($sites)) {
            $this->error('No sites data retrieved from API');
            return Command::FAILURE;
        }

        $this->info('Found ' . count($sites) . ' registered sites');

        // Step 2: Sync to database
        $this->info('Syncing sites to database...');
        $progressPlannerService->syncRegisteredSitesToDatabase($sites);
        $this->info('Sites synced successfully');

        // Step 3: Fetch stats for each site
        $this->info('Fetching stats for each site...');
        $bar = $this->output->createProgressBar(count($sites));
        $bar->start();

        $results = $siteStatsService->fetchAllSiteStats();

        $bar->finish();
        $this->newLine(2);

        // Display results
        $this->info('Data fetch completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Sites', $results['total']],
                ['Successful', $results['successful']],
                ['Failed', $results['failed']],
            ]
        );

        return Command::SUCCESS;
    }
}
