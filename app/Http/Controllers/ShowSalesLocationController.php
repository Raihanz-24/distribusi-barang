<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

class ShowSalesLocationController extends Controller
{
    public function __invoke(User $sales): JsonResponse
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $location = $sales->latestLocation;

        if (! $location) {
            return response()->json(['found' => false]);
        }

        $stale = $location->recorded_at->lt(now()->subMinutes(15));

        return response()->json([
            'found' => true,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'accuracy_meters' => $location->accuracy_meters,
            'recorded_at' => $location->recorded_at->toIso8601String(),
            'formatted' => $location->recorded_at->timezone(config('app.timezone'))->format('d M Y, H:i:s'),
            'human' => $location->recorded_at->diffForHumans(),
            'is_stale' => $stale,
        ]);
    }
}
