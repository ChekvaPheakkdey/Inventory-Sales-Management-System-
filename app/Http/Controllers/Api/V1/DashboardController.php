<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $r): array
    {
        $year = $r->integer('year', (int) now()->year);

        // Stock cards + donut
        $rows = Product::query()
            ->leftJoin('stock_balances', 'products.id', '=', 'stock_balances.product_id')
            ->where('products.is_active', true)
            ->whereNull('products.deleted_at')
            ->groupBy('products.id', 'products.reorder_level')
            ->selectRaw('products.reorder_level, COALESCE(SUM(stock_balances.quantity), 0) AS qty')
            ->get();

        $out = $rows->filter(fn (Product $p) => (float) $p->qty <= 0)->count();
        $low = $rows->filter(fn (Product $p) => (float) $p->qty > 0 && (float) $p->qty <= (float) $p->reorder_level)->count();
        $in = $rows->count() - $out - $low;

        // Cash flow chart, using the month expression supported by the active database.
        $monthSale = $this->monthExpression('created_at');
        $monthExp = $this->monthExpression('expense_date');

        $income = Sale::query()->where('status', 'completed')
            ->whereYear('created_at', $year)
            ->selectRaw("$monthSale AS m, SUM(total_amount) AS t")
            ->groupByRaw($monthSale)
            ->pluck('t', 'm');

        $expense = Expense::whereYear('expense_date', $year)
            ->selectRaw("$monthExp AS m, SUM(amount) AS t")
            ->groupByRaw($monthExp)
            ->pluck('t', 'm');

        $cashFlow = collect(range(1, 12))->map(fn (int $m) => [
            'month' => $m,
            'income' => (float) ($income[$m] ?? 0),
            'expenses' => (float) ($expense[$m] ?? 0),
        ]);

        return [
            'stats' => [
                'total_products' => $rows->count(),
                'in_stock' => $in,
                'low_stock' => $low,
                'out_of_stock' => $out,
            ],
            'cash_flow' => $cashFlow,
            'inventory_alerts' => [
                'in_stock' => $in,
                'low_stock' => $low,
                'out_of_stock' => $out,
            ],
        ];
    }

    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%m', $column) AS INTEGER)",
            'mysql', 'mariadb' => "MONTH($column)",
            default => "CAST(EXTRACT(MONTH FROM $column) AS INTEGER)",
        };
    }
}
