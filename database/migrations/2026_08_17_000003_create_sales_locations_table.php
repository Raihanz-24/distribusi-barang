<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_meters', 10, 2)->nullable();
            $table->decimal('altitude_meters', 10, 2)->nullable();
            $table->decimal('heading_degrees', 6, 2)->nullable();
            $table->decimal('speed_mps', 8, 2)->nullable();
            $table->string('device_info', 500)->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_locations');
    }
};
