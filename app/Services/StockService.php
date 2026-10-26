<?php

namespace App\Services;

use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class StockService
{
    /**
     * Change stock. $qty is signed: positive adds, negative removes.
     * Must be called inside a DB transaction.
     */
    public function move(
        int $productId,
        int $warehouseId,
        float $qty,
        string $type,
        ?Model $reference = null,
        ?int $userId = null,
        ?string $note = null
    ): void {
        // ON CONFLICT DO NOTHING: safe under concurrency and does not abort the
        // PostgreSQL transaction the way a unique-violation exception would.
        StockBalance::query()->insertOrIgnore([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $balance = StockBalance::query()->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        $new = round((float) $balance->quantity + $qty, 3);

        if ($new < 0) {
            throw ValidationException::withMessages([
                'stock' => "Insufficient stock for product #$productId.",
            ]);
        }

        $balance->update(['quantity' => $new]);

        StockMovement::query()->create([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'user_id' => $userId,
            'movement_type' => $type,
            'quantity' => $qty,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'note' => $note,
        ]);
    }
}
