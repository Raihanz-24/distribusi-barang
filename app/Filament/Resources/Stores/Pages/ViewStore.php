<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Actions\ReverseStoreTransaction;
use App\Filament\Resources\Stores\StoreResource;
use App\Models\StoreVisit;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;

class ViewStore extends ViewRecord
{
    protected static string $resource = StoreResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public function getTitle(): string
    {
        return 'Riwayat Transaksi — '.$this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reverseTransaction')
                ->label('Batalkan transaksi')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn () => auth()->user()->isAdmin())
                ->schema([
                    Select::make('visit_id')
                        ->label('Transaksi yang dibatalkan')
                        ->options(fn () => $this->record->visits()
                            ->with('sales:id,name')
                            ->whereNull('reversed_at')
                            ->where(fn ($query) => $query->whereHas('items')->orWhere('amount_paid', '>', 0))
                            ->latest('completed_at')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn (StoreVisit $visit) => [
                                $visit->id => ($visit->completed_at?->format('d M Y, H:i') ?? '-')
                                    .' · '.$visit->sales->name
                                    .' · Bayar Rp '.number_format($visit->amount_paid, 0, ',', '.'),
                            ]))
                        ->required()
                        ->searchable(),
                    Textarea::make('reason')
                        ->label('Alasan pembatalan')
                        ->helperText('Stok dan ledger hutang akan dibalik otomatis. Riwayat asli tetap disimpan.')
                        ->required()
                        ->minLength(10)
                        ->rows(3),
                ])
                ->requiresConfirmation()
                ->modalHeading('Batalkan transaksi toko?')
                ->modalDescription('Tindakan ini tidak menghapus transaksi asli dan hanya dapat dilakukan sebelum closing diajukan.')
                ->action(function (array $data): void {
                    try {
                        app(ReverseStoreTransaction::class)->execute(
                            StoreVisit::query()->findOrFail($data['visit_id']),
                            auth()->user(),
                            $data['reason'],
                        );
                        Notification::make()->title('Transaksi berhasil dibatalkan')->success()->send();
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Transaksi tidak dapat dibatalkan')
                            ->body(collect($exception->errors())->flatten()->first())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
            EditAction::make()->label('Edit toko'),
        ];
    }
}
