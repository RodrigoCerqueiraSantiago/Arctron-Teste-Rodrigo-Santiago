<?php

namespace App\Integrations\WooCommerce;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderMapper
{
    public function map(array $order): ProcessPurchaseCommand
    {
        // WooCommerce does not standardize CPF: use billing.cpf or order/billing metadata.
        // Missing CPF is rejected rather than inventing a customer identity.
        $cpf = collect($order['billing']['meta_data'] ?? [])->concat($order['meta_data'] ?? [])
            ->first(fn ($item) => in_array($item['key'] ?? '', ['_billing_cpf', 'billing_cpf', 'cpf'], true));
        $cpfValue = $order['billing']['cpf'] ?? $cpf['value'] ?? null;
        if (! is_string($cpfValue) && ! is_numeric($cpfValue)) {
            throw ValidationException::withMessages(['customer.cpf' => 'WooCommerce order must include a billing CPF.']);
        }

        $data = Validator::make([
            'id' => $order['id'] ?? null,
            'amount' => $order['total'] ?? null,
            'discount' => $order['discount_total'] ?? null,
            'purchased_at' => $order['date_created_gmt'] ?? $order['date_created'] ?? null,
            'notes' => $order['customer_note'] ?? null,
            'customer' => [
                'name' => trim(($order['billing']['first_name'] ?? '').' '.($order['billing']['last_name'] ?? '')),
                'email' => $order['billing']['email'] ?? null,
                'cpf' => preg_replace('/\D/', '', (string) $cpfValue),
            ],
            'products' => collect($order['line_items'] ?? [])->map(fn ($item) => [
                'name' => $item['name'] ?? null,
                'quantity' => $item['quantity'] ?? null,
                'unit_price' => $item['price'] ?? null,
            ])->all(),
        ], [
            'id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'discount' => ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'purchased_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.email' => ['required', 'email', 'max:255'],
            'customer.cpf' => ['required', 'digits:11'],
            'products' => ['required', 'array', 'min:1', 'max:1000'],
            'products.*.name' => ['required', 'string', 'max:255'],
            'products.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'products.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999', 'decimal:0,2'],
        ])->validate();

        return new ProcessPurchaseCommand(
            reference: 'woocommerce:'.$data['id'],
            amountCents: self::cents($data['amount']),
            discountCents: self::cents($data['discount']),
            purchasedAt: $data['purchased_at'],
            customer: $data['customer'],
            products: array_map(fn (array $product) => [
                'name' => $product['name'],
                'quantity' => (int) $product['quantity'],
                'unit_price_cents' => self::cents($product['unit_price']),
            ], $data['products']),
            notes: $data['notes'] ?? null,
        );
    }

    private static function cents(string|int|float $value): int
    {
        $parts = explode('.', (string) $value);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }
}
