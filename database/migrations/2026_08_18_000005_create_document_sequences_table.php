<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->string('sequence_key', 50)->primary();
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();
        });

        $areaMaximum = DB::table('route_areas')->pluck('code')->reduce(
            fn (int $maximum, string $code): int => max(
                $maximum,
                preg_match('/^AREA-(\d+)$/', $code, $matches) ? (int) $matches[1] : 0,
            ),
            0,
        );

        DB::table('document_sequences')->insert([
            ['sequence_key' => 'route_area', 'current_value' => $areaMaximum, 'created_at' => now(), 'updated_at' => now()],
            ['sequence_key' => 'store', 'current_value' => (int) DB::table('stores')->max('id'), 'created_at' => now(), 'updated_at' => now()],
            ['sequence_key' => 'stock_transfer', 'current_value' => (int) DB::table('stock_transfers')->max('id'), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
