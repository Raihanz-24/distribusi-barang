<?php

namespace App\Livewire\Admin;

use App\Models\DailyRouteStore;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class DailyStoreMonitoringTable extends TableWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(DailyRouteStore::query()
                ->with(['dailyRoute.sales', 'store', 'latestVisit'])
                ->whereHas('dailyRoute', fn (Builder $query) => $query->whereDate('route_date', today())))
            ->heading('Monitoring Kunjungan Toko Hari Ini')
            ->description('Daftar toko sesuai jadwal Admin. Data kunjungan diperbarui otomatis setiap 10 detik.')
            ->queryStringIdentifier('daily-store-monitoring')
            ->poll('10s')
            ->columns([
                TextColumn::make('planned_order')
                    ->label('Urutan')
                    ->state(fn (DailyRouteStore $record) => $record->optimized_order ?? $record->planned_order ?? '-')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('dailyRoute.sales.name')->label('Sales')->searchable()->sortable(),
                TextColumn::make('store.name')->label('Toko')->searchable()->sortable(),
                TextColumn::make('store.address')->label('Alamat')->limit(48)->wrap(),
                TextColumn::make('monitoring_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (DailyRouteStore $record) => $record->latestVisit ? 'Sudah dikunjungi' : 'Belum dikunjungi')
                    ->color(fn (string $state) => $state === 'Sudah dikunjungi' ? 'success' : 'gray'),
                TextColumn::make('latestVisit.visit_status')
                    ->label('Hasil')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '-')
                    ->placeholder('-'),
                TextColumn::make('latestVisit.completed_at')
                    ->label('Waktu kunjungan')
                    ->dateTime('d M Y, H:i:s')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('sales_id')
                    ->label('Sales bertugas')
                    ->options(fn () => User::query()
                        ->where('role', 'sales')
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $salesId) => $query->whereHas(
                            'dailyRoute',
                            fn (Builder $route) => $route->where('sales_id', $salesId),
                        ),
                    )),
                SelectFilter::make('visit_monitoring')
                    ->label('Status kunjungan')
                    ->options([
                        'visited' => 'Sudah dikunjungi',
                        'unvisited' => 'Belum dikunjungi',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'visited' => $query->whereHas('latestVisit'),
                            'unvisited' => $query->whereDoesntHave('latestVisit'),
                            default => $query,
                        };
                    }),
            ])
            ->defaultSort('planned_order')
            ->emptyStateHeading('Belum ada jadwal toko hari ini')
            ->emptyStateDescription('Toko akan muncul setelah Admin membuat rute harian untuk Sales.');
    }
}
