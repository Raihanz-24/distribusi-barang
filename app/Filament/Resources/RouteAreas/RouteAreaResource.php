<?php

namespace App\Filament\Resources\RouteAreas;

use App\Filament\Resources\RouteAreas\Pages\CreateRouteArea;
use App\Filament\Resources\RouteAreas\Pages\EditRouteArea;
use App\Filament\Resources\RouteAreas\Pages\ListRouteAreas;
use App\Models\RouteArea;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RouteAreaResource extends Resource
{
    protected static ?string $model = RouteArea::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|\UnitEnum|null $navigationGroup = 'Peta & Rute';

    protected static ?string $navigationLabel = 'Area Rute';

    protected static ?string $modelLabel = 'Area Rute';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Area')->schema([
                TextInput::make('code')
                    ->label('Kode area')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Dibuat otomatis saat area disimpan'),
                TextInput::make('name')->label('Nama')->required(),
                Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
                TextInput::make('default_start_latitude')->label('Latitude awal')->numeric()->minValue(-90)->maxValue(90),
                TextInput::make('default_start_longitude')->label('Longitude awal')->numeric()->minValue(-180)->maxValue(180),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ])->columns([
                'default' => 1,
                'md' => 2,
                'xl' => 3,
            ])->columnSpanFull(),
            Section::make('Pilih Toko dalam Area')
                ->description('Klik marker untuk memasukkan atau mengeluarkan toko dari area ini.')
                ->schema([
                    ViewField::make('area_store_map')
                        ->view('filament.forms.area-store-map')
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    Select::make('stores')
                        ->label('Toko terpilih')
                        ->relationship('stores', 'name')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->helperText('Daftar ini tersinkron dengan pilihan marker pada peta.')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label('Kode')->searchable(),
            TextColumn::make('name')->label('Nama')->searchable()->sortable(),
            TextColumn::make('stores_count')->label('Jumlah toko')->counts('stores'),
            TextColumn::make('is_active')->label('Aktif')->formatStateUsing(fn (bool $state) => $state ? 'Ya' : 'Tidak')->badge(),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRouteAreas::route('/'),
            'create' => CreateRouteArea::route('/create'),
            'edit' => EditRouteArea::route('/{record}/edit'),
        ];
    }
}
