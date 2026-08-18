<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('owner_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address');
            $table->string('village')->nullable();
            $table->string('district')->nullable();
            $table->string('regency')->nullable();
            $table->string('province')->default('Nusa Tenggara Barat');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('location_accuracy', 10, 2)->nullable();
            $table->string('map_reference')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->string('verification_status', 30)->default('pending')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('store_location_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->decimal('old_latitude', 10, 7)->nullable();
            $table->decimal('old_longitude', 10, 7)->nullable();
            $table->decimal('new_latitude', 10, 7);
            $table->decimal('new_longitude', 10, 7);
            $table->decimal('location_accuracy', 10, 2)->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('route_areas', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->decimal('default_start_latitude', 10, 7)->nullable();
            $table->decimal('default_start_longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('route_area_store', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('route_area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('priority')->nullable();
            $table->unsignedTinyInteger('preferred_visit_day')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['route_area_id', 'store_id']);
        });

        Schema::create('daily_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('route_area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_id')->constrained('users');
            $table->date('route_date')->index();
            $table->string('status', 30)->default('draft')->index();
            $table->decimal('start_latitude', 10, 7)->nullable();
            $table->decimal('start_longitude', 10, 7)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('optimization_status', 30)->default('pending');
            $table->string('optimization_provider')->nullable();
            $table->unsignedBigInteger('optimized_distance_meters')->nullable();
            $table->unsignedBigInteger('optimized_duration_seconds')->nullable();
            $table->json('route_geometry')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->unique(['sales_id', 'route_date']);
            $table->index(['route_area_id', 'route_date']);
        });

        Schema::create('daily_route_stores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained();
            $table->unsignedInteger('planned_order')->nullable();
            $table->unsignedInteger('optimized_order')->nullable();
            $table->unsignedInteger('actual_order')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->string('visit_status', 30)->default('pending')->index();
            $table->text('scheduled_reason')->nullable();
            $table->foreignId('rescheduled_from_id')->nullable()->constrained('daily_route_stores')->nullOnDelete();
            $table->timestamps();
            $table->unique(['daily_route_id', 'store_id']);
        });

        Schema::create('route_optimization_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_route_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->json('start_coordinate');
            $table->json('input_store_ids');
            $table->json('result_order')->nullable();
            $table->unsignedBigInteger('distance_meters')->nullable();
            $table->unsignedBigInteger('duration_seconds')->nullable();
            $table->json('geometry')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('status', 30)->index();
            $table->text('error_message')->nullable();
            $table->foreignId('executed_by')->constrained('users');
            $table->timestamp('executed_at');
            $table->timestamps();
        });

        Schema::create('store_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_route_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('daily_route_store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('store_id')->constrained();
            $table->foreignId('sales_id')->constrained('users');
            $table->unsignedInteger('visit_number');
            $table->string('visit_status', 30)->index();
            $table->timestamp('arrived_at');
            $table->timestamp('completed_at')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('location_accuracy', 10, 2)->nullable();
            $table->unsignedInteger('distance_to_store_meters')->nullable();
            $table->text('visit_notes')->nullable();
            $table->string('exception_reason')->nullable();
            $table->string('proof_photo_path')->nullable();
            $table->timestamps();
            $table->unique(['sales_id', 'visit_number']);
            $table->index(['store_id', 'arrived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_visits');
        Schema::dropIfExists('route_optimization_runs');
        Schema::dropIfExists('daily_route_stores');
        Schema::dropIfExists('daily_routes');
        Schema::dropIfExists('route_area_store');
        Schema::dropIfExists('route_areas');
        Schema::dropIfExists('store_location_histories');
        Schema::dropIfExists('stores');
    }
};
