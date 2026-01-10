<?php

namespace App\Console\Commands;

use App\Jobs\SyncBrazilLocationsJob;
use Illuminate\Console\Command;

class SyncBrazilLocationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'locations:sync-brazil';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Brazilian states and cities from IBGE API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Brazil locations sync...');

        try {
            // Execute job synchronously
            SyncBrazilLocationsJob::dispatchSync();

            $this->info('Brazil locations synced successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to sync Brazil locations: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
