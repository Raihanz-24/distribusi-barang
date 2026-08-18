<?php

namespace App\Http\Controllers;

use App\Enums\VisitStatus;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreMapDataController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $query = Store::query()
            ->with(['routeAreas:id,name', 'latestVisit'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('area_id'), fn ($query) => $query->whereHas('routeAreas', fn ($area) => $area->whereKey($request->integer('area_id'))))
            ->when($request->filled('verification_status'), fn ($query) => $query->where('verification_status', $request->string('verification_status')))
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->boolean('needs_reschedule'), fn ($query) => $query->whereHas(
                'latestVisit',
                fn ($visit) => $visit->whereIn('visit_status', [
                    VisitStatus::Closed,
                    VisitStatus::OwnerAbsent,
                    VisitStatus::Postponed,
                    VisitStatus::NotFound,
                ])
            ))
            ->when($request->filled('visit_status'), fn ($query) => $query->whereHas(
                'latestVisit',
                fn ($visit) => $visit->where('visit_status', $request->string('visit_status'))
            ));

        $stores = $query
            ->limit(2000)
            ->get();

        $statistics = [
            'total' => Store::query()->count(),
            'active' => Store::query()->where('is_active', true)->count(),
            'pending_verification' => Store::query()->where('verification_status', 'pending')->count(),
            'without_coordinates' => Store::query()->whereNull('latitude')->orWhereNull('longitude')->count(),
            'needs_reschedule' => Store::query()->whereHas('latestVisit', fn ($visit) => $visit->whereIn('visit_status', [
                VisitStatus::Closed,
                VisitStatus::OwnerAbsent,
                VisitStatus::Postponed,
                VisitStatus::NotFound,
            ]))->count(),
        ];

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $stores->map(fn (Store $store) => [
                'type' => 'Feature',
                'id' => $store->id,
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $store->longitude, (float) $store->latitude],
                ],
                'properties' => [
                    'id' => $store->id,
                    'code' => $store->code,
                    'name' => $store->name,
                    'address' => $store->address,
                    'areas' => $store->routeAreas->pluck('name')->join(', '),
                    'verification_status' => $store->verification_status->value,
                    'is_active' => $store->is_active,
                    'last_visit' => $store->latestVisit?->arrived_at?->timezone(config('app.timezone'))->format('d M Y H:i'),
                    'last_visit_status' => $store->latestVisit?->visit_status?->value,
                    'last_visit_status_label' => $store->latestVisit?->visit_status?->label(),
                    'needs_reschedule' => in_array($store->latestVisit?->visit_status, [
                        VisitStatus::Closed,
                        VisitStatus::OwnerAbsent,
                        VisitStatus::Postponed,
                        VisitStatus::NotFound,
                    ], true),
                    'detail_url' => route('filament.admin.resources.stores.edit', $store),
                    'transaction_history_url' => route('filament.admin.resources.stores.view', $store),
                ],
            ])->values(),
            'count' => $stores->count(),
            'statistics' => $statistics,
        ]);
    }
}
