<?php

namespace App\Actions;

use App\Enums\DailyRouteStatus;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteSalesReturnSubmission
{
    public function execute(StockTransfer $return, User $actor): void
    {
        if (! $actor->isDeveloper()) {
            throw ValidationException::withMessages([
                'authorization' => 'Hanya Developer yang dapat menghapus pengajuan Sales.',
            ]);
        }

        DB::transaction(function () use ($return, $actor): void {
            $locked = StockTransfer::query()
                ->with(['items', 'returnSettlement.dailyRoute', 'pickupTransfer'])
                ->lockForUpdate()
                ->findOrFail($return->id);

            if ($locked->transfer_type !== StockTransferType::ReturnFromSales) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya dokumen pengembalian Sales yang dapat dihapus dari tindakan ini.',
                ]);
            }

            if ($locked->status !== StockTransferStatus::PendingApproval) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya pengajuan yang belum disetujui yang dapat dihapus. Pengembalian yang sudah diterima tidak boleh dihapus karena stok sudah berpindah.',
                ]);
            }

            $settlement = $locked->returnSettlement;
            $route = $settlement?->dailyRoute;
            $pickup = $locked->pickupTransfer;

            // Pengajuan pending belum memindahkan stok, sehingga cukup hapus
            // dokumen pengajuan. Settlement harus dihapus lebih dahulu karena
            // kolom return_transfer_id memakai foreign key restrictOnDelete.
            $settlement?->delete();
            $locked->items()->delete();
            $locked->delete();

            if ($route) {
                $route->update([
                    'status' => $route->visits()->exists()
                        ? DailyRouteStatus::InProgress
                        : DailyRouteStatus::Scheduled,
                ]);
            }

            app(AuditLogger::class)->record(
                'distribution.closing_submission_deleted',
                $pickup,
                $actor,
                newValues: [
                    'deleted_return_transfer' => $locked->number,
                    'pickup_transfer_id' => $locked->pickup_transfer_id,
                    'route_id' => $route?->id,
                ],
                description: "Developer menghapus pengajuan pengembalian Sales {$locked->number}.",
            );
        });
    }
}
