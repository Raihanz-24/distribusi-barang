<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->string('province_code', 2)->nullable()->after('address')->index();
            $table->string('city_code', 4)->nullable()->after('province_code')->index();
            $table->string('district_code', 7)->nullable()->after('city_code')->index();
            $table->string('village_code', 10)->nullable()->after('district_code')->index();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table): void {
            $table->dropColumn(['province_code', 'city_code', 'district_code', 'village_code']);
        });
    }
};
