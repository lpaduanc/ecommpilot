<?php

namespace Database\Seeders;

use App\Enums\Platform;
use App\Enums\SyncStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('role', 'client')->get();

        $stores = [
            [
                'email' => 'maria@lojadamaria.com.br',
                'stores' => [
                    [
                        'platform' => Platform::Nuvemshop,
                        'external_store_id' => '1234567',
                        'name' => 'Loja da Maria',
                        'domain' => 'lojadamaria.lojavirtualnuvem.com.br',
                        'email' => 'contato@lojadamaria.com.br',
                        'sync_status' => SyncStatus::Completed,
                        'last_sync_at' => now()->subHours(2),
                        'metadata' => [
                            'plan' => 'Impulso',
                            'currency' => 'BRL',
                            'country' => 'BR',
                        ],
                    ],
                ],
            ],
            [
                'email' => 'joao@techstore.com.br',
                'stores' => [
                    [
                        'platform' => Platform::Nuvemshop,
                        'external_store_id' => '2345678',
                        'name' => 'Tech Store Brasil',
                        'domain' => 'techstorebrasil.lojavirtualnuvem.com.br',
                        'email' => 'vendas@techstore.com.br',
                        'sync_status' => SyncStatus::Completed,
                        'last_sync_at' => now()->subHours(1),
                        'metadata' => [
                            'plan' => 'Escalar',
                            'currency' => 'BRL',
                            'country' => 'BR',
                        ],
                    ],
                ],
            ],
            [
                'email' => 'ana@modafeminina.com.br',
                'stores' => [
                    [
                        'platform' => Platform::Nuvemshop,
                        'external_store_id' => '3456789',
                        'name' => 'Moda Feminina Ana',
                        'domain' => 'modafeminina.lojavirtualnuvem.com.br',
                        'email' => 'ana@modafeminina.com.br',
                        'sync_status' => SyncStatus::Completed,
                        'last_sync_at' => now()->subMinutes(30),
                        'metadata' => [
                            'plan' => 'Próximo Nível',
                            'currency' => 'BRL',
                            'country' => 'BR',
                        ],
                    ],
                ],
            ],
            [
                'email' => 'pedro@esportemania.com.br',
                'stores' => [
                    [
                        'platform' => Platform::Nuvemshop,
                        'external_store_id' => '4567890',
                        'name' => 'Esporte Mania',
                        'domain' => 'esportemania.lojavirtualnuvem.com.br',
                        'email' => 'contato@esportemania.com.br',
                        'sync_status' => SyncStatus::Syncing,
                        'last_sync_at' => now()->subDays(1),
                        'metadata' => [
                            'plan' => 'Impulso',
                            'currency' => 'BRL',
                            'country' => 'BR',
                        ],
                    ],
                ],
            ],
        ];

        foreach ($stores as $storeData) {
            $user = User::where('email', $storeData['email'])->first();
            
            if (!$user) {
                continue;
            }

            foreach ($storeData['stores'] as $store) {
                Store::create([
                    'user_id' => $user->id,
                    'platform' => $store['platform'],
                    'external_store_id' => $store['external_store_id'],
                    'name' => $store['name'],
                    'domain' => $store['domain'],
                    'email' => $store['email'],
                    'access_token' => 'demo_access_token_' . $store['external_store_id'],
                    'refresh_token' => 'demo_refresh_token_' . $store['external_store_id'],
                    'sync_status' => $store['sync_status'],
                    'last_sync_at' => $store['last_sync_at'],
                    'metadata' => $store['metadata'],
                ]);
            }
        }
    }
}

