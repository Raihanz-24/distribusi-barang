<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 30)->index();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['type', 'reference_id'], 'stock_locations_type_reference_unique');
        });

        Schema::create('stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('quantity_pieces')->default(0);
            $table->timestamps();
            $table->unique(['stock_location_id', 'product_id']);
        });

        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('source_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('destination_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('sales_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->text('discrepancy_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->index(['sales_id', 'status']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('quantity_issued');
            $table->unsignedBigInteger('quantity_received')->nullable();
            $table->bigInteger('discrepancy_quantity')->default(0);
            $table->timestamps();
            $table->unique(['stock_transfer_id', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('destination_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
            $table->unsignedBigInteger('quantity_pieces');
            $table->string('movement_type', 40)->index();
            $table->nullableMorphs('reference');
            $table->timestamp('occurred_at')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->unsignedBigInteger('source_balance_after')->nullable();
            $table->unsignedBigInteger('destination_balance_after')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_balances');
        Schema::dropIfExists('stock_locations');
    }
};
