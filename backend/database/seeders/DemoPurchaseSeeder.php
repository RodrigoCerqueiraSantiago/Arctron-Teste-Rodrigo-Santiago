<?php

namespace Database\Seeders;

use App\Services\PurchaseService;
use Illuminate\Database\Seeder;

class DemoPurchaseSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(PurchaseService::class);
        $products = ['Camisa esportiva', 'Bola de treino', 'Garrafa térmica', 'Boné esportivo', 'Mochila', 'Meias esportivas'];

        for ($i = 1; $i <= 12; $i++) {
            $service->store([
                'reference' => sprintf('DEMO-%03d', $i),
                'amount' => number_format(75 + $i * 12, 2, '.', ''),
                'discount' => '5.00',
                'purchased_at' => sprintf('2026-01-%02dT12:00:00Z', $i),
                'customer' => [
                    'name' => sprintf('Cliente fictício %02d', $i),
                    'email' => sprintf('demo%02d@example.com', $i),
                    'cpf' => sprintf('000000%05d', $i),
                ],
                'products' => [[
                    'name' => $products[($i - 1) % count($products)],
                    'quantity' => 1,
                    'unit_price' => number_format(80 + $i * 12, 2, '.', ''),
                ]],
                'notes' => 'Dados fictícios de demonstração.',
            ]);
        }
    }
}
