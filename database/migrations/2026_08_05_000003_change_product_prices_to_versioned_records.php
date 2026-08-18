<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_prices', function (Blueprint $table): void {
            $table->dropUnique('product_prices_product_start_unique');
            $table->index(['product_id', 'created_at'], 'product_prices_version_index');
        });
    }

    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table): void {
            $table->dropIndex('product_prices_version_index');
            $table->unique(['product_id', 'effective_from'], 'product_prices_product_start_unique');
        });
    }
};
