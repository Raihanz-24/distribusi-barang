<?php

namespace App\Actions;

use App\Data\Coordinate;
use App\Enums\StockMovementType;
use App\Enums\VisitStatus;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreAccountEntry;
use App\Models\StoreVisit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StockLocationService;
use App\Services\StoreSettlementCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordStoreTransaction
{
    public function execute(User $sales, Store $store, Coordinate $position, array $quantities, ?float $accuracy = null, ?int $amountPaid = null, int $debtPayment = 0, ?string $locationNote = null): StoreVisit
    {
        if (! $sales->isSales()) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Sales yang dapat mencatat transaksi toko.']);
        }

        $rows = collect($quantities)->map(function (array $quantity, int|string $productId): array {
            return [
                'product_id' => (int) $productId,
                'sold' => max(0, (int) ($quantity['sold'] ?? 0)),
                'deposited' => max(0, (int) ($quantity['deposited'] ?? 0)),
                'withdrawn' => max(0, (int) ($quantity['withdrawn'] ?? 0)),
            ];
        })->filter(fn (array $row) => $row['sold'] > 0 || $row['deposited'] > 0 || $row['withdrawn'] > 0)->values();

        return DB::transaction(function () use ($sales, $store, $position, $rows, $accuracy, $amountPaid, $debtPayment, $locationNote): StoreVisit {
            $lockedStore = Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();
            $openingDebt = max(0, (int) StoreAccountEntry::query()
                ->where('store_id', $lockedStore->id)
                ->sum('amount'));

            if ($rows->isEmpty() && $debtPayment < 1) {
                throw ValidationException::withMessages([
                    'quantities' => 'Isi transaksi produk atau pembayaran hutang lama.',
                ]);
            }

            $preparedRows = $rows->map(function (array $row): array {
                $product = Product::query()
                    ->with('currentPrice')
                    ->where('is_active', true)
                    ->find($row['product_id']);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'product' => 'Produk tidak ditemukan atau sudah tidak aktif.',
                    ]);
                }

                return [...$row, 'product' => $product, 'calculation' => app(StoreSettlementCalculator::class)->calculate($product, $row['sold'])];
            });
            $totalDue = (int) $preparedRows->sum(fn (array $row) => $row['calculation']['amount_due']);
            $actualPaid = $amountPaid ?? $totalDue;

            if ($actualPaid < 0 || $actualPaid > $totalDue) {
                throw ValidationException::withMessages([
                    'amount_paid' => 'Pembayaran toko harus antara Rp 0 dan jumlah wajib bayar Rp '.number_format($totalDue, 0, ',', '.').'.',
                ]);
            }

            if ($debtPayment < 0 || $debtPayment > $openingDebt) {
                throw ValidationException::withMessages([
                    'debt_payment_amount' => 'Pembayaran hutang lama harus antara Rp 0 dan saldo hutang Rp '.number_format($openingDebt, 0, ',', '.').'.',
                ]);
            }

            $visit = app(RecordStoreVisit::class)->execute(
                $sales,
                $lockedStore,
                $position,
                VisitStatus::Visited,
                [
                    'location_accuracy' => $accuracy,
                    'exception_reason' => $locationNote,
                ],
            );
            $locations = app(StockLocationService::class);
            $salesLocation = $locations->forSales($sales);
            $storeLocation = $locations->forStore($lockedStore);

            foreach ($preparedRows as $row) {
                $product = $row['product'];
                $calculation = $row['calculation'];

                if ($row['sold'] > 0) {
                    app(MoveStock::class)->execute(
                        $product,
                        $storeLocation,
                        null,
                        $row['sold'],
                        StockMovementType::SaleFromStore,
                        $sales,
                        $visit,
                        "Terjual di {$lockedStore->name}",
                    );
                }

                if ($row['deposited'] > 0) {
                    app(MoveStock::class)->execute(
                        $product,
                        $salesLocation,
                        $storeLocation,
                        $row['deposited'],
                        StockMovementType::DepositToStore,
                        $sales,
                        $visit,
                        "Titipan ke {$lockedStore->name}",
                    );
                }

                if ($row['withdrawn'] > 0) {
                    app(MoveStock::class)->execute(
                        $product,
                        $storeLocation,
                        $salesLocation,
                        $row['withdrawn'],
                        StockMovementType::WithdrawalFromStore,
                        $sales,
                        $visit,
                        "Penarikan produk dari {$lockedStore->name}",
                    );
                }

                $visit->items()->create([
                    'product_id' => $product->id,
                    'sold_pieces' => $row['sold'],
                    'deposited_pieces' => $row['deposited'],
                    'withdrawn_pieces' => $row['withdrawn'],
                    'retail_price_per_piece' => $calculation['retail_price_per_piece'],
                    'price_per_renteng' => $calculation['price_per_renteng'],
                    'store_fee' => $calculation['store_fee'],
                    'amount_due' => $calculation['amount_due'],
                ]);
            }

            $debtAmount = $totalDue - $actualPaid;
            $totalPaid = $actualPaid + $debtPayment;
            $closingDebt = max(0, $openingDebt + $totalDue - $totalPaid);
            $visit->update([
                'amount_due' => $totalDue,
                'amount_paid' => $totalPaid,
                'debt_amount' => $debtAmount,
                'opening_debt' => $openingDebt,
                'current_payment_amount' => $actualPaid,
                'debt_payment_amount' => $debtPayment,
                'closing_debt' => $closingDebt,
            ]);

            if ($totalDue > 0) {
                StoreAccountEntry::create([
                    'store_id' => $lockedStore->id,
                    'store_visit_id' => $visit->id,
                    'sales_id' => $sales->id,
                    'entry_type' => 'charge',
                    'amount' => $totalDue,
                    'notes' => 'Tagihan transaksi kunjungan.',
                    'occurred_at' => now(),
                    'recorded_by' => $sales->id,
                ]);
            }

            if ($totalPaid > 0) {
                StoreAccountEntry::create([
                    'store_id' => $lockedStore->id,
                    'store_visit_id' => $visit->id,
                    'sales_id' => $sales->id,
                    'entry_type' => 'payment',
                    'amount' => -$totalPaid,
                    'notes' => 'Pembayaran transaksi Rp '.number_format($actualPaid, 0, ',', '.')
                        .' dan hutang lama Rp '.number_format($debtPayment, 0, ',', '.').'.',
                    'occurred_at' => now(),
                    'recorded_by' => $sales->id,
                ]);
            }

            app(AuditLogger::class)->record(
                'store.transaction_recorded',
                $visit,
                $sales,
                newValues: [
                    'store_id' => $lockedStore->id,
                    'amount_due' => $totalDue,
                    'current_payment' => $actualPaid,
                    'debt_payment' => $debtPayment,
                    'closing_debt' => $closingDebt,
                ],
                description: "Transaksi {$lockedStore->name} dicatat oleh Sales.",
            );

            return $visit->load(['store', 'items.product', 'accountEntries']);
        });
    }
}
