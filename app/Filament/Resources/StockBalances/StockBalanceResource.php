<?php

namespace App\Filament\Resources\StockBalances;

use App\Filament\Resources\StockBalances\Pages\ListStockBalances;
use App\Models\StockBalance;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockBalanceResource extends Resource
{
    protected static ?string $model = StockBalance::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'Produk & Stok';

    protected static ?string $navigationLabel = 'Stok Saat Ini';

    protected static ?string $modelLabel = 'Stok Saat Ini';

    protected static ?string $pluralModelLabel = 'Stok Saat Ini';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('location.name')->label('Lokasi')->searchable()->sortable(),
                TextColumn::make('location.type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('product.code')->label('Kode')->badge(),
                TextColumn::make('product.name')->label('Produk')->searchable()->sortable(),
                TextColumn::make('quantity_pieces')
                    ->label('Jumlah Tersedia')
                    ->formatStateUsing(fn (int $state) => number_format($state, 0, ',', '.').' bungkus')
                    ->sortable(),
                TextColumn::make('stock_conversion')
                    ->label('Konversi')
                    ->state(function (StockBalance $record): string {
                        $perRenteng = max(1, $record->product->pieces_per_renteng);

                        return intdiv($record->quantity_pieces, $perRenteng).' renteng + '
                            .($record->quantity_pieces % $perRenteng).' bungkus';
                    }),
                TextColumn::make('updated_at')->label('Diperbarui')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('stock_location_id')->label('Lokasi')->relationship('location', 'name'),
                SelectFilter::make('product_id')->label('Produk')->relationship('product', 'name'),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListStockBalances::route('/')];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['location', 'product']);

        if (auth()->user()?->isSales()) {
            $query->whereHas('location', fn (Builder $location) => $location
                ->where('type', 'sales')
                ->where('reference_id', auth()->id()));
        }

        return $query;
    }
}
