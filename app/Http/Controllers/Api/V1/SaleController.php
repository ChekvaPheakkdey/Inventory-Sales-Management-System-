<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Throwable;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $svc) {}

    public function index(Request $r): LengthAwarePaginator
    {
        return Sale::query()->with('customer:id,name')
            ->when($r->query('status'), fn (Builder $q, $s) => $q->where('status', $s))
            ->when($r->query('from'), fn (Builder $q, $f) => $q->whereDate('created_at', '>=', $f))
            ->when($r->query('to'), fn (Builder $q, $t) => $q->whereDate('created_at', '<=', $t))
            ->latest('id')
            ->paginate($r->integer('per_page', 15));
    }

    /** @throws Throwable */
    public function store(Request $r): JsonResponse
    {
        $d = $r->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'payment' => 'nullable|array',
            'payment.amount' => 'required_with:payment|numeric|gt:0',
            'payment.method' => 'required_with:payment|string|max:30',
        ]);

        return response()->json($this->svc->create($d, $r->user()), 201);
    }

    public function show(int $id): Sale
    {
        return Sale::query()->with('items.product', 'payments', 'customer', 'user:id,name')->findOrFail($id);
    }

    /** @throws Throwable */
    public function addPayment(Request $r, int $id): JsonResponse
    {
        $p = $r->validate([
            'amount' => 'required|numeric|gt:0',
            'method' => 'required|string|max:30',
        ]);

        return response()->json($this->svc->addPayment(Sale::query()->findOrFail($id), $p), 201);
    }

    /** @throws Throwable */
    public function void(Request $r, int $id): Sale
    {
        return $this->svc->void(Sale::query()->findOrFail($id), $r->user());
    }
}
