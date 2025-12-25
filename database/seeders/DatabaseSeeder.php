<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Core seeders (required)
            PermissionSeeder::class,
            AdminSeeder::class,

            // Demo data seeders
            UserSeeder::class,
            StoreSeeder::class,
            ProductSeeder::class,
            CustomerSeeder::class,
            OrderSeeder::class,
            AnalysisSeeder::class,
        ]);
    }
}
