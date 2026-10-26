<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('unit_id')->constrained();
            $t->string('sku')->unique();
            $t->string('barcode')->nullable()->unique();
            $t->string('name');
            $t->decimal('cost_price', 15, 2)->default(0);
            $t->decimal('selling_price', 15, 2)->default(0);
            $t->decimal('reorder_level', 15, 3)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
