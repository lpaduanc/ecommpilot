<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a partial unique index on (store_id, lower(email)) for synced_customers,
 * excluding soft-deleted rows.
 *
 * Before creating the index, removes any remaining order-derived placeholder rows
 * whose email already has a real (numeric external_id) record in the same store,
 * preventing a constraint violation during the migration itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: hard-delete order-derived placeholders that have a real counterpart.
        // This mirrors what DeduplicateCustomersCommand and bulkUpsertCustomers() do at
        // runtime, but runs once here to clean up historical data before adding the index.
        DB::statement("
            DELETE FROM synced_customers
            WHERE external_id LIKE 'order-derived-%'
              AND deleted_at IS NULL
              AND EXISTS (
                  SELECT 1
                  FROM synced_customers sc_real
                  WHERE sc_real.store_id  = synced_customers.store_id
                    AND lower(sc_real.email) = lower(synced_customers.email)
                    AND sc_real.external_id  NOT LIKE 'order-derived-%'
                    AND sc_real.deleted_at   IS NULL
              )
        ");

        // Step 2: add a partial unique index (excludes soft-deleted rows).
        // Using a PostgreSQL expression index (lower(email)) to make it case-insensitive.
        Schema::table('synced_customers', function ($table) {
            // Drop the non-unique index added in the original migration if it exists.
            // We replace it with the unique one below.
        });

        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS synced_customers_store_email_unique
            ON synced_customers (store_id, lower(email))
            WHERE deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS synced_customers_store_email_unique');
    }
};
