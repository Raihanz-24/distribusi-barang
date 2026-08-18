<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_visit_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sold_pieces')->default(0);
            $table->unsignedInteger('deposited_pieces')->default(0);
            $table->unsignedInteger('retail_price_per_piece');
            $table->unsignedBigInteger('price_per_renteng');
            $table->unsignedBigInteger('store_fee')->default(0);
            $table->unsignedBigInteger('amount_due')->default(0);
            $table->timestamps();
            $table->unique(['store_visit_id', 'product_id']);
        });

        Schema::create('daily_sales_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pickup_transfer_id')->unique()->constrained('stock_transfers')->restrictOnDelete();
            $table->foreignId('return_transfer_id')->unique()->constrained('stock_transfers')->restrictOnDelete();
            $table->foreignId('daily_route_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_id')->constrained('users')->restrictOnDelete();
            $table->date('settlement_date')->index();
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedInteger('total_stores')->default(0);
            $table->unsignedInteger('total_sold_pieces')->default(0);
            $table->unsignedInteger('total_deposited_pieces')->default(0);
            $table->unsignedBigInteger('total_collection')->default(0);
            $table->unsignedInteger('equivalent_renteng')->default(0);
            $table->unsignedBigInteger('sales_fee')->default(0);
            $table->timestamp('submitted_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['sales_id', 'settlement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales_settlements');
        Schema::dropIfExists('store_visit_items');
    }
};
