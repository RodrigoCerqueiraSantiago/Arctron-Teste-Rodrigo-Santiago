<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchasesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function purchase(array $overrides = []): array
    {
        return array_replace_recursive([
            'reference' => 'PED-1',
            'amount' => '100.00',
            'discount' => '10.00',
            'purchased_at' => '2026-01-15T12:00:00Z',
            'customer' => ['name' => 'Ana Silva', 'email' => 'ana@example.com', 'cpf' => '123.456.789-01'],
            'products' => [['name' => 'Café', 'quantity' => 2, 'unit_price' => '25.00']],
            'notes' => 'Enviado',
        ], $overrides);
    }

    public function test_creation_is_idempotent_and_invalid_payloads_are_rejected(): void
    {
        $this->postJson('/api/purchases', $this->purchase())->assertCreated()->assertJsonPath('created', true);
        $this->postJson('/api/purchases', $this->purchase(['amount' => '300.00']))->assertOk()->assertJsonPath('created', false)->assertJsonPath('data.amount_cents', 10000);
        $this->postJson('/api/purchases', ['reference' => 'PED-1'])->assertOk()->assertJsonPath('created', false);
        $this->postJson('/api/purchases', $this->purchase(['reference' => 'BAD', 'customer' => ['cpf' => '123']]))->assertUnprocessable();
        $this->assertDatabaseCount('purchases', 1);
        $this->assertDatabaseCount('purchase_items', 1);
    }

    public function test_filters_and_aggregates_use_purchase_and_product_totals(): void
    {
        $this->postJson('/api/purchases', $this->purchase())->assertCreated();
        $this->postJson('/api/purchases', $this->purchase([
            'reference' => 'PED-2', 'amount' => '200.00', 'discount' => '20.00',
            'purchased_at' => '2026-02-20T12:00:00Z',
            'customer' => ['name' => 'Bia', 'email' => 'bia@example.com', 'cpf' => '98765432100'],
            'products' => [['name' => 'Café', 'quantity' => 3, 'unit_price' => '20.00']],
        ]))->assertCreated();
        $this->getJson('/api/purchases?reference=PED-2&customer=987.654.321-00&date_from=2026-02-01&date_to=2026-02-28&amount_min=199&amount_max=201')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.reference', 'PED-2');
        $this->getJson('/api/purchases?customer=ana%40example.com')->assertJsonPath('total', 1);
        $this->getJson('/api/purchases?date_to=2026-01-31')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/purchases?amount_max=100')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/purchases?date_from=2026-03-01&date_to=2026-01-01')->assertUnprocessable();
        $response = $this->getJson('/api/dashboard')->assertOk();
        $this->assertEquals(2, $response->json('summary.purchase_count'));
        $this->assertEquals(30000, $response->json('summary.purchase_total_cents'));
        $this->assertEquals(15000, $response->json('summary.purchase_average_cents'));
        $this->assertEquals(3000, $response->json('summary.discount_total_cents'));
        $this->assertEquals(1500, $response->json('summary.discount_average_cents'));
        $this->assertEquals(11000, $response->json('summary.product_total_cents'));
        $this->assertEquals(5500, $response->json('summary.product_average_cents'));
        $this->assertEquals(11000, $response->json('products.0.total_cents'));
        $this->assertEquals(5500, $response->json('products.0.average_cents'));
        $this->assertEquals(20000, $response->json('customers.0.total_cents'));
    }

    public function test_woocommerce_webhook_authenticates_and_imports_only_processed_orders(): void
    {
        config(['services.woocommerce.webhook_secret' => 'test-secret']);
        $order = [
            'id' => 42, 'status' => 'processing', 'total' => '100.00', 'discount_total' => '10.00',
            'date_created_gmt' => '2026-01-15T12:00:00',
            'billing' => ['first_name' => 'Ana', 'last_name' => 'Silva', 'email' => 'ana@example.com'],
            'meta_data' => [['key' => '_billing_cpf', 'value' => '12345678901']],
            'line_items' => [['name' => 'Café', 'quantity' => 2, 'price' => '25.00']],
        ];
        $body = json_encode($order);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_WC_WEBHOOK_TOPIC' => 'order.updated'];
        $this->call('POST', '/api/webhooks/woocommerce', [], [], [], $headers + ['HTTP_X_WC_WEBHOOK_SIGNATURE' => 'bad'], $body)->assertUnauthorized();
        $headers['HTTP_X_WC_WEBHOOK_SIGNATURE'] = base64_encode(hash_hmac('sha256', $body, 'test-secret', true));
        $this->call('POST', '/api/webhooks/woocommerce', [], [], [], $headers, $body)->assertCreated()->assertJsonPath('reference', 'woocommerce:42');
        $this->call('POST', '/api/webhooks/woocommerce', [], [], [], $headers, $body)->assertOk()->assertJsonPath('created', false);
        $this->assertDatabaseCount('purchases', 1);
        $order['id'] = 43;
        $order['status'] = 'pending';
        $body = json_encode($order);
        $headers['HTTP_X_WC_WEBHOOK_SIGNATURE'] = base64_encode(hash_hmac('sha256', $body, 'test-secret', true));
        $this->call('POST', '/api/webhooks/woocommerce', [], [], [], $headers, $body)->assertOk()->assertJsonPath('ignored', true);
        $this->assertDatabaseCount('purchases', 1);
    }

    public function test_empty_dashboard_returns_zeroes(): void
    {
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('summary.purchase_count', 0)->assertJsonPath('summary.product_average_cents', 0)->assertJsonCount(0, 'products');
    }

    public function test_reference_name_and_email_search_ignore_letter_case(): void
    {
        $this->postJson('/api/purchases', $this->purchase())->assertCreated();

        foreach (['reference=ped-1', 'customer=ana%20silva', 'customer=ANA%40EXAMPLE.COM'] as $filter) {
            $this->getJson('/api/purchases?'.$filter)->assertOk()
                ->assertJsonPath('total', 1)->assertJsonPath('data.0.reference', 'PED-1');
        }

        $this->getJson('/api/purchases?customer=outro')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_dates_are_normalized_to_utc_before_filtering(): void
    {
        $this->postJson('/api/purchases', $this->purchase(['purchased_at' => '2026-01-15T23:30:00-03:00']))->assertCreated();
        $this->getJson('/api/purchases?date_from=2026-01-16&date_to=2026-01-16')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/purchases?date_to=2026-01-15')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_reference_zero_and_customer_search_do_not_match_unrelated_cpf_digits(): void
    {
        $this->postJson('/api/purchases', $this->purchase(['reference' => '0']))->assertCreated();
        $this->postJson('/api/purchases', $this->purchase([
            'reference' => 'OTHER',
            'customer' => ['name' => 'Cliente 123', 'email' => 'cliente123@example.com', 'cpf' => '98765432100'],
        ]))->assertCreated();

        $this->getJson('/api/purchases?reference=0')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.reference', '0');
        $this->getJson('/api/purchases?customer=cliente123%40example.com')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.reference', 'OTHER');
        $this->getJson('/api/purchases?customer=123.456')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.reference', '0');
    }

    public function test_personal_data_is_encrypted_and_partial_search_still_works(): void
    {
        $this->postJson('/api/purchases', $this->purchase())->assertCreated();
        $this->postJson('/api/purchases', $this->purchase(['reference' => 'PED-2']))->assertCreated();

        $customer = DB::table('customers')->first();
        $purchase = DB::table('purchases')->first();
        $this->assertNotEquals('12345678901', $customer->cpf);
        $this->assertNotEquals('ana@example.com', $customer->email);
        $this->assertNotEquals('12345678901', $purchase->customer_cpf);
        $this->assertNotEquals('ana@example.com', $purchase->customer_email);
        $this->assertNotEquals('Enviado', $purchase->notes);
        $this->assertDatabaseCount('customers', 1);
        $this->getJson('/api/purchases?customer=EXAMPLE')->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/purchases?customer=456.789')->assertOk()->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.customer_cpf', '12345678901');
    }

    public function test_personal_data_search_paginates_after_matching(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->postJson('/api/purchases', $this->purchase(['reference' => "PED-$i"]))->assertCreated();
        }

        $this->getJson('/api/purchases?customer=example&page=2')->assertOk()
            ->assertJsonPath('total', 12)->assertJsonPath('current_page', 2)
            ->assertJsonCount(2, 'data');
    }
}
