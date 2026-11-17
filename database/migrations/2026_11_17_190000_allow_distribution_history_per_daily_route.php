<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A completed pickup remains linked to its route for audit/settlement
     * history. The original unique index prevented a new distribution from
     * being created after that pickup had been returned and closed.
     */
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->dropUnique('stock_transfers_daily_route_id_unique');
            $table->index('daily_route_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->dropIndex('stock_transfers_daily_route_id_index');
            $table->unique('daily_route_id');
        });
    }
};
