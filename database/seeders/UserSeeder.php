<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo client users
        $clients = [
            [
                'name' => 'Maria Silva',
                'email' => 'maria@lojadamaria.com.br',
                'phone' => '(11) 99999-1111',
                'password' => Hash::make('password123'),
                'role' => UserRole::Client,
                'is_active' => true,
                'email_verified_at' => now(),
                'ai_credits' => 100,
            ],
            [
                'name' => 'João Santos',
                'email' => 'joao@techstore.com.br',
                'phone' => '(21) 98888-2222',
                'password' => Hash::make('password123'),
                'role' => UserRole::Client,
                'is_active' => true,
                'email_verified_at' => now(),
                'ai_credits' => 50,
            ],
            [
                'name' => 'Ana Costa',
                'email' => 'ana@modafeminina.com.br',
                'phone' => '(31) 97777-3333',
                'password' => Hash::make('password123'),
                'role' => UserRole::Client,
                'is_active' => true,
                'email_verified_at' => now(),
                'ai_credits' => 75,
            ],
            [
                'name' => 'Pedro Oliveira',
                'email' => 'pedro@esportemania.com.br',
                'phone' => '(41) 96666-4444',
                'password' => Hash::make('password123'),
                'role' => UserRole::Client,
                'is_active' => true,
                'email_verified_at' => now(),
                'ai_credits' => 25,
            ],
            [
                'name' => 'Carla Mendes',
                'email' => 'carla@belezanatural.com.br',
                'phone' => '(51) 95555-5555',
                'password' => Hash::make('password123'),
                'role' => UserRole::Client,
                'is_active' => false, // Inactive user for testing
                'email_verified_at' => now(),
                'ai_credits' => 0,
            ],
        ];

        foreach ($clients as $client) {
            $user = User::create($client);
            $user->assignRole('client');
        }
    }
}

