<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('warehouse_id')->constrained();
            $t->string('invoice_number')->unique();
            $t->string('status', 20)->default('completed');      // completed, void
            $t->string('payment_status', 20)->default('unpaid'); // unpaid, partial, paid
            $t->decimal('subtotal', 15, 2);
            $t->decimal('discount_amount', 15, 2)->default(0);
            $t->decimal('tax_amount', 15, 2)->default(0);
            $t->decimal('total_amount', 15, 2);
            $t->timestamps();
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
