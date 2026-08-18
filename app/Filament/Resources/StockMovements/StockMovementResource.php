<?php

namespace App\Filament\Resources\StockMovements;

use App\Enums\StockMovementType;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\StockMovement;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Produk & Stok';

    protected static ?string $navigationLabel = 'Riwayat Mutasi';

    protected static ?string $modelLabel = 'Mutasi Stok';

    protected static ?string $pluralModelLabel = 'Riwayat Mutasi';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')->label('Waktu')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('movement_type')->label('Mutasi')->badge()->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('external_source')->label('Asal Stok')->placeholder('-')->searchable(),
                TextColumn::make('product.name')->label('Produk')->searchable(),
                TextColumn::make('sourceLocation.name')->label('Dari')->placeholder('Eksternal'),
                TextColumn::make('destinationLocation.name')->label('Ke')->placeholder('Keluar sistem'),
                TextColumn::make('quantity_pieces')
                    ->label('Jumlah')
                    ->formatStateUsing(fn (int $state) => number_format($state, 0, ',', '.').' bungkus'),
                TextColumn::make('creator.name')->label('Dicatat oleh'),
                TextColumn::make('notes')->label('Catatan')->limit(40)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('product_id')->label('Produk')->relationship('product', 'name'),
                SelectFilter::make('movement_type')
                    ->label('Jenis mutasi')
                    ->options(collect(StockMovementType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])),
            ])
            ->defaultSort('occurred_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListStockMovements::route('/')];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->user()?->isSales()) {
            $query->where(function (Builder $scope): void {
                $scope->whereHas('sourceLocation', fn (Builder $location) => $location
                    ->where('type', 'sales')->where('reference_id', auth()->id()))
                    ->orWhereHas('destinationLocation', fn (Builder $location) => $location
                        ->where('type', 'sales')->where('reference_id', auth()->id()));
            });
        }

        return $query;
    }
}
