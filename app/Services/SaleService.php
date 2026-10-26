<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

readonly class SaleService
{
    public function __construct(private StockService $stock) {}

    /** @throws Throwable */
    public function create(array $d, User $user): Sale
    {
        return DB::transaction(function () use ($d, $user) {
            $products = Product::query()->whereIn('id', collect($d['items'])->pluck('product_id'))
                ->get()
                ->keyBy('id');

            $subtotal = 0;
            $lines = [];

            foreach ($d['items'] as $i) {
                $p = $products[$i['product_id']];
                $price = $i['unit_price'] ?? $p->selling_price;
                $line = round($i['quantity'] * $price, 2);
                $subtotal += $line;

                $lines[] = [
                    'product_id' => $p->id,
                    'quantity' => $i['quantity'],
                    'unit_price' => $price,       // snapshot
                    'unit_cost' => $p->cost_price, // snapshot
                    'subtotal' => $line,
                ];
            }

            $discount = $d['discount_amount'] ?? 0;
            $tax = $d['tax_amount'] ?? 0;
            $total = round($subtotal - $discount + $tax, 2);

            if ($discount > $subtotal) {
                throw ValidationException::withMessages(['discount_amount' => 'Discount exceeds subtotal.']);
            }

            $sale = Sale::query()->create([
                'customer_id' => $d['customer_id'] ?? null,
                'user_id' => $user->id,
                'warehouse_id' => $d['warehouse_id'],
                'invoice_number' => 'INV-'.now()->format('ymd').'-'.strtoupper(Str::random(6)),
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'payment_status' => $total <= 0 ? 'paid' : 'unpaid',
            ]);

            foreach ($lines as $line) {
                $sale->items()->create($line);

                $this->stock->move(
                    $line['product_id'],
                    (int) $d['warehouse_id'],
                    -1 * (float) $line['quantity'],
                    'sale',
                    $sale,
                    $user->id
                );
            }

            if (! empty($d['payment'])) {
                $this->addPayment($sale, $d['payment']);
            }

            return $sale->refresh()->load('items.product', 'payments', 'customer');
        });
    }

    /** @throws Throwable */
    public function addPayment(Sale $sale, array $p): Payment
    {
        return DB::transaction(function () use ($sale, $p) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            $paid = (float) $sale->payments()->where('status', 'completed')->sum('amount');

            if ($sale->status === 'void' || $paid + $p['amount'] > (float) $sale->total_amount + 0.001) {
                throw ValidationException::withMessages(['amount' => 'Invalid payment amount.']);
            }

            $payment = $sale->payments()->create([
                'amount' => $p['amount'],
                'method' => $p['method'],
                'paid_at' => now(),
            ]);

            $paid += (float) $p['amount'];
            $sale->update([
                'payment_status' => $paid + 0.001 >= (float) $sale->total_amount ? 'paid' : 'partial',
            ]);

            return $payment;
        });
    }

    /** @throws Throwable */
    public function void(Sale $sale, User $user): Sale
    {
        return DB::transaction(function () use ($sale, $user) {
            $sale = Sale::query()->lockForUpdate()->with('items')->findOrFail($sale->id);

            if ($sale->status === 'void') {
                throw ValidationException::withMessages(['sale' => 'Sale is already void.']);
            }

            // Reversal movements: history is never deleted.
            foreach ($sale->items as $item) {
                $this->stock->move(
                    $item->product_id,
                    $sale->warehouse_id,
                    (float) $item->quantity,
                    'reversal',
                    $sale,
                    $user->id,
                    'Void '.$sale->invoice_number
                );
            }

            $sale->update(['status' => 'void']);

            return $sale;
        });
    }
}
