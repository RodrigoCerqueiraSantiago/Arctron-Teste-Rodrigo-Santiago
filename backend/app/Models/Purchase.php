<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = ['reference', 'customer_id', 'customer_name', 'customer_email', 'customer_cpf', 'amount_cents', 'discount_cents', 'purchased_at', 'notes'];

    protected function casts(): array
    {
        return ['purchased_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
