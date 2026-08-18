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
            $table->unsignedBigInteger('opening_debt')->default(0)->after('debt_amount');
            $table->unsignedBigInteger('current_payment_amount')->default(0)->after('opening_debt');
            $table->unsignedBigInteger('debt_payment_amount')->default(0)->after('current_payment_amount');
            $table->unsignedBigInteger('closing_debt')->default(0)->after('debt_payment_amount');
        });

        DB::table('stores')->select('id')->orderBy('id')->each(function (object $store): void {
            $balance = 0;

            DB::table('store_visits')
                ->where('store_id', $store->id)
                ->orderBy('arrived_at')
                ->orderBy('id')
                ->get()
                ->each(function (object $visit) use (&$balance): void {
                    $openingDebt = max(0, $balance);
                    $currentPayment = min((int) $visit->amount_paid, (int) $visit->amount_due);
                    $closingDebt = max(0, $openingDebt + (int) $visit->amount_due - (int) $visit->amount_paid);

                    DB::table('store_visits')->where('id', $visit->id)->update([
                        'opening_debt' => $openingDebt,
                        'current_payment_amount' => $currentPayment,
                        'debt_payment_amount' => max(0, (int) $visit->amount_paid - $currentPayment),
                        'closing_debt' => $closingDebt,
                    ]);

                    $balance = $closingDebt;
                });
        });
    }

    public function down(): void
    {
        Schema::table('store_visits', function (Blueprint $table): void {
            $table->dropColumn([
                'opening_debt',
                'current_payment_amount',
                'debt_payment_amount',
                'closing_debt',
            ]);
        });
    }
};
