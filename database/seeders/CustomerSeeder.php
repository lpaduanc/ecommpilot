<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\SyncedCustomer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $stores = Store::all();

        $customerNames = [
            ['name' => 'Lucas Ferreira', 'email' => 'lucas.ferreira@gmail.com', 'phone' => '(11) 99111-1111'],
            ['name' => 'Fernanda Lima', 'email' => 'fernanda.lima@hotmail.com', 'phone' => '(11) 99222-2222'],
            ['name' => 'Ricardo Souza', 'email' => 'ricardo.souza@yahoo.com.br', 'phone' => '(21) 99333-3333'],
            ['name' => 'Patrícia Alves', 'email' => 'patricia.alves@gmail.com', 'phone' => '(21) 99444-4444'],
            ['name' => 'Gabriel Santos', 'email' => 'gabriel.santos@outlook.com', 'phone' => '(31) 99555-5555'],
            ['name' => 'Juliana Costa', 'email' => 'juliana.costa@gmail.com', 'phone' => '(31) 99666-6666'],
            ['name' => 'Marcos Oliveira', 'email' => 'marcos.oliveira@terra.com.br', 'phone' => '(41) 99777-7777'],
            ['name' => 'Camila Rodrigues', 'email' => 'camila.rodrigues@gmail.com', 'phone' => '(41) 99888-8888'],
            ['name' => 'Bruno Mendes', 'email' => 'bruno.mendes@icloud.com', 'phone' => '(51) 99999-9999'],
            ['name' => 'Amanda Pereira', 'email' => 'amanda.pereira@gmail.com', 'phone' => '(51) 99000-0000'],
            ['name' => 'Thiago Barbosa', 'email' => 'thiago.barbosa@hotmail.com', 'phone' => '(61) 98111-1111'],
            ['name' => 'Larissa Martins', 'email' => 'larissa.martins@gmail.com', 'phone' => '(61) 98222-2222'],
            ['name' => 'Felipe Gomes', 'email' => 'felipe.gomes@yahoo.com.br', 'phone' => '(71) 98333-3333'],
            ['name' => 'Vanessa Ribeiro', 'email' => 'vanessa.ribeiro@gmail.com', 'phone' => '(71) 98444-4444'],
            ['name' => 'Rafael Cardoso', 'email' => 'rafael.cardoso@outlook.com', 'phone' => '(81) 98555-5555'],
            ['name' => 'Natália Araújo', 'email' => 'natalia.araujo@gmail.com', 'phone' => '(81) 98666-6666'],
            ['name' => 'Diego Nascimento', 'email' => 'diego.nascimento@gmail.com', 'phone' => '(85) 98777-7777'],
            ['name' => 'Priscila Moura', 'email' => 'priscila.moura@hotmail.com', 'phone' => '(85) 98888-8888'],
            ['name' => 'Leandro Teixeira', 'email' => 'leandro.teixeira@icloud.com', 'phone' => '(91) 98999-9999'],
            ['name' => 'Tatiane Freitas', 'email' => 'tatiane.freitas@gmail.com', 'phone' => '(91) 98000-0000'],
        ];

        foreach ($stores as $store) {
            // Randomize number of customers per store (10-20)
            $numCustomers = rand(10, 20);
            $shuffledCustomers = collect($customerNames)->shuffle()->take($numCustomers);

            foreach ($shuffledCustomers as $index => $customer) {
                $totalOrders = rand(1, 15);
                $avgOrderValue = rand(100, 800);
                $totalSpent = $totalOrders * $avgOrderValue;

                SyncedCustomer::create([
                    'store_id' => $store->id,
                    'external_id' => 'CUST'.$store->id.str_pad($index + 1, 5, '0', STR_PAD_LEFT),
                    'name' => $customer['name'],
                    'email' => $customer['email'],
                    'phone' => $customer['phone'],
                    'total_orders' => $totalOrders,
                    'total_spent' => $totalSpent,
                    'external_created_at' => now()->subDays(rand(30, 365)),
                ]);
            }
        }
    }
}
