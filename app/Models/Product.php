<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $category_id
 * @property int $unit_id
 * @property string $sku
 * @property string|null $barcode
 * @property string $name
 * @property numeric-string|float $cost_price
 * @property numeric-string|float $selling_price
 * @property numeric-string|float $reorder_level
 * @property bool $is_active
 * @property numeric-string|float|null $total_stock
 * @property string|null $stock_status
 * @property numeric-string|float|null $qty
 * @property-read Category|null $category
 * @property-read Unit $unit
 * @property-read Collection<int, StockBalance> $balances
 */
class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'unit_id', 'sku', 'barcode', 'name', 'cost_price', 'selling_price', 'reorder_level', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'reorder_level' => 'decimal:3',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
