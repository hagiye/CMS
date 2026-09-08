<?php

namespace App\Console\Commands;

use App\Jobs\ImportAuNews;
use Illuminate\Console\Command;

class ImportAuNewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'au-news:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue an import of the latest African Union news and media items.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        ImportAuNews::dispatch();

        $this->info('AU News import queued.');

        return self::SUCCESS;
    }
}
