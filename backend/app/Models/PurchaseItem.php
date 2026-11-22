<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = ['product_id', 'quantity', 'unit_price_cents'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
