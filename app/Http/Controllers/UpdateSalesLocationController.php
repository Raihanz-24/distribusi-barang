<?php

namespace App\Http\Controllers;

use App\Services\ActiveSalesRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateSalesLocationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isSales(), 403);

        if (! app(ActiveSalesRoute::class)->find($request->user())) {
            return response()->json([
                'saved' => false,
                'message' => 'Lokasi hanya direkam selama pengiriman aktif.',
            ], 409);
        }

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'altitude' => ['nullable', 'numeric', 'between:-1000,20000'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'speed' => ['nullable', 'numeric', 'min:0', 'max:500'],
        ]);

        $location = $request->user()->latestLocation()->updateOrCreate([], [
            'latitude' => round((float) $data['latitude'], 7),
            'longitude' => round((float) $data['longitude'], 7),
            'accuracy_meters' => isset($data['accuracy']) ? round((float) $data['accuracy'], 2) : null,
            'altitude_meters' => isset($data['altitude']) ? round((float) $data['altitude'], 2) : null,
            'heading_degrees' => isset($data['heading']) ? round((float) $data['heading'], 2) : null,
            'speed_mps' => isset($data['speed']) ? round((float) $data['speed'], 2) : null,
            'device_info' => mb_substr((string) $request->userAgent(), 0, 500),
            'recorded_at' => now(),
        ]);

        return response()->json([
            'saved' => true,
            'recorded_at' => $location->recorded_at->toIso8601String(),
        ]);
    }
}
