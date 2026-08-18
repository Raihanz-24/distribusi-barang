<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->foreignId('daily_route_id')
                ->nullable()
                ->after('sales_id')
                ->unique()
                ->constrained('daily_routes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('daily_route_id');
        });
    }
};
