<?php

namespace App\Actions;

use App\Enums\StockMovementType;
use App\Enums\VisitStatus;
use App\Models\DailySalesSettlement;
use App\Models\StoreAccountEntry;
use App\Models\StoreVisit;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReverseStoreTransaction
{
    public function execute(StoreVisit $visit, User $admin, string $reason): StoreVisit
    {
        if (! $admin->isAdmin()) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Admin yang dapat membatalkan transaksi.']);
        }
        if (blank(trim($reason))) {
            throw ValidationException::withMessages(['reason' => 'Alasan pembatalan wajib diisi.']);
        }

        return DB::transaction(function () use ($visit, $admin, $reason): StoreVisit {
            $locked = StoreVisit::query()
                ->with(['stockMovements.product', 'stockMovements.sourceLocation', 'stockMovements.destinationLocation', 'accountEntries', 'dailyRouteStore'])
                ->whereKey($visit->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->reversed_at) {
                throw ValidationException::withMessages(['visit' => 'Transaksi ini sudah pernah dibatalkan.']);
            }

            if ($locked->daily_route_id && DailySalesSettlement::query()->where('daily_route_id', $locked->daily_route_id)->exists()) {
                throw ValidationException::withMessages([
                    'visit' => 'Transaksi tidak dapat dibatalkan setelah closing diajukan. Batalkan atau koreksi closing terlebih dahulu.',
                ]);
            }

            $latestTransactionId = StoreVisit::query()
                ->where('store_id', $locked->store_id)
                ->whereNull('reversed_at')
                ->where(fn ($query) => $query->whereHas('items')->orWhere('amount_paid', '>', 0))
                ->latest('completed_at')
                ->value('id');
            if ((int) $latestTransactionId !== (int) $locked->id) {
                throw ValidationException::withMessages([
                    'visit' => 'Hanya transaksi aktif terakhir toko yang dapat dibatalkan agar saldo hutang tetap runtut.',
                ]);
            }

            foreach ($locked->stockMovements->sortByDesc('id') as $movement) {
                if ($movement->reversal_of_id) {
                    continue;
                }

                $reversal = app(MoveStock::class)->execute(
                    $movement->product,
                    $movement->destinationLocation,
                    $movement->sourceLocation,
                    $movement->quantity_pieces,
                    StockMovementType::Reversal,
                    $admin,
                    $movement,
                    'Pembatalan transaksi kunjungan: '.trim($reason),
                );
                $reversal->update(['reversal_of_id' => $movement->id]);
            }

            foreach ($locked->accountEntries->whereNull('reversal_of_id') as $entry) {
                StoreAccountEntry::query()->create([
                    'reversal_of_id' => $entry->id,
                    'store_id' => $entry->store_id,
                    'store_visit_id' => $locked->id,
                    'sales_id' => $locked->sales_id,
                    'entry_type' => 'reversal',
                    'amount' => -$entry->amount,
                    'notes' => 'Pembatalan: '.trim($reason),
                    'occurred_at' => now(),
                    'recorded_by' => $admin->id,
                ]);
            }

            $locked->update([
                'reversed_at' => now(),
                'reversed_by' => $admin->id,
                'reversal_reason' => trim($reason),
            ]);
            $locked->dailyRouteStore?->update([
                'visit_status' => VisitStatus::Pending,
                'actual_order' => null,
            ]);

            app(AuditLogger::class)->record(
                'store.transaction_reversed',
                $locked,
                $admin,
                oldValues: ['amount_due' => $locked->amount_due, 'amount_paid' => $locked->amount_paid],
                newValues: ['reversed_at' => $locked->reversed_at?->toIso8601String()],
                description: trim($reason),
            );

            return $locked->refresh();
        });
    }
}
