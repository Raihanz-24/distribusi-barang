<?php

namespace App\Filament\Resources\StockTransfers\Pages;

use App\Actions\CompleteWarehouseTransfer;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Filament\Resources\StockTransfers\StockTransferResource;
use App\Models\DailyRoute;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\StockLocationService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;

class CreateStockTransfer extends CreateRecord
{
    protected static string $resource = StockTransferResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $locations = app(StockLocationService::class);
        $type = StockTransferType::from($data['transfer_type']);
        $mainWarehouse = $locations->mainWarehouse();
        $adminWarehouse = $locations->adminWarehouse();

        if ($type === StockTransferType::WarehouseTransfer) {
            $data['sales_id'] = null;
            $data['daily_route_id'] = null;
            $data['source_location_id'] = $mainWarehouse->id;
            $data['destination_location_id'] = $adminWarehouse->id;
        } else {
            $sales = User::query()->where('role', 'sales')->findOrFail($data['sales_id']);
            $salesLocation = $locations->forSales($sales);

            if ($type === StockTransferType::IssueToSales) {
                $route = DailyRoute::query()
                    ->whereKey($data['daily_route_id'] ?? null)
                    ->where('sales_id', $sales->id)
                    ->whereIn('status', ['scheduled', 'in_progress'])
                    ->first();

                if (! $route) {
                    throw ValidationException::withMessages([
                        'daily_route_id' => 'Jadwal kerja tidak valid atau bukan milik Sales yang dipilih.',
                    ]);
                }

                if (StockTransfer::query()->where('daily_route_id', $route->id)->exists()) {
                    throw ValidationException::withMessages([
                        'daily_route_id' => 'Jadwal kerja ini sudah memiliki transaksi pengiriman stok.',
                    ]);
                }

                $data['daily_route_id'] = $route->id;
                $data['source_location_id'] = $adminWarehouse->id;
                $data['destination_location_id'] = $salesLocation->id;
            } else {
                $data['daily_route_id'] = null;
                $data['source_location_id'] = $salesLocation->id;
                $data['destination_location_id'] = $adminWarehouse->id;
                $data['pickup_transfer_id'] = StockTransfer::query()
                    ->where('transfer_type', StockTransferType::IssueToSales)
                    ->where('sales_id', $sales->id)
                    ->whereNotNull('issued_at')
                    ->whereDoesntHave('returnTransfer')
                    ->latest('issued_at')
                    ->value('id');
            }
        }

        if ((int) $data['source_location_id'] === (int) $data['destination_location_id']) {
            throw ValidationException::withMessages([
                'destination_location_id' => 'Lokasi asal dan tujuan harus berbeda.',
            ]);
        }

        $data['status'] = StockTransferStatus::Draft;
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->record->transfer_type !== StockTransferType::WarehouseTransfer) {
            return;
        }

        try {
            app(CompleteWarehouseTransfer::class)->execute($this->record, auth()->user());
            $this->record->refresh();
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Stok tidak memenuhi untuk transaksi ini.';

            $this->record->delete();

            Notification::make()
                ->title('Stok tidak mencukupi')
                ->body($message)
                ->danger()
                ->persistent()
                ->send();

            throw new Halt;
        }
    }
}
