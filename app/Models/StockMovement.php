<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $product_id
 * @property int $warehouse_id
 * @property int|null $user_id
 * @property string $movement_type
 * @property numeric-string|float $quantity
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $note
 * @property-read Product $product
 * @property-read Warehouse $warehouse
 * @property-read User|null $user
 * @property-read Model|null $reference
 */
class StockMovement extends Model
{
    protected $fillable = [
        'product_id', 'warehouse_id', 'user_id', 'movement_type', 'quantity', 'reference_type', 'reference_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
