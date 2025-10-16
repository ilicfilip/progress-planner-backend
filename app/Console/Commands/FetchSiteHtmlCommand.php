<?php

namespace App\Console\Commands;

use App\Jobs\QueueSiteHtmlFetchJob;
use Illuminate\Console\Command;

class FetchSiteHtmlCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sites:fetch-html {--force : Force fetch all domains regardless of last fetch time} {--domains=* : Specific domains to fetch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue site HTML fetching via Cloudflare Worker';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $force = $this->option('force');
        $domains = $this->option('domains');

        $this->info('Queueing site HTML fetch...');

        QueueSiteHtmlFetchJob::dispatch($domains, $force);

        $this->info('HTML fetch job queued successfully!');
        $this->info('HTML content will be fetched in approximately 60 seconds.');

        return Command::SUCCESS;
    }
}
