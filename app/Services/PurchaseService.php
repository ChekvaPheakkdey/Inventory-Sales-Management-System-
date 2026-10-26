<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

readonly class PurchaseService
{
    public function __construct(private StockService $stock) {}

    /** @throws Throwable */
    public function create(array $d, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($d, $user) {
            $total = 0;

            $po = PurchaseOrder::query()->create([
                'supplier_id' => $d['supplier_id'],
                'warehouse_id' => $d['warehouse_id'],
                'user_id' => $user->id,
                'order_number' => 'PO-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
                'status' => 'ordered',
            ]);

            foreach ($d['items'] as $i) {
                $sub = round($i['quantity'] * $i['unit_cost'], 2);
                $total += $sub;
                $po->items()->create([
                    'product_id' => $i['product_id'],
                    'quantity' => $i['quantity'],
                    'unit_cost' => $i['unit_cost'],
                    'subtotal' => $sub,
                ]);
            }

            $po->update(['total_amount' => $total]);

            return $po->load('items.product', 'supplier');
        });
    }

    /**
     * Stock increases only here, when goods are actually received.
     *
     * @throws Throwable
     */
    public function receive(PurchaseOrder $po, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $user) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);

            if ($po->status !== 'ordered') {
                throw ValidationException::withMessages(['status' => 'Only ordered POs can be received.']);
            }

            $po->load('items.product');

            foreach ($po->items as $item) {
                $this->stock->move(
                    $item->product_id,
                    $po->warehouse_id,
                    (float) $item->quantity,
                    'purchase',
                    $po,
                    $user->id
                );

                $item->product->update(['cost_price' => $item->unit_cost]); // latest cost
            }

            $po->update(['status' => 'received', 'received_at' => now()]);

            return $po;
        });
    }
}
