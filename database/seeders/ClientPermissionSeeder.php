<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class ClientPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // User Management
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // Dashboard
            'dashboard.view',

            // Products
            'products.view',
            'products.edit',

            // Orders
            'orders.view',
            'orders.edit',

            // Analysis
            'analysis.view',
            'analysis.request',

            // Chat
            'chat.use',

            // Settings
            'settings.view',
            'settings.edit',

            // Integrations
            'integrations.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
}
