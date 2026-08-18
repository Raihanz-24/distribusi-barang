<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('stock_locations')
            ->where('type', 'warehouse')
            ->update(['type' => 'main_warehouse', 'name' => 'Gudang Utama']);

        DB::table('stock_locations')
            ->where('type', 'sales')
            ->update(['name' => DB::raw("REPLACE(name, 'Stok Sales', 'Stok Bawaan Sales')")]);

        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->string('transfer_type', 40)->default('issue_to_sales')->after('number')->index();
            $table->foreignId('sales_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('stock_locations')
            ->where('type', 'main_warehouse')
            ->update(['type' => 'warehouse', 'name' => 'Gudang Utama']);

        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->dropIndex(['transfer_type']);
            $table->dropColumn('transfer_type');
            $table->foreignId('sales_id')->nullable(false)->change();
        });
    }
};
