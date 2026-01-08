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
            // Users
            ['name' => 'users.create', 'label' => 'Criar Usuários'],
            ['name' => 'users.edit', 'label' => 'Editar Usuários'],
            ['name' => 'users.delete', 'label' => 'Excluir Usuários'],
            ['name' => 'users.view', 'label' => 'Visualizar Usuários'],

            // Integrations
            ['name' => 'integrations.manage', 'label' => 'Gerenciar Integrações'],

            // Analytics
            ['name' => 'analytics.view', 'label' => 'Visualizar Análises'],
            ['name' => 'analytics.request', 'label' => 'Solicitar Análise'],

            // Chat
            ['name' => 'chat.use', 'label' => 'Usar Chat IA'],

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
            'integrations.manage',
            'analytics.view',
            'analytics.request',
            'chat.use',
        ]);
    }
}
