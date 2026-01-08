<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@plataforma.com'],
            [
                'name' => 'Administrador',
                'password' => 'changeme123',
                'phone' => '(11) 99999-9999',
                'role' => UserRole::Admin,
                'is_active' => true,
                'must_change_password' => true,
                'ai_credits' => 100,
                'email_verified_at' => now(),
            ]
        );

        // Assign admin role
        $admin->assignRole('admin');
    }
}
