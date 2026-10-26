<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\PurchaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseService $svc) {}

    public function index(Request $r): LengthAwarePaginator
    {
        return PurchaseOrder::query()->with('supplier:id,name')
            ->when($r->query('status'), fn (Builder $q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate($r->integer('per_page', 15));
    }

    /** @throws Throwable */
    public function store(Request $r): JsonResponse
    {
        $d = $r->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ]);

        return response()->json($this->svc->create($d, $r->user()), 201);
    }

    public function show(int $id): PurchaseOrder
    {
        return PurchaseOrder::query()->with('items.product', 'supplier', 'user:id,name')->findOrFail($id);
    }

    /** @throws Throwable */
    public function receive(Request $r, int $id): PurchaseOrder
    {
        return $this->svc->receive(PurchaseOrder::query()->findOrFail($id), $r->user());
    }

    /** @throws Throwable */
    public function cancel(int $id): PurchaseOrder
    {
        return DB::transaction(function () use ($id) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($id);

            if ($po->status !== 'ordered') {
                throw ValidationException::withMessages(['status' => 'Only ordered POs can be cancelled.']);
            }

            $po->update(['status' => 'cancelled']);

            return $po;
        });
    }
}
