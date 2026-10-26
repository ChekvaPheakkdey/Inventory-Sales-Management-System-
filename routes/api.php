<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WarehouseController;
use Illuminate\Support\Facades\Route;

// Laravel adds the /api prefix automatically  ->  /api/v1/...
Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('api.token')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('dashboard', [DashboardController::class, 'index']);

        // Read access for every logged-in user
        Route::apiResource('products', ProductController::class)->only(['index', 'show']);
        Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
        Route::apiResource('units', UnitController::class)->only(['index', 'show']);
        Route::apiResource('warehouses', WarehouseController::class)->only(['index', 'show']);
        Route::apiResource('customers', CustomerController::class)->only(['index', 'show']);

        // Catalog, stock and purchasing
        Route::middleware('role:manager,stock_keeper')->group(function () {
            Route::apiResource('products', ProductController::class)->except(['index', 'show']);
            Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
            Route::apiResource('units', UnitController::class)->except(['index', 'show']);
            Route::apiResource('warehouses', WarehouseController::class)->except(['index', 'show']);
            Route::apiResource('suppliers', SupplierController::class);

            Route::get('stock/balances', [StockController::class, 'balances']);
            Route::get('stock/movements', [StockController::class, 'movements']);
            Route::post('stock/adjust', [StockController::class, 'adjust']);

            Route::get('purchase-orders', [PurchaseOrderController::class, 'index']);
            Route::post('purchase-orders', [PurchaseOrderController::class, 'store']);
            Route::get('purchase-orders/{id}', [PurchaseOrderController::class, 'show']);
            Route::post('purchase-orders/{id}/receive', [PurchaseOrderController::class, 'receive']);
            Route::post('purchase-orders/{id}/cancel', [PurchaseOrderController::class, 'cancel']);

            Route::get('reports/low-stock', [ReportController::class, 'lowStock']);
        });

        // Selling
        Route::middleware('role:manager,cashier')->group(function () {
            Route::apiResource('customers', CustomerController::class)->except(['index', 'show']);

            Route::get('sales', [SaleController::class, 'index']);
            Route::post('sales', [SaleController::class, 'store']);
            Route::get('sales/{id}', [SaleController::class, 'show']);
            Route::post('sales/{id}/payments', [SaleController::class, 'addPayment']);
        });

        // Manager (admin always passes)
        Route::middleware('role:manager')->group(function () {
            Route::post('sales/{id}/void', [SaleController::class, 'void']);
            Route::apiResource('expenses', ExpenseController::class);

            Route::get('reports/daily-sales', [ReportController::class, 'dailySales']);
            Route::get('reports/inventory-valuation', [ReportController::class, 'inventoryValuation']);
            Route::get('reports/profit', [ReportController::class, 'profit']);
        });

        Route::middleware('role:admin')->group(function () {
            Route::apiResource('users', UserController::class)
                ->only(['index', 'store', 'show', 'update']);
        });
    });
});
