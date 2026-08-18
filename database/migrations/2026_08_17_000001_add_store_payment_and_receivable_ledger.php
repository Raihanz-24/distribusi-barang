<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_visits', function (Blueprint $table): void {
            $table->unsignedBigInteger('amount_due')->default(0)->after('visit_status');
            $table->unsignedBigInteger('amount_paid')->default(0)->after('amount_due');
            $table->unsignedBigInteger('debt_amount')->default(0)->after('amount_paid');
        });

        Schema::create('store_account_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entry_type', 30)->index();
            $table->bigInteger('amount');
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['store_id', 'occurred_at']);
        });

        DB::table('store_visits')->orderBy('id')->chunkById(200, function ($visits): void {
            foreach ($visits as $visit) {
                $amountDue = (int) DB::table('store_visit_items')
                    ->where('store_visit_id', $visit->id)
                    ->sum('amount_due');

                DB::table('store_visits')->where('id', $visit->id)->update([
                    'amount_due' => $amountDue,
                    'amount_paid' => $amountDue,
                    'debt_amount' => 0,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_account_entries');

        Schema::table('store_visits', function (Blueprint $table): void {
            $table->dropColumn(['amount_due', 'amount_paid', 'debt_amount']);
        });
    }
};
