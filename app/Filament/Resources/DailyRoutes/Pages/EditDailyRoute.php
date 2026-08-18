<?php

namespace App\Filament\Resources\DailyRoutes\Pages;

use App\Actions\OptimizeDailyRoute;
use App\Actions\SyncDailyRouteStores;
use App\Enums\OptimizationStatus;
use App\Enums\VisitStatus;
use App\Filament\Resources\DailyRoutes\DailyRouteResource;
use App\Models\DailyRoute;
use App\Models\RouteArea;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditDailyRoute extends EditRecord
{
    protected static string $resource = DailyRouteResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('optimize')
                ->label('Optimalkan Rute')
                ->icon('heroicon-o-sparkles')
                ->visible(fn () => auth()->user()->isAdmin())
                ->action(function (OptimizeDailyRoute $action): void {
                    $action->execute($this->record, auth()->user());
                    Notification::make()->title('Urutan rute berhasil dihitung')->success()->send();
                    $this->refreshFormData(['optimization_status', 'optimization_provider']);
                }),
            Action::make('manualOrder')
                ->label('Gunakan Urutan Manual')
                ->visible(fn () => auth()->user()->isAdmin())
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update(['optimization_status' => OptimizationStatus::Manual]);
                    Notification::make()->title('Urutan manual diaktifkan')->success()->send();
                }),
            Action::make('reschedule')
                ->label('Jadwalkan Ulang Toko')
                ->visible(fn () => auth()->user()->isAdmin() && $this->record->routeStores()
                    ->whereIn('visit_status', [VisitStatus::Closed, VisitStatus::Postponed, VisitStatus::OwnerAbsent])->exists())
                ->schema([
                    Select::make('daily_route_store_id')
                        ->label('Toko')
                        ->options(fn () => $this->record->routeStores()
                            ->with('store')
                            ->whereIn('visit_status', [VisitStatus::Closed, VisitStatus::Postponed, VisitStatus::OwnerAbsent])
                            ->get()->pluck('store.name', 'id'))
                        ->required(),
                    Select::make('target_route_id')
                        ->label('Rute tujuan')
                        ->options(fn () => DailyRoute::query()
                            ->whereDate('route_date', '>', $this->record->route_date)
                            ->orderBy('route_date')
                            ->with('sales')
                            ->get()
                            ->mapWithKeys(fn (DailyRoute $route) => [
                                $route->id => $route->route_date->format('d M Y').' — '.$route->sales->name,
                            ]))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    DB::transaction(function () use ($data): void {
                        $source = $this->record->routeStores()->lockForUpdate()->findOrFail($data['daily_route_store_id']);
                        $target = DailyRoute::lockForUpdate()->findOrFail($data['target_route_id']);
                        $target->routeStores()->firstOrCreate(
                            ['store_id' => $source->store_id],
                            [
                                'priority' => $source->priority,
                                'visit_status' => VisitStatus::Pending,
                                'scheduled_reason' => 'Penjadwalan ulang dari rute '.$this->record->route_date->format('Y-m-d'),
                                'rescheduled_from_id' => $source->id,
                            ],
                        );
                    });
                    Notification::make()->title('Toko ditambahkan ke rute tujuan')->success()->send();
                }),
        ];
    }

    protected function afterSave(): void
    {
        app(SyncDailyRouteStores::class)->execute(
            $this->record,
            $this->data['area_ids'] ?? [],
            $this->data['extra_store_ids'] ?? [],
        );
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $areaIds = $this->record->areas()->pluck('route_areas.id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($areaIds === [] && $this->record->route_area_id) {
            $areaIds = [(int) $this->record->route_area_id];
        }
        $areaStoreIds = RouteArea::query()
            ->whereIn('id', $areaIds)
            ->with('stores:id')
            ->get()
            ->flatMap->stores
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $routeStoreIds = $this->record->routeStores()
            ->pluck('store_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $data['area_ids'] = $areaIds;
        $data['area_store_ids'] = $areaStoreIds;
        $data['extra_store_ids'] = array_values(array_diff($routeStoreIds, $areaStoreIds));

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $areaIds = collect($this->data['area_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $data['route_area_id'] = $areaIds[0] ?? null;
        if (config('maps.enforce_unique_area_per_date') && DailyRoute::query()
            ->whereKeyNot($this->record->id)
            ->whereDate('route_date', $data['route_date'])
            ->where(function ($query) use ($areaIds): void {
                $query->whereIn('route_area_id', $areaIds)
                    ->orWhereHas('areas', fn ($areas) => $areas->whereIn('route_areas.id', $areaIds));
            })->exists()) {
            throw ValidationException::withMessages(['area_ids' => 'Salah satu area sudah ditugaskan pada tanggal tersebut.']);
        }

        return $data;
    }
}
