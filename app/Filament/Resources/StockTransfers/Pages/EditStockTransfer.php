<?php

namespace App\Filament\Resources\StockTransfers\Pages;

use App\Actions\IssueStockTransfer;
use App\Actions\ReceiveStockTransfer;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Filament\Resources\StockTransfers\StockTransferResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditStockTransfer extends EditRecord
{
    protected static string $resource = StockTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('issue')
                ->label('Kirim Transfer')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn () => auth()->user()->isAdmin() && $this->record->status === StockTransferStatus::Draft)
                ->action(function (): void {
                    try {
                        app(IssueStockTransfer::class)->execute($this->record, auth()->user());
                    } catch (ValidationException $exception) {
                        $this->failureNotification($exception);

                        return;
                    }
                    $this->record->refresh();
                    $this->fillForm();
                    Notification::make()
                        ->title(match ($this->record->transfer_type) {
                            StockTransferType::WarehouseTransfer => 'Transfer menunggu konfirmasi Gudang Admin',
                            StockTransferType::IssueToSales => 'Pengambilan menunggu konfirmasi Sales',
                            StockTransferType::ReturnFromSales => 'Pengembalian menunggu konfirmasi Admin',
                        })
                        ->success()
                        ->send();
                }),
            Action::make('receive')
                ->label('Konfirmasi penerimaan')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === StockTransferStatus::Issued
                    && (
                        auth()->user()->isAdmin()
                        || (
                            $this->record->transfer_type === StockTransferType::IssueToSales
                            && $this->record->sales_id === auth()->id()
                        )
                    ))
                ->schema([
                    Repeater::make('items')
                        ->label('Jumlah aktual diterima')
                        ->default(fn () => $this->record->items()->with('product')->get()->map(fn ($item) => [
                            'item_id' => $item->id,
                            'product_name' => $item->product->name,
                            'quantity_issued' => $item->quantity_issued,
                            'quantity_received' => $item->quantity_issued,
                        ])->all())
                        ->schema([
                            Hidden::make('item_id'),
                            TextInput::make('product_name')->label('Produk')->disabled()->dehydrated(),
                            TextInput::make('quantity_issued')->label('Diserahkan')->disabled()->dehydrated()->suffix('bungkus'),
                            TextInput::make('quantity_received')
                                ->label('Diterima')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->suffix('bungkus')
                                ->required(),
                        ])
                        ->columns(3)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false),
                    Textarea::make('discrepancy_notes')
                        ->label('Catatan jika ada selisih')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    try {
                        app(ReceiveStockTransfer::class)->execute(
                            $this->record,
                            $data['items'],
                            auth()->user(),
                            $data['discrepancy_notes'] ?? null,
                        );
                    } catch (ValidationException $exception) {
                        $this->failureNotification($exception);

                        return;
                    }
                    $this->record->refresh();
                    $this->fillForm();
                    Notification::make()->title('Penerimaan stok berhasil dicatat')->success()->send();
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return auth()->user()->isAdmin() && $this->record->status === StockTransferStatus::Draft
            ? parent::getFormActions()
            : [];
    }

    private function failureNotification(ValidationException $exception): void
    {
        Notification::make()
            ->title('Transaksi tidak dapat diproses')
            ->body(collect($exception->errors())->flatten()->first()
                ?? 'Stok tidak memenuhi atau data transaksi belum valid.')
            ->danger()
            ->persistent()
            ->send();
    }
}
