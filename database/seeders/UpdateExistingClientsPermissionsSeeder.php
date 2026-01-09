<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class UpdateExistingClientsPermissionsSeeder extends Seeder
{
    /**
     * Atualiza as permissões de todos os clientes existentes.
     * Dá todas as permissões de cliente para cada cliente que ainda não tem.
     */
    public function run(): void
    {
        // Todas as permissões disponíveis para clientes
        $allClientPermissions = [
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'dashboard.view',
            'products.view',
            'products.edit',
            'orders.view',
            'orders.edit',
            'analysis.view',
            'analysis.request',
            'chat.use',
            'settings.view',
            'settings.edit',
            'integrations.manage',
        ];

        // Buscar permissões que existem no banco
        $clientPermissions = Permission::whereIn('name', $allClientPermissions)
            ->pluck('name')->toArray();

        // Buscar todos os clientes (role = Client e sem parent_user_id, ou seja, clientes principais)
        $clients = User::where('role', UserRole::Client)
            ->whereNull('parent_user_id')
            ->get();

        $updated = 0;

        foreach ($clients as $client) {
            // Verifica quantas permissões o cliente já tem
            $currentPermissions = $client->permissions->pluck('name')->toArray();

            // Se não tem todas as permissões, sincroniza
            if (count($currentPermissions) < count($clientPermissions)) {
                $client->syncPermissions($clientPermissions);
                $updated++;
                $this->command->info("Cliente '{$client->name}' ({$client->email}) atualizado com todas as permissões.");
            }
        }

        $this->command->info("Total de clientes atualizados: {$updated}");
        $this->command->info('Todos os clientes existentes agora têm todas as permissões.');
    }
}
