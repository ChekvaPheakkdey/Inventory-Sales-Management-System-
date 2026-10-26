<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @mixin Builder
 *
 * @method static Builder lockForUpdate()
 * @method static Builder with(array|string $relations)
 * @method static static create(array $attributes = [])
 * @method static static findOrFail(int|string $id, array|string $columns = ['*'])
 *
 * @property int $id
 * @property int $supplier_id
 * @property int $user_id
 * @property int $warehouse_id
 * @property string $order_number
 * @property string $status
 * @property numeric-string|float $total_amount
 * @property Carbon|null $received_at
 * @property-read Supplier $supplier
 * @property-read User $user
 * @property-read Collection<int, PurchaseItem> $items
 */
class PurchaseOrder extends Model
{
    protected $fillable = [
        'supplier_id', 'user_id', 'warehouse_id', 'order_number', 'status', 'total_amount', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}
