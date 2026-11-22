<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SimulateWooCommerceOrder extends Command
{
    protected $signature = 'woocommerce:simulate-order {--reference= : ID numérico do pedido} {--url= : URL do webhook}';

    protected $description = 'Envia um pedido fictício assinado ao webhook WooCommerce';

    public function handle(): int
    {
        $id = $this->option('reference') ?: (string) random_int(100000000, 999999999);
        if (! is_string($id) || ! ctype_digit($id) || (int) $id < 1) {
            $this->error('A referência deve ser um ID numérico positivo de WooCommerce.');

            return self::FAILURE;
        }
        $secret = config('services.woocommerce.webhook_secret');
        if (! is_string($secret) || $secret === '') {
            $this->error('WOOCOMMERCE_WEBHOOK_SECRET não configurado.');

            return self::FAILURE;
        }
        $payload = json_encode([
            'id' => (int) $id, 'status' => 'processing', 'currency' => 'BRL',
            'total' => '189.90', 'discount_total' => '10.00',
            'date_created_gmt' => now()->utc()->format('Y-m-d\TH:i:s'),
            'customer_note' => 'Pedido fictício de integração',
            'billing' => ['first_name' => 'Cliente', 'last_name' => 'Teste', 'email' => 'cliente@example.test', 'cpf' => '12345678901'],
            'line_items' => [['name' => 'Camisa de teste', 'quantity' => 2, 'price' => '99.95']],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response = Http::timeout(15)->withHeaders([
            'Accept' => 'application/json',
            'X-WC-Webhook-Topic' => 'order.created',
            'X-WC-Webhook-Signature' => base64_encode(hash_hmac('sha256', $payload, $secret, true)),
        ])->withBody($payload, 'application/json')
            ->post($this->option('url') ?: config('services.woocommerce.webhook_url'));

        if (! $response->successful()) {
            $this->error('Webhook retornou HTTP '.$response->status().': '.$response->body());

            return self::FAILURE;
        }
        $this->info('Referência: woocommerce:'.$id.'; HTTP '.$response->status().'; '.$response->body());

        return self::SUCCESS;
    }
}
