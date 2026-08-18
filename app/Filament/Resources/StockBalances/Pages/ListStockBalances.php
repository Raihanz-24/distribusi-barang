<?php

namespace App\Filament\Resources\StockBalances\Pages;

use App\Actions\AdjustStock;
use App\Enums\StockLocationType;
use App\Enums\StockUnit;
use App\Filament\Resources\StockBalances\StockBalanceResource;
use App\Models\Product;
use App\Models\StockLocation;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;

class ListStockBalances extends ListRecords
{
    protected static string $resource = StockBalanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('productionStock')
                ->label('Input stok hasil produksi')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('success')
                ->visible(fn () => auth()->user()->isAdmin())
                ->schema([
                    Select::make('product_id')
                        ->label('Produk')
                        ->options(fn () => Product::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    TextInput::make('target_quantity')
                        ->label('Jumlah hasil produksi')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->suffix(fn (Get $get) => StockUnit::tryFrom((string) $get('quantity_unit'))?->label())
                        ->helperText('Sistem menyimpan dan menghitung saldo akhir dalam satuan bungkus.')
                        ->required(),
                    Select::make('quantity_unit')
                        ->label('Satuan input')
                        ->options(collect(StockUnit::cases())->mapWithKeys(
                            fn (StockUnit $unit) => [$unit->value => $unit->label()]
                        ))
                        ->default(StockUnit::Renteng->value)
                        ->native(false)
                        ->live()
                        ->required(),
                    TextInput::make('external_source')
                        ->label('Asal stok')
                        ->default('Produksi')
                        ->disabled()
                        ->dehydrated()
                        ->required()
                        ->maxLength(255),
                    Textarea::make('reason')
                        ->label('Keterangan penerimaan')
                        ->default('Penerimaan stok hasil produksi')
                        ->rows(2)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $mainWarehouse = StockLocation::query()
                        ->where('type', StockLocationType::MainWarehouse)
                        ->firstOrFail();
                    $product = Product::findOrFail($data['product_id']);
                    $unit = StockUnit::from($data['quantity_unit']);
                    $inputQuantity = (int) $data['target_quantity'];
                    $quantityPieces = $product->convertStockToPieces($inputQuantity, $unit);
                    $inputDescription = number_format($inputQuantity, 0, ',', '.').' '.strtolower($unit->label());

                    app(AdjustStock::class)->execute(
                        $mainWarehouse,
                        $product,
                        $quantityPieces,
                        "{$data['reason']} ({$inputDescription} = "
                            .number_format($quantityPieces, 0, ',', '.').' bungkus)',
                        auth()->user(),
                        'Produksi',
                        true,
                    );

                    Notification::make()
                        ->title('Hasil produksi masuk ke Gudang Utama')
                        ->body("{$inputDescription} berhasil ditambahkan sebagai "
                            .number_format($quantityPieces, 0, ',', '.').' bungkus ke Gudang Utama.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
