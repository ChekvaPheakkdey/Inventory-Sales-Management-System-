<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventorySalesFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_receipt_sale_payment_and_reports_keep_inventory_consistent(): void
    {
        Role::create(['name' => 'manager']);
        $manager = User::factory()->create();
        $manager->roles()->attach(Role::where('name', 'manager')->firstOrFail());

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $manager->email,
            'password' => 'password',
        ])->assertOk()->json('token');
        $this->withToken($token);

        $unitId = $this->postJson('/api/v1/units', ['name' => 'Piece', 'symbol' => 'pc'])
            ->assertCreated()->json('id');
        $warehouseId = $this->postJson('/api/v1/warehouses', ['name' => 'Main'])
            ->assertCreated()->json('id');
        $supplierId = $this->postJson('/api/v1/suppliers', ['name' => 'Supplier'])
            ->assertCreated()->json('id');
        $productId = $this->postJson('/api/v1/products', [
            'unit_id' => $unitId,
            'sku' => 'SKU-1',
            'name' => 'Sample product',
            'cost_price' => 4,
            'selling_price' => 8,
            'reorder_level' => 2,
        ])->assertCreated()->json('id');
        $this->patchJson("/api/v1/products/{$productId}", ['name' => 'Updated product'])
            ->assertOk()->assertJsonPath('name', 'Updated product');
        $this->deleteJson("/api/v1/units/{$unitId}")->assertConflict();

        $this->postJson('/api/v1/stock/adjust', [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => 10,
            'type' => 'opening',
        ])->assertCreated();

        $purchaseId = $this->postJson('/api/v1/purchase-orders', [
            'supplier_id' => $supplierId,
            'warehouse_id' => $warehouseId,
            'items' => [['product_id' => $productId, 'quantity' => 5, 'unit_cost' => 4]],
        ])->assertCreated()->json('id');

        $this->postJson("/api/v1/purchase-orders/{$purchaseId}/receive")
            ->assertOk()->assertJsonPath('status', 'received');

        $saleId = $this->postJson('/api/v1/sales', [
            'warehouse_id' => $warehouseId,
            'items' => [['product_id' => $productId, 'quantity' => 2]],
            'payment' => ['amount' => 16, 'method' => 'cash'],
        ])->assertCreated()
            ->assertJsonPath('total_amount', '16.00')
            ->assertJsonPath('payment_status', 'paid')
            ->json('id');

        $this->assertDatabaseHas('stock_balances', [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => 13,
        ]);

        $this->postJson('/api/v1/sales', [
            'warehouse_id' => $warehouseId,
            'items' => [['product_id' => $productId, 'quantity' => 100]],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('sales', 1);

        $this->getJson('/api/v1/reports/daily-sales')
            ->assertOk()
            ->assertJsonPath('0.invoices', 1)
            ->assertJsonPath('0.total', 16);
        $this->getJson('/api/v1/reports/inventory-valuation')
            ->assertOk()->assertJsonPath('cost_value', 52)->assertJsonPath('retail_value', 104);
        $this->getJson('/api/v1/reports/profit')
            ->assertOk()->assertJsonPath('gross_profit', 8)->assertJsonPath('net_profit', 8);
        $this->getJson('/api/v1/dashboard')
            ->assertOk()->assertJsonPath('stats.total_products', 1);

        $this->postJson("/api/v1/sales/{$saleId}/void")
            ->assertOk()->assertJsonPath('status', 'void');
        $this->assertDatabaseHas('stock_balances', [
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'quantity' => 15,
        ]);
    }
}
