<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_prices', function (Blueprint $table): void {
            $table->dropColumn('price_per_piece');
        });
    }

    public function down(): void
    {
        Schema::table('product_prices', function (Blueprint $table): void {
            $table->unsignedBigInteger('price_per_piece')->nullable()->after('product_id');
        });
    }
};
