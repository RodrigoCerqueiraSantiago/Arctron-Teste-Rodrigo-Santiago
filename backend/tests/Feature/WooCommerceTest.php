<?php

use App\Integrations\WooCommerce\OrderMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function wooOrder(): array
{
    return [
        'id' => 42, 'status' => 'processing', 'total' => '89.90', 'discount_total' => '10.05',
        'date_created_gmt' => '2026-01-15T12:00:00',
        'billing' => ['first_name' => 'Ana', 'last_name' => 'Silva', 'email' => 'ana@example.test'],
        'meta_data' => [['key' => '_billing_cpf', 'value' => '123.456.789-01']],
        'line_items' => [['name' => 'Camisa', 'quantity' => 2, 'price' => '49.95']],
    ];
}

function signedWooRequest(array $payload, string $secret = 'test-secret'): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    return test()->call('POST', '/api/integrations/woocommerce/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_WC_WEBHOOK_TOPIC' => 'order.created',
        'HTTP_X_WC_WEBHOOK_SIGNATURE' => base64_encode(hash_hmac('sha256', $body, $secret, true)),
    ], $body);
}

beforeEach(function () {
    config(['services.woocommerce.webhook_secret' => 'test-secret']);
});

it('maps WooCommerce amounts, CPF and email to a purchase command', function () {
    $command = app(OrderMapper::class)->map(wooOrder());
    expect($command->reference)->toBe('woocommerce:42')
        ->and($command->amountCents)->toBe(8990)
        ->and($command->discountCents)->toBe(1005)
        ->and($command->products[0]['unit_price_cents'])->toBe(4995)
        ->and($command->customer['cpf'])->toBe('12345678901')
        ->and($command->customer['email'])->toBe('ana@example.test');
});

it('accepts a signed order and ignores the same reference on resubmission', function () {
    signedWooRequest(wooOrder())->assertCreated()->assertJsonPath('reference', 'woocommerce:42')->assertJsonMissingPath('data');
    $this->assertDatabaseHas('purchases', ['reference' => 'woocommerce:42', 'amount_cents' => 8990]);
    signedWooRequest(wooOrder())->assertOk()->assertJsonPath('created', false);
    $this->assertDatabaseCount('purchases', 1);
    $this->assertDatabaseCount('purchase_items', 1);
});

it('rejects invalid signatures', function () {
    signedWooRequest(wooOrder(), 'wrong-secret')->assertUnauthorized();
    $this->assertDatabaseCount('purchases', 0);
});

it('rejects an order with no CPF and supports billing cpf', function () {
    $order = wooOrder();
    $order['meta_data'] = [];
    signedWooRequest($order)->assertUnprocessable()->assertJsonValidationErrors('customer.cpf');
    $order['billing']['cpf'] = '12345678901';
    signedWooRequest($order)->assertCreated()->assertJsonPath('reference', 'woocommerce:42');
    $this->assertDatabaseHas('purchases', ['reference' => 'woocommerce:42', 'customer_cpf' => '12345678901']);
});

it('accepts the unsigned activation ping without recording a purchase', function () {
    $this->call('POST', '/api/integrations/woocommerce/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
    ], 'webhook_id=42')->assertOk();
    $this->assertDatabaseCount('purchases', 0);
});

it('sends a correctly signed simulation', function () {
    Http::fake(['*' => Http::response(['created' => true], 201)]);
    $this->artisan('woocommerce:simulate-order', [
        '--reference' => '987',
        '--url' => 'http://example.test/webhook',
    ])->assertSuccessful();

    Http::assertSent(function (Request $request) {
        $signature = base64_encode(hash_hmac('sha256', $request->body(), 'test-secret', true));

        return $request->url() === 'http://example.test/webhook'
            && $request->hasHeader('X-WC-Webhook-Signature', $signature)
            && $request['id'] === 987;
    });

});

it('reports a remote simulation failure', function () {
    Http::fake(['*' => Http::response(['message' => 'failure'], 500)]);
    $this->artisan('woocommerce:simulate-order', ['--reference' => '987'])->assertFailed();
});
