<?php

namespace App\Filament\Resources\DailyRoutes\Pages;

use App\Actions\SyncDailyRouteStores;
use App\Filament\Resources\DailyRoutes\DailyRouteResource;
use App\Models\DailyRoute;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;

class CreateDailyRoute extends CreateRecord
{
    protected static string $resource = DailyRouteResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $areaIds = collect($this->data['area_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $data['created_by'] = auth()->id();
        $data['route_area_id'] = $areaIds[0] ?? null;
        if (config('maps.enforce_unique_area_per_date') && DailyRoute::query()
            ->whereDate('route_date', $data['route_date'])
            ->where(function ($query) use ($areaIds): void {
                $query->whereIn('route_area_id', $areaIds)
                    ->orWhereHas('areas', fn ($areas) => $areas->whereIn('route_areas.id', $areaIds));
            })->exists()) {
            throw ValidationException::withMessages(['area_ids' => 'Salah satu area sudah ditugaskan pada tanggal tersebut.']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        app(SyncDailyRouteStores::class)->execute(
            $this->record,
            $this->data['area_ids'] ?? [],
            $this->data['extra_store_ids'] ?? [],
        );
    }
}
