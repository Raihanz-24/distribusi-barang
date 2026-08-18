<?php

namespace App\Filament\Resources\Stores;

use App\Enums\StoreStatus;
use App\Enums\VerificationStatus;
use App\Filament\Resources\Stores\Pages\CreateStore;
use App\Filament\Resources\Stores\Pages\EditStore;
use App\Filament\Resources\Stores\Pages\ListStores;
use App\Filament\Resources\Stores\Pages\ViewStore;
use App\Models\Store;
use App\Services\StoreTransactionReport;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Models\Village;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Toko';

    protected static ?string $modelLabel = 'Toko';

    protected static ?string $pluralModelLabel = 'Toko';

    protected static string|\UnitEnum|null $navigationGroup = 'Peta & Rute';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Toko')->schema([
                Hidden::make('code'),
                TextInput::make('name')->label('Nama toko')->required()->maxLength(255),
                TextInput::make('owner_name')->label('Nama pemilik')->maxLength(255),
                TextInput::make('phone')->label('Telepon')->tel()->maxLength(30),
            ])->columns(2),
            Section::make('Wilayah dan Alamat')
                ->description('Pilih wilayah secara berurutan. Data berikutnya akan muncul setelah pilihan sebelumnya diisi.')
                ->schema([
                    Select::make('province_code')
                        ->label('Provinsi')
                        ->options(fn () => Province::query()->orderBy('name')->pluck('name', 'code'))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('city_code', null);
                            $set('district_code', null);
                            $set('village_code', null);
                        })
                        ->required(),
                    Select::make('city_code')
                        ->label('Kabupaten/Kota')
                        ->options(fn (Get $get) => filled($get('province_code'))
                            ? City::query()->where('province_code', $get('province_code'))->orderBy('name')->pluck('name', 'code')
                            : [])
                        ->searchable()
                        ->preload()
                        ->live()
                        ->disabled(fn (Get $get) => blank($get('province_code')))
                        ->afterStateUpdated(function (Set $set): void {
                            $set('district_code', null);
                            $set('village_code', null);
                        })
                        ->required(),
                    Select::make('district_code')
                        ->label('Kecamatan')
                        ->options(fn (Get $get) => filled($get('city_code'))
                            ? District::query()->where('city_code', $get('city_code'))->orderBy('name')->pluck('name', 'code')
                            : [])
                        ->searchable()
                        ->preload()
                        ->live()
                        ->disabled(fn (Get $get) => blank($get('city_code')))
                        ->afterStateUpdated(fn (Set $set) => $set('village_code', null))
                        ->required(),
                    Select::make('village_code')
                        ->label('Desa/Kelurahan')
                        ->options(fn (Get $get) => filled($get('district_code'))
                            ? Village::query()->where('district_code', $get('district_code'))->orderBy('name')->pluck('name', 'code')
                            : [])
                        ->searchable()
                        ->preload()
                        ->disabled(fn (Get $get) => blank($get('district_code')))
                        ->required(),
                    Hidden::make('province'),
                    Hidden::make('regency'),
                    Hidden::make('district'),
                    Hidden::make('village'),
                    Textarea::make('address')
                        ->label('Alamat lengkap')
                        ->helperText('Alamat otomatis terisi saat GPS berhasil ditemukan dan tetap dapat diperbaiki manual.')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),
            Section::make('Lokasi GPS')
                ->description('Geser pin, klik peta, atau gunakan lokasi perangkat. Pastikan pin tepat di posisi toko.')
                ->schema([
                    ViewField::make('coordinate_picker')
                        ->view('filament.forms.coordinate-picker')
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    TextInput::make('latitude')->label('Latitude')->numeric()->minValue(-90)->maxValue(90),
                    TextInput::make('longitude')->label('Longitude')->numeric()->minValue(-180)->maxValue(180),
                    TextInput::make('location_accuracy')->label('Akurasi GPS (meter)')->numeric()->minValue(0),
                    TextInput::make('map_reference')->label('Referensi peta'),
                ])->columns(2),
            Section::make('Status')->schema([
                Select::make('status')->label('Status toko')->options(self::enumOptions(StoreStatus::cases()))->required(),
                Select::make('verification_status')
                    ->label('Verifikasi')
                    ->options(self::enumOptions(VerificationStatus::cases()))
                    ->disabled(fn () => auth()->user()?->isSales())
                    ->dehydrated(fn () => auth()->user()?->isAdmin())
                    ->default(VerificationStatus::Pending->value),
                Toggle::make('is_active')->label('Aktif')->default(true),
                FileUpload::make('photo_path')
                    ->label('Foto toko')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(4096)
                    ->directory('stores')
                    ->helperText('JPG, PNG, atau WebP. Maksimal 4 MB.'),
                Textarea::make('notes')->label('Catatan')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable(),
                TextColumn::make('name')->label('Nama toko')->searchable()->sortable(),
                TextColumn::make('district')->label('Kecamatan')->searchable(),
                TextColumn::make('verification_status')->label('Verifikasi')->badge(),
                TextColumn::make('routeAreas.name')->label('Area')->badge(),
                TextColumn::make('updated_at')->label('Diperbarui')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('verification_status')->label('Verifikasi')->options(self::enumOptions(VerificationStatus::cases())),
                SelectFilter::make('routeAreas')->label('Area')->relationship('routeAreas', 'name'),
                TernaryFilter::make('is_active')->label('Status aktif'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi Toko')
                ->schema([
                    TextEntry::make('name')->label('Nama toko')->weight('bold'),
                    TextEntry::make('code')->label('Kode toko')->copyable(),
                    TextEntry::make('owner_name')->label('Pemilik')->placeholder('-'),
                    TextEntry::make('phone')->label('Telepon')->placeholder('-'),
                    TextEntry::make('address')->label('Alamat')->columnSpanFull(),
                    TextEntry::make('routeAreas.name')->label('Area rute')->badge()->placeholder('Belum masuk area'),
                ])
                ->columns([
                    'default' => 1,
                    'md' => 2,
                    'xl' => 3,
                ]),
            Section::make('Riwayat Transaksi')
                ->description('Seluruh penjualan, pembayaran, dan piutang toko akan ditampilkan pada bagian ini.')
                ->schema([
                    ViewEntry::make('transaction_history')
                        ->hiddenLabel()
                        ->view('filament.stores.transaction-history')
                        ->viewData(fn (Store $record) => app(StoreTransactionReport::class)->data($record))
                        ->columnSpanFull(),
                ]),
            Section::make('Riwayat Kunjungan')
                ->description('Aktivitas kunjungan sales yang sudah tercatat pada toko ini.')
                ->schema([
                    RepeatableEntry::make('visit_history')
                        ->hiddenLabel()
                        ->state(fn (Store $record) => $record->visits()
                            ->with('sales:id,name')
                            ->latest('arrived_at')
                            ->limit(50)
                            ->get())
                        ->schema([
                            TextEntry::make('arrived_at')
                                ->label('Waktu kunjungan')
                                ->dateTime('d M Y, H:i')
                                ->timezone(config('app.timezone')),
                            TextEntry::make('sales.name')->label('Sales'),
                            TextEntry::make('visit_status')
                                ->label('Hasil')
                                ->formatStateUsing(fn ($state) => $state?->label() ?? '-')
                                ->badge(),
                            TextEntry::make('visit_notes')->label('Catatan')->placeholder('-')->columnSpanFull(),
                        ])
                        ->columns([
                            'default' => 1,
                            'md' => 3,
                        ]),
                ]),
        ])->columns(1);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStores::route('/'),
            'create' => CreateStore::route('/create'),
            'view' => ViewStore::route('/{record}'),
            'edit' => EditStore::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        if (auth()->user()?->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $scope): void {
            $scope->where('created_by', auth()->id())
                ->orWhereHas('dailyRouteStores.dailyRoute', fn (Builder $route) => $route->where('sales_id', auth()->id()));
        });
    }

    private static function enumOptions(array $cases): array
    {
        return collect($cases)->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
