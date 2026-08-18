<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|\UnitEnum|null $navigationGroup = 'Produk & Stok';

    protected static ?string $navigationLabel = 'Produk';

    protected static ?string $modelLabel = 'Produk';

    protected static ?string $pluralModelLabel = 'Produk';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Produk')
                ->description('Satuan stok terkecil adalah bungkus. Konversi renteng mengikuti nilai per produk.')
                ->schema([
                    TextInput::make('code')
                        ->label('Kode produk')
                        ->required()
                        ->maxLength(30)
                        ->unique(ignoreRecord: true)
                        ->helperText('Contoh: P untuk Premium atau J untuk Jahe.'),
                    TextInput::make('name')
                        ->label('Nama produk')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('unit_name')
                        ->label('Nama satuan terkecil')
                        ->required()
                        ->default('bungkus')
                        ->maxLength(30),
                    TextInput::make('pieces_per_renteng')
                        ->label('Isi per renteng')
                        ->required()
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->default(12)
                        ->suffix('bungkus'),
                    Toggle::make('is_active')
                        ->label('Produk aktif')
                        ->default(true),
                ])
                ->columns([
                    'default' => 1,
                    'md' => 2,
                ]),
            Section::make('Harga Produk')
                ->description('Ubah nominal lalu simpan produk. Sistem membuat versi harga baru tanpa mengubah histori sebelumnya.')
                ->schema([
                    TextInput::make('current_price_per_renteng')
                        ->label('Harga per renteng')
                        ->prefix('Rp')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->helperText('Nilai tagihan tidak dihitung per bungkus. Stok bungkus dan pembayaran aktual akan dicatat terpisah.')
                        ->dehydrated(false),
                    ViewField::make('price_history')
                        ->view('filament.forms.product-price-history')
                        ->dehydrated(false)
                        ->columnSpanFull(),
                ])
                ->columns([
                    'default' => 1,
                    'md' => 2,
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama produk')->searchable()->sortable(),
                TextColumn::make('pieces_per_renteng')
                    ->label('Isi renteng')
                    ->formatStateUsing(fn (int $state, Product $record) => $state.' '.$record->unit_name),
                TextColumn::make('currentPrice.price_per_renteng')
                    ->label('Harga/renteng')
                    ->money('IDR', locale: 'id')
                    ->placeholder('Belum diatur'),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn (bool $state) => $state ? 'success' : 'gray'),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Status produk'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

}
