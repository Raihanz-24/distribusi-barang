<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_sales_settlements', function (Blueprint $table): void {
            $table->unsignedBigInteger('actual_cash_received')->nullable()->after('total_collection');
            $table->bigInteger('cash_difference')->default(0)->after('actual_cash_received');
            $table->unsignedInteger('stock_shortage_pieces')->default(0)->after('total_withdrawn_pieces');
            $table->text('verification_notes')->nullable()->after('notes');
        });

        DB::table('daily_sales_settlements')
            ->where('status', 'approved')
            ->update([
                'actual_cash_received' => DB::raw('total_collection'),
                'cash_difference' => 0,
            ]);
    }

    public function down(): void
    {
        Schema::table('daily_sales_settlements', function (Blueprint $table): void {
            $table->dropColumn([
                'actual_cash_received',
                'cash_difference',
                'stock_shortage_pieces',
                'verification_notes',
            ]);
        });
    }
};
