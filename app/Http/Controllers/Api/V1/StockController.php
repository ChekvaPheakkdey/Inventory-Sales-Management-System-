<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Throwable;

class StockController extends Controller
{
    public function balances(Request $r): LengthAwarePaginator
    {
        return StockBalance::query()->with('product:id,sku,name', 'warehouse:id,name')
            ->when($r->query('warehouse_id'), fn (Builder $q, $w) => $q->where('warehouse_id', $w))
            ->when($r->query('product_id'), fn (Builder $q, $p) => $q->where('product_id', $p))
            ->paginate($r->integer('per_page', 15));
    }

    public function movements(Request $r): LengthAwarePaginator
    {
        return StockMovement::query()->with('product:id,sku,name', 'warehouse:id,name')
            ->when($r->query('product_id'), fn (Builder $q, $p) => $q->where('product_id', $p))
            ->when($r->query('warehouse_id'), fn (Builder $q, $w) => $q->where('warehouse_id', $w))
            ->latest('id')
            ->paginate($r->integer('per_page', 15));
    }

    /** Opening stock or manual correction. Quantity is signed (+ in, - out). */
    /** @throws Throwable */
    public function adjust(Request $r, StockService $stock): JsonResponse
    {
        $d = $r->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|not_in:0',
            'type' => 'required|in:opening,adjustment',
            'note' => 'nullable|string|max:255',
        ]);

        DB::transaction(fn () => $stock->move(
            (int) $d['product_id'],
            (int) $d['warehouse_id'],
            (float) $d['quantity'],
            $d['type'],
            null,
            $r->user()->id,
            $d['note'] ?? null
        ));

        return response()->json(['message' => 'Stock updated'], 201);
    }
}
