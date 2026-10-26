<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained();
            $t->foreignId('warehouse_id')->constrained();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('movement_type', 30); // opening, purchase, sale, adjustment, reversal
            $t->decimal('quantity', 15, 3);  // signed: + in, - out
            $t->nullableMorphs('reference');
            $t->string('note')->nullable();
            $t->timestamps();
            $t->index(['product_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
