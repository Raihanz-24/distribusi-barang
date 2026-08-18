<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('quantity_entered')->nullable()->after('product_id');
            $table->string('quantity_unit', 20)->default('piece')->after('quantity_entered');
        });

        DB::table('stock_transfer_items')->update([
            'quantity_entered' => DB::raw('quantity_issued'),
            'quantity_unit' => 'piece',
        ]);
    }

    public function down(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table): void {
            $table->dropColumn(['quantity_entered', 'quantity_unit']);
        });
    }
};
