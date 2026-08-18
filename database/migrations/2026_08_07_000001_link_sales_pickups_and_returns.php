<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->foreignId('pickup_transfer_id')
                ->nullable()
                ->after('transfer_type')
                ->constrained('stock_transfers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pickup_transfer_id');
        });
    }
};
