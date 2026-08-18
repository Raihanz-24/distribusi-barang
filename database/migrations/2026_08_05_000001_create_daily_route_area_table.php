<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_route_area', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('route_area_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('area_order')->default(1);
            $table->timestamps();
            $table->unique(['daily_route_id', 'route_area_id']);
            $table->index(['route_area_id', 'daily_route_id']);
        });

        DB::table('daily_routes')
            ->whereNotNull('route_area_id')
            ->orderBy('id')
            ->each(function (object $route): void {
                DB::table('daily_route_area')->insert([
                    'daily_route_id' => $route->id,
                    'route_area_id' => $route->route_area_id,
                    'area_order' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_route_area');
    }
};
