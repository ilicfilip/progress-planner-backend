<?php

namespace App\Jobs;

use App\Services\CloudflareWorkerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class QueueSiteHtmlFetchJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $domains = [],
        public bool $force = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CloudflareWorkerService $workerService): void
    {
        // If specific domains provided, use those, otherwise get all domains that need fetching
        $domainsToQueue = empty($this->domains)
            ? $workerService->getDomainsToFetch($this->force)
            : $this->domains;

        if (empty($domainsToQueue)) {
            Log::info('No domains to queue for HTML fetch');
            return;
        }

        $success = $workerService->queueDomains($domainsToQueue);

        if ($success) {
            // Schedule the fetch job to run after 60 seconds
            FetchSiteHtmlJob::dispatch($domainsToQueue)
                ->delay(now()->addSeconds(60));

            Log::info('Queued domains and scheduled fetch job', [
                'count' => count($domainsToQueue),
            ]);
        } else {
            Log::error('Failed to queue domains for HTML fetch');
        }
    }
}
