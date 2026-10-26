<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /** Products at or below reorder level (includes out of stock). */
    public function lowStock(): Collection
    {
        return Product::query()->with('unit:id,symbol')
            ->withSum('balances as total_stock', 'quantity')
            ->get()
            ->filter(fn (Product $p) => (float) $p->total_stock <= (float) $p->reorder_level)
            ->values();
    }

    /** Daily sales totals. ?from=YYYY-MM-DD&to=YYYY-MM-DD */
    public function dailySales(Request $r): Collection
    {
        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);

        $day = match (DB::connection()->getDriverName()) {
            'sqlite' => 'date(created_at)',
            'mysql', 'mariadb' => 'DATE(created_at)',
            default => 'CAST(created_at AS DATE)',
        };

        return Sale::query()->where('status', 'completed')
            ->when($r->query('from'), fn (Builder $q, $f) => $q->whereDate('created_at', '>=', $f))
            ->when($r->query('to'), fn (Builder $q, $t) => $q->whereDate('created_at', '<=', $t))
            ->selectRaw("$day AS day, COUNT(*) AS invoices, SUM(total_amount) AS total")
            ->groupByRaw($day)
            ->orderByRaw($day)
            ->get();
    }

    /** Current stock value at cost and at selling price. */
    public function inventoryValuation(): array
    {
        $row = DB::table('stock_balances')
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->whereNull('products.deleted_at')
            ->selectRaw('COALESCE(SUM(stock_balances.quantity * products.cost_price), 0) AS cost_value,
                         COALESCE(SUM(stock_balances.quantity * products.selling_price), 0) AS retail_value')
            ->first();
        $row = $row ? get_object_vars($row) : [];

        return [
            'cost_value' => (float) $row['cost_value'],
            'retail_value' => (float) $row['retail_value'],
        ];
    }

    /** Revenue - COGS (from sale item snapshots) - expenses. */
    public function profit(Request $r): array
    {
        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);

        $itemTotals = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->when($r->query('from'), fn (Builder $q, $f) => $q->whereDate('sales.created_at', '>=', $f))
            ->when($r->query('to'), fn (Builder $q, $t) => $q->whereDate('sales.created_at', '<=', $t))
            ->selectRaw('COALESCE(SUM(sale_items.subtotal), 0) AS gross_sales,
                         COALESCE(SUM(sale_items.quantity * sale_items.unit_cost), 0) AS cogs')
            ->first();
        $items = $itemTotals?->getAttributes() ?? [];

        $salesTotals = Sale::query()->where('status', 'completed')
            ->when($r->query('from'), fn (Builder $q, $f) => $q->whereDate('created_at', '>=', $f))
            ->when($r->query('to'), fn (Builder $q, $t) => $q->whereDate('created_at', '<=', $t))
            ->selectRaw('COALESCE(SUM(discount_amount), 0) AS discounts, COALESCE(SUM(tax_amount), 0) AS tax')
            ->first();
        $sales = $salesTotals?->getAttributes() ?? [];

        $expenses = (float) Expense::query()
            ->when($r->query('from'), fn (Builder $q, $f) => $q->whereDate('expense_date', '>=', $f))
            ->when($r->query('to'), fn (Builder $q, $t) => $q->whereDate('expense_date', '<=', $t))
            ->sum('amount');

        $revenue = (float) ($items['gross_sales'] ?? 0) - (float) ($sales['discounts'] ?? 0);
        $gross = $revenue - (float) ($items['cogs'] ?? 0);

        return [
            'gross_sales' => (float) ($items['gross_sales'] ?? 0),
            'discounts' => (float) ($sales['discounts'] ?? 0),
            'revenue' => $revenue,
            'cogs' => (float) ($items['cogs'] ?? 0),
            'gross_profit' => $gross,
            'expenses' => $expenses,
            'net_profit' => $gross - $expenses,
        ];
    }
}
