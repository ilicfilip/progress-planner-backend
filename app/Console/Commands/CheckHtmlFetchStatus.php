<?php

namespace App\Console\Commands;

use App\Services\CloudflareWorkerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CheckHtmlFetchStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sites:html-status {--clear : Clear all pending/in-progress cache}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the status of HTML snapshot fetching';

    /**
     * Execute the console command.
     */
    public function handle(CloudflareWorkerService $workerService)
    {
        if ($this->option('clear')) {
            Cache::forget('html_fetch_in_progress');
            Cache::forget('html_fetch_pending');
            $this->info('Cleared all HTML fetch cache');
            return Command::SUCCESS;
        }

        $inProgress = Cache::get('html_fetch_in_progress', []);
        $pending = Cache::get('html_fetch_pending', []);
        $readyDomains = $workerService->getReadyDomains();

        $this->info('HTML Fetch Status Report');
        $this->line('=========================');
        $this->newLine();

        // In Progress
        $this->info(sprintf('Domains queued with CF Worker: %d', count($inProgress)));
        if (!empty($inProgress) && count($inProgress) <= 10) {
            foreach (array_slice($inProgress, 0, 10) as $domain) {
                $this->line('  - ' . $domain);
            }
        } elseif (count($inProgress) > 10) {
            $this->line('  (Showing first 10 of ' . count($inProgress) . ')');
            foreach (array_slice($inProgress, 0, 10) as $domain) {
                $this->line('  - ' . $domain);
            }
        }
        $this->newLine();

        // Pending
        $this->info(sprintf('Domains pending HTML fetch: %d', count($pending)));
        if (!empty($pending)) {
            $oldestTimestamp = min(array_values($pending));
            $newestTimestamp = max(array_values($pending));
            $now = now()->timestamp;

            $this->line(sprintf('  Oldest: %d seconds ago', $now - $oldestTimestamp));
            $this->line(sprintf('  Newest: %d seconds ago', $now - $newestTimestamp));

            if (count($pending) <= 5) {
                foreach ($pending as $domain => $timestamp) {
                    $age = $now - $timestamp;
                    $this->line(sprintf('  - %s (%d seconds ago)', $domain, $age));
                }
            }
        }
        $this->newLine();

        // Ready to fetch
        $this->info(sprintf('Domains ready to fetch (>60s old): %d', count($readyDomains)));
        if (!empty($readyDomains) && count($readyDomains) <= 10) {
            foreach ($readyDomains as $domain) {
                $this->line('  - ' . $domain);
            }
        } elseif (count($readyDomains) > 10) {
            $this->line('  (Showing first 10 of ' . count($readyDomains) . ')');
            foreach (array_slice($readyDomains, 0, 10) as $domain) {
                $this->line('  - ' . $domain);
            }
        }
        $this->newLine();

        // Queue jobs
        $pendingJobs = DB::table('jobs')->count();
        $this->info(sprintf('Jobs in queue: %d', $pendingJobs));

        if ($pendingJobs > 0) {
            $jobs = DB::table('jobs')->orderBy('created_at', 'desc')->limit(5)->get();
            foreach ($jobs as $job) {
                $payload = json_decode($job->payload);
                $availableIn = $job->available_at - now()->timestamp;
                $this->line(sprintf('  - %s (available in %d seconds)', $payload->displayName, $availableIn));
            }
        }
        $this->newLine();

        // Recommendations
        if (!empty($readyDomains) && $pendingJobs == 0) {
            $this->warn('Domains are ready to fetch but no jobs are queued!');
            $this->line('Run: php artisan queue:work');
            $this->line('Or dispatch manually with: FetchSiteHtmlJob::dispatch($domains)');
        }

        if (count($inProgress) > 0 && count($pending) == 0) {
            $this->warn('Domains are in progress but not marked as pending. This might indicate an issue.');
            $this->line('Consider running: php artisan sites:html-status --clear');
        }

        return Command::SUCCESS;
    }
}
