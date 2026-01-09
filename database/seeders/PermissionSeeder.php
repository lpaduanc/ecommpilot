<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Dashboard
            ['name' => 'dashboard.view', 'label' => 'Visualizar Dashboard'],

            // Products
            ['name' => 'products.view', 'label' => 'Visualizar Produtos'],

            // Orders
            ['name' => 'orders.view', 'label' => 'Visualizar Pedidos'],

            // Users
            ['name' => 'users.create', 'label' => 'Criar Usuários'],
            ['name' => 'users.edit', 'label' => 'Editar Usuários'],
            ['name' => 'users.delete', 'label' => 'Excluir Usuários'],
            ['name' => 'users.view', 'label' => 'Visualizar Usuários'],

            // Integrations
            ['name' => 'integrations.manage', 'label' => 'Gerenciar Integrações'],

            // Analytics
            ['name' => 'analysis.view', 'label' => 'Visualizar Análises'],
            ['name' => 'analysis.request', 'label' => 'Solicitar Análise'],

            // Chat
            ['name' => 'chat.use', 'label' => 'Usar Chat IA'],

            // Marketing
            ['name' => 'marketing.access', 'label' => 'Acessar Marketing e Descontos'],

            // Settings
            ['name' => 'settings.view', 'label' => 'Visualizar Configurações'],
            ['name' => 'settings.edit', 'label' => 'Editar Configurações'],

            // Admin
            ['name' => 'admin.access', 'label' => 'Acesso Administrativo'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
            );
        }

        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $clientRole = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        // Assign all permissions to admin
        $adminRole->syncPermissions(Permission::all());

        // Assign limited permissions to client
        $clientRole->syncPermissions([
            'dashboard.view',
            'products.view',
            'orders.view',
            'integrations.manage',
            'analysis.view',
            'analysis.request',
            'chat.use',
            'marketing.access',
            'settings.view',
            'settings.edit',
        ]);
    }
}
