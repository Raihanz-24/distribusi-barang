<?php

namespace App\Filament\Resources\DailyRoutes;

use App\Enums\DailyRouteStatus;
use App\Filament\Resources\DailyRoutes\Pages\CreateDailyRoute;
use App\Filament\Resources\DailyRoutes\Pages\EditDailyRoute;
use App\Filament\Resources\DailyRoutes\Pages\ListDailyRoutes;
use App\Models\DailyRoute;
use App\Models\RouteArea;
use App\Models\Store;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DailyRouteResource extends Resource
{
    protected static ?string $model = DailyRoute::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'Peta & Rute';

    protected static ?string $navigationLabel = 'Jadwal Harian';

    protected static ?string $modelLabel = 'Rute Harian';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Jadwal')->schema([
                Select::make('area_ids')
                    ->label('Area')
                    ->options(fn () => RouteArea::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function (Set $set, ?array $state): void {
                        $storeIds = RouteArea::query()
                            ->whereIn('id', $state ?? [])
                            ->with('stores:id')
                            ->get()
                            ->flatMap->stores
                            ->pluck('id')
                            ->map(fn ($id) => (int) $id)
                            ->unique()
                            ->values()
                            ->all();
                        $set('area_store_ids', $storeIds);
                        $set('extra_store_ids', []);
                    })
                    ->helperText('Anda dapat memilih lebih dari satu area untuk satu sales pada tanggal yang sama.')
                    ->required(),
                Select::make('sales_id')
                    ->label('Sales')
                    ->options(fn () => User::query()->where('role', 'sales')->where('is_active', true)->pluck('name', 'id'))
                    ->required()->searchable(),
                DatePicker::make('route_date')
                    ->label('Tanggal')
                    ->required()
                    ->default(today())
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('extra_store_ids', [])),
                Select::make('status')->label('Status')->options(collect(DailyRouteStatus::cases())->mapWithKeys(
                    fn ($case) => [$case->value => $case->label()]
                ))->required()->default(DailyRouteStatus::Scheduled->value),
                TextInput::make('start_latitude')->label('Latitude awal')->numeric()->minValue(-90)->maxValue(90),
                TextInput::make('start_longitude')->label('Longitude awal')->numeric()->minValue(-180)->maxValue(180),
            ])->columns([
                'default' => 1,
                'md' => 2,
                'xl' => 3,
            ])->columnSpanFull(),
            Section::make('Pilih Toko dari Peta')
                ->description('Toko utama otomatis mengikuti Area. Peta ini hanya menampilkan pratinjau rute.')
                ->schema([
                    Hidden::make('area_store_ids')->dehydrated(false),
                    ViewField::make('daily_route_preview_map')
                        ->view('filament.forms.daily-route-preview-map')
                        ->dehydrated(false)
                        ->columnSpanFull(),
                    Select::make('extra_store_ids')
                        ->label('Tambahkan toko yang tutup pada hari sebelumnya')
                        ->options(function (Get $get) {
                            if (blank($get('route_date'))) {
                                return [];
                            }

                            $routeDate = Carbon::parse($get('route_date'));
                            $rangeStart = $routeDate->copy()->subDays(4)->startOfDay();
                            $rangeEnd = $routeDate->copy()->subDay()->endOfDay();
                            $areaStoreIds = collect($get('area_store_ids') ?? [])->map(fn ($id) => (int) $id)->all();

                            return Store::query()
                                ->whereNotIn('id', $areaStoreIds)
                                ->whereHas('visits', fn (Builder $query) => $query
                                    ->whereBetween('arrived_at', [$rangeStart, $rangeEnd])
                                    ->where('visit_status', 'closed'))
                                ->withMax(['visits as last_closed_at' => fn (Builder $query) => $query
                                    ->whereBetween('arrived_at', [$rangeStart, $rangeEnd])
                                    ->where('visit_status', 'closed')], 'arrived_at')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Store $store) => [
                                    $store->id => $store->name.' — tutup '.
                                        Carbon::parse($store->last_closed_at)->timezone(config('app.timezone'))->format('d M'),
                                ]);
                        })
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->dehydrated(false)
                        ->helperText('Pilihan berisi toko di luar area yang tercatat tutup dalam 1–4 hari sebelum tanggal rute.')
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('route_date')->label('Tanggal')->date('d M Y')->sortable(),
            TextColumn::make('sales.name')->label('Sales')->searchable(),
            TextColumn::make('areas.name')->label('Area')->badge(),
            TextColumn::make('route_stores_count')->label('Toko')->counts('routeStores'),
            TextColumn::make('status')->label('Status')->badge(),
            TextColumn::make('optimization_provider')->label('Optimasi')->placeholder('Belum'),
        ])->filters([
            SelectFilter::make('sales_id')->label('Sales')->relationship('sales', 'name'),
            SelectFilter::make('status')->label('Status')->options(collect(DailyRouteStatus::cases())->mapWithKeys(
                fn ($case) => [$case->value => $case->label()]
            )),
        ])->recordActions([EditAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return auth()->user()?->isSales() ? $query->where('sales_id', auth()->id()) : $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDailyRoutes::route('/'),
            'create' => CreateDailyRoute::route('/create'),
            'edit' => EditDailyRoute::route('/{record}/edit'),
        ];
    }
}
