<?php

namespace App\Integrations\WooCommerce;

final readonly class ProcessPurchaseCommand
{
    /**
     * @param  array{name: string, email: string, cpf: string}  $customer
     * @param  list<array{name: string, quantity: int, unit_price_cents: int}>  $products
     */
    public function __construct(
        public string $reference,
        public int $amountCents,
        public int $discountCents,
        public string $purchasedAt,
        public array $customer,
        public array $products,
        public ?string $notes,
    ) {}

    public function toPurchase(): array
    {
        return [
            'reference' => $this->reference,
            'amount' => self::decimal($this->amountCents),
            'discount' => self::decimal($this->discountCents),
            'purchased_at' => $this->purchasedAt,
            'customer' => $this->customer,
            'products' => array_map(fn (array $product) => [
                'name' => $product['name'],
                'quantity' => $product['quantity'],
                'unit_price' => self::decimal($product['unit_price_cents']),
            ], $this->products),
            'notes' => $this->notes,
        ];
    }

    private static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
