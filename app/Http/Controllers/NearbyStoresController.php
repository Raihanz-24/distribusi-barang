<?php

namespace App\Http\Controllers;

use App\Data\Coordinate;
use App\Services\NearbyStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NearbyStoresController extends Controller
{
    public function __invoke(Request $request, NearbyStoreService $service): JsonResponse
    {
        abort_unless($request->user()?->isSales(), 403);
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);
        $stores = $service->find(
            $request->user(),
            new Coordinate((float) $data['latitude'], (float) $data['longitude']),
        );

        return response()->json($stores->map(fn ($store) => [
            'id' => $store->id,
            'name' => $store->name,
            'address' => $store->address,
            'photo_url' => $store->photo_path ? asset('storage/'.$store->photo_path) : null,
            'distance_meters' => $store->distance_meters,
            'in_today_route' => $store->in_today_route,
        ]));
    }
}
