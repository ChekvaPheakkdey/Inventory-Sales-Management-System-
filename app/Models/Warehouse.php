<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $location
 * @property bool $is_active
 * @property-read Collection<int, StockBalance> $balances
 * @property-read Collection<int, StockMovement> $stockMovements
 * @property-read Collection<int, Sale> $sales
 * @property-read Collection<int, PurchaseOrder> $purchaseOrders
 */
class Warehouse extends Model
{
    protected $fillable = ['name', 'location', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
