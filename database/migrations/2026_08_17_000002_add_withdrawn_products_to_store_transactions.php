<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_visit_items', function (Blueprint $table): void {
            $table->unsignedInteger('withdrawn_pieces')->default(0)->after('deposited_pieces');
        });

        Schema::table('daily_sales_settlements', function (Blueprint $table): void {
            $table->unsignedInteger('total_withdrawn_pieces')->default(0)->after('total_deposited_pieces');
        });
    }

    public function down(): void
    {
        Schema::table('daily_sales_settlements', function (Blueprint $table): void {
            $table->dropColumn('total_withdrawn_pieces');
        });

        Schema::table('store_visit_items', function (Blueprint $table): void {
            $table->dropColumn('withdrawn_pieces');
        });
    }
};
