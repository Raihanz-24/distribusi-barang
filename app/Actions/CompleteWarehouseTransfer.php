<?php

namespace App\Actions;

use App\Enums\StockTransferType;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteWarehouseTransfer
{
    public function execute(StockTransfer $transfer, User $actor): StockTransfer
    {
        if ($transfer->transfer_type !== StockTransferType::WarehouseTransfer) {
            throw ValidationException::withMessages([
                'transfer_type' => 'Penyelesaian langsung hanya berlaku untuk transfer antar gudang.',
            ]);
        }

        return DB::transaction(function () use ($transfer, $actor): StockTransfer {
            app(IssueStockTransfer::class)->execute($transfer, $actor);
            $transfer->load('items');

            return app(ReceiveStockTransfer::class)->execute(
                $transfer,
                $transfer->items->map(fn ($item) => [
                    'item_id' => $item->id,
                    'quantity_received' => $item->quantity_issued,
                ])->all(),
                $actor,
            );
        });
    }
}
