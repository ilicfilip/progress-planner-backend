<?php

namespace App\Jobs;

use App\Services\CloudflareWorkerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchSiteHtmlJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $domains
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CloudflareWorkerService $workerService): void
    {
        if (empty($this->domains)) {
            Log::info('No domains to fetch HTML for');
            return;
        }

        $results = $workerService->fetchHtmlContent($this->domains);

        Log::info('HTML fetch completed', [
            'successful' => count($results['successful']),
            'failed' => count($results['failed']),
        ]);

        // If there were failures, we could optionally retry them
        if (!empty($results['failed'])) {
            Log::warning('Some domains failed to fetch', [
                'domains' => $results['failed'],
            ]);
        }
    }
}
