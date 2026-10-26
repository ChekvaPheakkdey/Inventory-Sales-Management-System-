<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductController extends CrudController
{
    protected string $model = Product::class;

    protected array $searchable = ['name', 'sku', 'barcode'];

    protected array $with = ['category:id,name', 'unit:id,name,symbol'];

    protected function rules(?int $id = null): array
    {
        return [
            'category_id' => 'nullable|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'sku' => 'required|string|max:100|unique:products,sku'.($id ? ",$id" : ''),
            'barcode' => 'nullable|string|max:100|unique:products,barcode'.($id ? ",$id" : ''),
            'name' => 'required|string|max:255',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }

    /** Feeds the "Product Inventory" table: name, sku, category, units, status. */
    public function index(Request $r): LengthAwarePaginator
    {
        $q = Product::query()->with($this->with)
            ->withSum('balances as total_stock', 'quantity')
            ->latest('id');

        if ($s = $r->query('search')) {
            $op = $this->likeOperator();
            $q->where(fn (Builder $w) => $w->where('name', $op, "%$s%")
                ->orWhere('sku', $op, "%$s%")
                ->orWhere('barcode', $s));
        }

        if ($c = $r->query('category_id')) {
            $q->where('category_id', $c);
        }

        $page = $q->paginate($r->integer('per_page', 15));

        $page->getCollection()->transform(function (Product $p) {
            $qty = (float) $p->total_stock;
            $p->total_stock = $qty;
            $p->stock_status = $qty <= 0
                ? 'out_of_stock'
                : ($qty <= (float) $p->reorder_level ? 'low_stock' : 'in_stock');

            return $p;
        });

        return $page;
    }
}
