<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeduplicateCustomersCommand extends Command
{
    protected $signature = 'customers:deduplicate
                            {--store= : Specific store ID (optional, runs all if not provided)}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Remove order-derived placeholder customers that now have a real Nuvemshop record with the same email';

    public function handle(): int
    {
        $storeId = $this->option('store');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN mode — no records will be deleted.');
        }

        $this->info('Scanning for order-derived duplicates...');

        // Find order-derived rows whose email already has a real (numeric external_id) counterpart
        // in the same store. These are safe to remove.
        $storeFilter = $storeId ? 'AND sc_derived.store_id = ?' : '';
        $bindings = $storeId ? [(int) $storeId] : [];

        $duplicates = DB::select("
            SELECT
                sc_derived.id,
                sc_derived.store_id,
                sc_derived.email,
                sc_derived.external_id AS derived_external_id,
                sc_real.external_id    AS real_external_id
            FROM synced_customers sc_derived
            JOIN synced_customers sc_real
              ON  sc_real.store_id  = sc_derived.store_id
              AND lower(sc_real.email) = lower(sc_derived.email)
              AND sc_real.external_id  NOT LIKE 'order-derived-%'
              AND sc_real.deleted_at   IS NULL
            WHERE sc_derived.external_id LIKE 'order-derived-%'
              AND sc_derived.deleted_at IS NULL
              {$storeFilter}
        ", $bindings);

        $count = count($duplicates);

        if ($count === 0) {
            $this->info('No duplicates found. Nothing to do.');

            return Command::SUCCESS;
        }

        $this->info("Found {$count} order-derived record(s) with a matching real Nuvemshop customer.");

        if ($dryRun) {
            $headers = ['id', 'store_id', 'email', 'derived_external_id', 'real_external_id'];
            $rows = array_map(fn ($d) => [
                $d->id,
                $d->store_id,
                $d->email,
                $d->derived_external_id,
                $d->real_external_id,
            ], array_slice($duplicates, 0, 20));

            $this->table($headers, $rows);

            if ($count > 20) {
                $this->line('... and '.($count - 20).' more.');
            }

            return Command::SUCCESS;
        }

        $ids = array_column($duplicates, 'id');

        // Hard-delete (forceDelete via raw) since SyncedCustomer uses SoftDeletes
        // and soft-deleted duplicates would still appear in aggregate counts.
        $chunks = array_chunk($ids, 500);
        $deleted = 0;

        $bar = $this->output->createProgressBar(count($chunks));
        $bar->start();

        foreach ($chunks as $chunk) {
            $deleted += DB::table('synced_customers')
                ->whereIn('id', $chunk)
                ->delete();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. {$deleted} duplicate record(s) permanently removed.");

        return Command::SUCCESS;
    }
}
