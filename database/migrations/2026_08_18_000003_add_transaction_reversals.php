<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_visits', function (Blueprint $table): void {
            $table->timestamp('reversed_at')->nullable()->after('completed_at')->index();
            $table->foreignId('reversed_by')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable()->after('reversed_by');
        });

        Schema::table('store_account_entries', function (Blueprint $table): void {
            $table->foreignId('reversal_of_id')->nullable()->after('id')->constrained('store_account_entries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('store_account_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reversal_of_id');
        });
        Schema::table('store_visits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropColumn(['reversed_at', 'reversal_reason']);
        });
    }
};
