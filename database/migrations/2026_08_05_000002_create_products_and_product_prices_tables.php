<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('unit_name', 30)->default('bungkus');
            $table->unsignedInteger('pieces_per_renteng')->default(12);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('product_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('price_per_piece');
            $table->unsignedBigInteger('price_per_renteng');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'is_active', 'effective_from'], 'product_prices_effective_index');
            $table->unique(['product_id', 'effective_from'], 'product_prices_product_start_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('products');
    }
};
