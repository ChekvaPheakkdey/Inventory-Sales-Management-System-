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
 * @method static Builder where(string|array|\Closure $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static Builder lockForUpdate()
 * @method static Builder with(array|string $relations)
 * @method static static create(array $attributes = [])
 * @method static static findOrFail(int|string $id, array|string $columns = ['*'])
 *
 * @property int $id
 * @property int|null $customer_id
 * @property int $user_id
 * @property int $warehouse_id
 * @property string $invoice_number
 * @property string $status
 * @property string $payment_status
 * @property numeric-string|float $subtotal
 * @property numeric-string|float $discount_amount
 * @property numeric-string|float $tax_amount
 * @property numeric-string|float $total_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer|null $customer
 * @property-read User $user
 * @property-read Collection<int, SaleItem> $items
 * @property-read Collection<int, Payment> $payments
 */
class Sale extends Model
{
    protected $fillable = [
        'customer_id', 'user_id', 'warehouse_id', 'invoice_number', 'status', 'payment_status',
        'subtotal', 'discount_amount', 'tax_amount', 'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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
