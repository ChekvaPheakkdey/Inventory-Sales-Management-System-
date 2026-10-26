<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('warehouse_id')->constrained();
            $t->string('order_number')->unique();
            $t->string('status', 20)->default('ordered'); // ordered, received, cancelled
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->timestamp('received_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
