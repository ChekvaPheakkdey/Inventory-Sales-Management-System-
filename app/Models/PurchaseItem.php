<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int $product_id
 * @property numeric-string|float $quantity
 * @property numeric-string|float $unit_cost
 * @property numeric-string|float $subtotal
 * @property-read Product $product
 */
class PurchaseItem extends Model
{
    protected $fillable = ['purchase_order_id', 'product_id', 'quantity', 'unit_cost', 'subtotal'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
