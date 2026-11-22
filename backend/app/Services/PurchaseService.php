<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Support\CustomerCpfIndex;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseService
{
    public function store(array $input): array
    {
        $reference = Validator::make($input, ['reference' => ['required', 'string', 'max:255']])->validate()['reference'];
        $existing = Purchase::where('reference', $reference)->first();
        if ($existing) {
            return [$existing->load('items.product'), false];
        }

        if (isset($input['customer']['cpf']) && is_string($input['customer']['cpf'])) {
            $input['customer']['cpf'] = preg_replace('/\D/', '', $input['customer']['cpf']);
        }

        $data = Validator::make($input, [
            'reference' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'discount' => ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'purchased_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.email' => ['required', 'email', 'max:255'],
            'customer.cpf' => ['required', 'digits:11'],
            'products' => ['required', 'array', 'min:1', 'max:1000'],
            'products.*.name' => ['required', 'string', 'max:255'],
            'products.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'products.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999', 'decimal:0,2'],
        ])->validate();

        return DB::transaction(function () use ($data) {
            $existing = Purchase::where('reference', $data['reference'])->first();
            if ($existing) {
                return [$existing->load('items.product'), false];
            }

            $customer = Customer::firstOrCreate(
                ['cpf_hash' => CustomerCpfIndex::hash($data['customer']['cpf'])],
                ['cpf' => $data['customer']['cpf'], 'name' => $data['customer']['name'], 'email' => $data['customer']['email']]
            );
            $purchase = Purchase::firstOrCreate(['reference' => $data['reference']], [
                'customer_id' => $customer->id,
                'customer_name' => $data['customer']['name'],
                'customer_email' => $data['customer']['email'],
                'customer_cpf' => $data['customer']['cpf'],
                'amount_cents' => self::cents($data['amount']),
                'discount_cents' => self::cents($data['discount']),
                'purchased_at' => Carbon::parse($data['purchased_at'])->utc(),
                'notes' => $data['notes'] ?? null,
            ]);
            if (! $purchase->wasRecentlyCreated) {
                return [$purchase->load('items.product'), false];
            }
            foreach ($data['products'] as $item) {
                $product = Product::firstOrCreate(['name' => $item['name']]);
                $purchase->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price_cents' => self::cents($item['unit_price']),
                ]);
            }

            return [$purchase->load('items.product'), true];
        });
    }

    private static function cents(string|int|float $value): int
    {
        return (int) round((float) $value * 100);
    }
}
