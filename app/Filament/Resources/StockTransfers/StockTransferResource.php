<?php

namespace App\Filament\Resources\StockTransfers;

use App\Actions\ApproveSalesReturn;
use App\Actions\CompleteSalesPickup;
use App\Actions\IssueStockTransfer;
use App\Actions\SubmitSalesReturn;
use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Enums\StockUnit;
use App\Filament\Resources\StockTransfers\Pages\CreateStockTransfer;
use App\Filament\Resources\StockTransfers\Pages\EditStockTransfer;
use App\Filament\Resources\StockTransfers\Pages\ListStockTransfers;
use App\Models\DailyRoute;
use App\Models\StockTransfer;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class StockTransferResource extends Resource
{
    protected static ?string $model = StockTransfer::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|\UnitEnum|null $navigationGroup = 'Produk & Stok';

    protected static ?string $navigationLabel = 'Transfer Antar Gudang';

    protected static ?string $modelLabel = 'Transfer Antar Gudang';

    protected static ?string $pluralModelLabel = 'Transfer Antar Gudang';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Transfer Stok')
                ->description('Alur distribusi: Gudang Utama → Gudang Admin → Sales. Sisa bawaan Sales dikembalikan ke Gudang Admin.')
                ->schema([
                    TextInput::make('number')
                        ->label('Nomor')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Dibuat otomatis'),
                    Select::make('transfer_type')
                        ->label('Jenis transfer')
                        ->options(static::transferTypeOptions())
                        ->default(static::defaultTransferType())
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('sales_id', null);
                            $set('daily_route_id', null);
                        })
                        ->disabled(fn (?StockTransfer $record) => $record && $record->status !== StockTransferStatus::Draft),
                    Select::make('sales_id')
                        ->label('Sales penerima')
                        ->options(fn () => User::query()
                            ->where('role', 'sales')
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->required(fn (Get $get) => $get('transfer_type') !== StockTransferType::WarehouseTransfer->value)
                        ->visible(fn (Get $get) => $get('transfer_type') !== StockTransferType::WarehouseTransfer->value)
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('daily_route_id', null))
                        ->disabled(fn (?StockTransfer $record) => $record && $record->status !== StockTransferStatus::Draft),
                    Select::make('daily_route_id')
                        ->label('Jadwal / rute kerja')
                        ->options(fn (Get $get) => static::scheduleOptions($get))
                        ->placeholder(fn (Get $get) => $get('sales_id') ? 'Pilih jadwal kerja Sales' : 'Pilih Sales terlebih dahulu')
                        ->helperText('Pengiriman stok akan terhubung dengan toko dan area yang dijadwalkan pada hari tersebut.')
                        ->searchable()
                        ->required(fn (Get $get) => $get('transfer_type') === StockTransferType::IssueToSales->value)
                        ->visible(fn (Get $get) => $get('transfer_type') === StockTransferType::IssueToSales->value)
                        ->disabled(fn (Get $get, ?StockTransfer $record) => ! $get('sales_id')
                            || ($record && $record->status !== StockTransferStatus::Draft)),
                    Textarea::make('notes')
                        ->label('Catatan penyerahan')
                        ->rows(2)
                        ->disabled(fn (?StockTransfer $record) => $record && $record->status !== StockTransferStatus::Draft)
                        ->columnSpanFull(),
                ])
                ->columns([
                    'default' => 1,
                    'md' => 2,
                    'xl' => 3,
                ]),
            Section::make('Produk')
                ->description('Masukkan jumlah dalam bungkus. Sistem memeriksa ketersediaan stok pada lokasi asal.')
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->hiddenLabel()
                        ->schema([
                            Select::make('product_id')
                                ->label('Produk')
                                ->relationship('product', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->distinct()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                            TextInput::make('quantity_entered')
                                ->label('Jumlah')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->required(),
                            Select::make('quantity_unit')
                                ->label('Satuan')
                                ->options(collect(StockUnit::cases())->mapWithKeys(
                                    fn (StockUnit $unit) => [$unit->value => $unit->label()]
                                ))
                                ->default(StockUnit::Renteng->value)
                                ->native(false)
                                ->required(),
                        ])
                        ->columns(3)
                        ->minItems(1)
                        ->addActionLabel('Tambah produk')
                        ->defaultItems(2)
                        ->disabled(fn (?StockTransfer $record) => $record && $record->status !== StockTransferStatus::Draft)
                        ->columnSpanFull(),
                ]),
            Section::make('Status Penerimaan')
                ->visible(fn (?StockTransfer $record) => $record && $record->status !== StockTransferStatus::Draft)
                ->schema([
                    TextInput::make('status_label')
                        ->label('Status')
                        ->formatStateUsing(fn (?StockTransfer $record) => $record?->status->label())
                        ->disabled()
                        ->dehydrated(false),
                    Textarea::make('discrepancy_notes')
                        ->label('Catatan selisih')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn (?StockTransfer $record) => $record?->status === StockTransferStatus::Discrepancy),
                ])
                ->columns(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('Nomor')->searchable()->sortable(),
                TextColumn::make('transfer_type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),
                TextColumn::make('sales.name')->label('Sales')->searchable()->sortable(),
                TextColumn::make('dailyRoute.route_date')
                    ->label('Jadwal kerja')
                    ->date('d M Y')
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('items_count')->label('Produk')->counts('items'),
                TextColumn::make('total_pieces')
                    ->label('Total ekuivalen')
                    ->state(fn (StockTransfer $record) => $record->items->sum('quantity_issued'))
                    ->formatStateUsing(fn (int $state) => number_format($state, 0, ',', '.').' bungkus'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state, StockTransfer $record) => $record->displayStatusLabel())
                    ->color(fn ($state, StockTransfer $record) => $record->displayStatusLabel() === 'Dalam perjalanan' ? 'info' : match ($state) {
                        StockTransferStatus::Draft => 'gray',
                        StockTransferStatus::Issued => 'warning',
                        StockTransferStatus::PendingApproval => 'info',
                        StockTransferStatus::Received => 'success',
                        StockTransferStatus::Discrepancy => 'danger',
                        StockTransferStatus::Cancelled => 'gray',
                    }),
                TextColumn::make('pickup_at')
                    ->label('Pengambilan')
                    ->state(fn (StockTransfer $record) => $record->transfer_type === StockTransferType::ReturnFromSales
                        ? $record->pickupTransfer?->issued_at
                        : $record->issued_at)
                    ->dateTime('d M Y, H:i')
                    ->placeholder('-'),
                TextColumn::make('return_at')
                    ->label('Pengembalian')
                    ->state(fn (StockTransfer $record) => $record->transfer_type === StockTransferType::ReturnFromSales
                        ? $record->received_at
                        : null)
                    ->dateTime('d M Y, H:i')
                    ->placeholder('-'),
                TextColumn::make('returnSettlement.total_collection')
                    ->label('Tagihan final')
                    ->money('IDR', locale: 'id')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(StockTransferStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])),
                SelectFilter::make('sales_id')->label('Sales')->relationship('sales', 'name'),
                SelectFilter::make('transfer_type')
                    ->label('Jenis transfer')
                    ->options(static::transferTypeOptions()),
            ])
            ->recordActions([
                Action::make('trackSales')
                    ->label('Lacak Sales')
                    ->icon('heroicon-o-map-pin')
                    ->color('info')
                    ->size('xs')
                    ->button()
                    ->visible(fn (StockTransfer $record) => auth()->user()->isAdmin()
                        && $record->transfer_type === StockTransferType::IssueToSales
                        && $record->status === StockTransferStatus::Received
                        && $record->returnTransfer === null)
                    ->modalHeading(fn (StockTransfer $record) => 'Lokasi terakhir '.$record->sales?->name)
                    ->modalDescription('Posisi berasal dari GPS perangkat saat Sales terakhir membuka aplikasi.')
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalContent(fn (StockTransfer $record) => view('filament.stock-transfers.sales-location', [
                        'sales' => $record->sales?->load('latestLocation'),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                Action::make('issue')
                    ->label(fn (StockTransfer $record) => match ($record->transfer_type) {
                        StockTransferType::WarehouseTransfer => 'Kirim ke Gudang Admin',
                        StockTransferType::IssueToSales => 'Ambil',
                        StockTransferType::ReturnFromSales => 'Ajukan Pengembalian',
                    })
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->button()
                    ->size('xs')
                    ->requiresConfirmation()
                    ->modalHeading(fn (StockTransfer $record) => match ($record->transfer_type) {
                        StockTransferType::WarehouseTransfer => 'Kirim transfer ke Gudang Admin?',
                        StockTransferType::IssueToSales => 'Serahkan stok kepada Sales?',
                        StockTransferType::ReturnFromSales => 'Ajukan pengembalian stok Sales?',
                    })
                    ->visible(fn (StockTransfer $record) => auth()->user()->isAdmin()
                        && $record->status === StockTransferStatus::Draft
                        && $record->transfer_type !== StockTransferType::ReturnFromSales)
                    ->action(function (StockTransfer $record): void {
                        try {
                            if ($record->transfer_type === StockTransferType::IssueToSales) {
                                app(CompleteSalesPickup::class)->execute($record, auth()->user());
                            } else {
                                app(IssueStockTransfer::class)->execute($record, auth()->user());
                            }
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Stok tidak mencukupi')
                                ->body(collect($exception->errors())->flatten()->first()
                                    ?? 'Stok tidak memenuhi untuk transaksi ini.')
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title(match ($record->transfer_type) {
                                StockTransferType::WarehouseTransfer => 'Transfer dikirim ke Gudang Admin',
                                StockTransferType::IssueToSales => 'Stok masuk ke proses pengambilan',
                                StockTransferType::ReturnFromSales => 'Pengembalian stok telah diajukan',
                            })
                            ->success()
                            ->send();
                    }),
                Action::make('submitReturn')
                    ->label('Selesai')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('info')
                    ->size('xs')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading('Selesaikan pekerjaan hari ini?')
                    ->modalDescription('Seluruh transaksi toko, tagihan final, dan sisa stok akan dikunci lalu diajukan kepada Admin.')
                    ->visible(fn (StockTransfer $record) => auth()->user()->isSales()
                        && $record->sales_id === auth()->id()
                        && $record->transfer_type === StockTransferType::IssueToSales
                        && $record->status === StockTransferStatus::Received
                        && $record->returnTransfer === null)
                    ->action(function (StockTransfer $record): void {
                        try {
                            app(SubmitSalesReturn::class)->execute($record, auth()->user());
                            Notification::make()->title('Pengajuan dikirim kepada Admin')->success()->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Pengajuan tidak dapat dibuat')
                                ->body(collect($exception->errors())->flatten()->first())
                                ->danger()->persistent()->send();
                        }
                    }),
                Action::make('approveReturn')
                    ->label('Periksa & Konfirmasi')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->size('xs')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading('Pemeriksaan closing Sales')
                    ->modalDescription('Cocokkan uang dan stok fisik sebelum menyelesaikan distribusi.')
                    ->modalWidth(Width::FourExtraLarge)
                    ->fillForm(function (StockTransfer $record): array {
                        $record->loadMissing(['items.product', 'returnSettlement']);

                        return [
                            'expected_cash' => (int) ($record->returnSettlement?->total_collection ?? 0),
                            'actual_cash_received' => (int) ($record->returnSettlement?->total_collection ?? 0),
                            'received_items' => $record->items->map(fn ($item) => [
                                'item_id' => $item->id,
                                'product_name' => $item->product->name,
                                'expected_quantity' => $item->quantity_issued,
                                'quantity_received' => $item->quantity_issued,
                            ])->all(),
                        ];
                    })
                    ->schema([
                        TextInput::make('expected_cash')
                            ->label('Uang menurut sistem')
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('actual_cash_received')
                            ->label('Uang fisik diterima Admin')
                            ->prefix('Rp')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required(),
                        Repeater::make('received_items')
                            ->label('Pemeriksaan sisa stok')
                            ->schema([
                                Hidden::make('item_id'),
                                TextInput::make('product_name')->label('Produk')->disabled()->dehydrated(false),
                                TextInput::make('expected_quantity')->label('Menurut sistem')->suffix('bungkus')->disabled()->dehydrated(false),
                                TextInput::make('quantity_received')->label('Diterima Admin')->suffix('bungkus')->numeric()->integer()->minValue(0)->required(),
                            ])
                            ->columns(3)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                        Textarea::make('verification_notes')
                            ->label('Catatan pemeriksaan')
                            ->helperText('Wajib jika uang atau stok fisik memiliki selisih.')
                            ->rows(3),
                    ])
                    ->visible(fn (StockTransfer $record) => auth()->user()->isAdmin()
                        && $record->transfer_type === StockTransferType::ReturnFromSales
                        && $record->status === StockTransferStatus::PendingApproval)
                    ->action(function (StockTransfer $record, array $data): void {
                        try {
                            app(ApproveSalesReturn::class)->execute(
                                $record,
                                auth()->user(),
                                (int) $data['actual_cash_received'],
                                $data['received_items'] ?? [],
                                $data['verification_notes'] ?? null,
                            );
                            Notification::make()->title('Pengembalian Sales dikonfirmasi')->success()->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Pengembalian tidak dapat dikonfirmasi')
                                ->body(collect($exception->errors())->flatten()->first())
                                ->danger()->persistent()->send();
                        }
                    }),
                Action::make('detailReturn')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->size('xs')
                    ->visible(fn (StockTransfer $record) => $record->transfer_type === StockTransferType::ReturnFromSales)
                    ->modalHeading(fn (StockTransfer $record) => 'Rekap '.$record->number)
                    ->modalContent(fn (StockTransfer $record) => view('filament.stock-transfers.return-detail', [
                        'transfer' => $record->load([
                            'sales', 'items.product', 'returnSettlement.dailyRoute.visits.store',
                            'returnSettlement.dailyRoute.visits.items.product',
                        ]),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ], position: RecordActionsPosition::BeforeColumns)
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockTransfers::route('/'),
            'create' => CreateStockTransfer::route('/create'),
            'edit' => EditStockTransfer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['items', 'pickupTransfer', 'returnTransfer', 'returnSettlement']);
        $query->whereIn('transfer_type', static::allowedTransferTypes());

        if (auth()->user()?->isSales()) {
            $query->where('sales_id', auth()->id());
        }

        return $query;
    }

    protected static function allowedTransferTypes(): array
    {
        return [StockTransferType::WarehouseTransfer->value];
    }

    protected static function defaultTransferType(): string
    {
        return StockTransferType::WarehouseTransfer->value;
    }

    protected static function transferTypeOptions(): array
    {
        return collect(StockTransferType::cases())
            ->filter(fn (StockTransferType $type) => in_array($type->value, static::allowedTransferTypes(), true))
            ->mapWithKeys(fn (StockTransferType $type) => [$type->value => $type->label()])
            ->all();
    }

    protected static function scheduleOptions(Get $get): array
    {
        if (! $get('sales_id')) {
            return [];
        }

        return DailyRoute::query()
            ->with(['area', 'areas'])
            ->withCount('routeStores')
            ->where('sales_id', $get('sales_id'))
            ->whereDate('route_date', '>=', today())
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->orderBy('route_date')
            ->get()
            ->mapWithKeys(function (DailyRoute $route): array {
                $areaNames = $route->areas->pluck('name');

                if ($areaNames->isEmpty() && $route->area) {
                    $areaNames = collect([$route->area->name]);
                }

                $areas = $areaNames->isNotEmpty() ? $areaNames->join(', ') : 'Rute khusus';

                return [$route->id => $route->route_date->translatedFormat('d M Y')
                    ." — {$areas} — {$route->route_stores_count} toko"];
            })
            ->all();
    }
}
