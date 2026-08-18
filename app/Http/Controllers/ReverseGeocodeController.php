<?php

namespace App\Http\Controllers;

use App\Services\IndonesianRegionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ReverseGeocodeController extends Controller
{
    public function __invoke(Request $request, IndonesianRegionResolver $resolver): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $baseUrl = rtrim((string) config('maps.geocoding_base_url'), '/');
        abort_if($baseUrl === '', 503, 'Layanan pencarian alamat belum dikonfigurasi.');

        $cacheKey = sprintf('reverse-geocode:%.5f:%.5f', $data['latitude'], $data['longitude']);
        $result = Cache::remember($cacheKey, config('maps.geocoding_cache_seconds'), function () use ($baseUrl, $data): array {
            return Http::acceptJson()
                ->withUserAgent(config('maps.geocoding_user_agent'))
                ->timeout(config('maps.http_timeout_seconds'))
                ->retry(2, 300)
                ->get($baseUrl.'/reverse', [
                    'lat' => $data['latitude'],
                    'lon' => $data['longitude'],
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'accept-language' => 'id',
                    'zoom' => 18,
                ])
                ->throw()
                ->json();
        });

        return response()->json([
            'display_name' => $result['display_name'] ?? null,
            ...$resolver->fromGeocoder($result['address'] ?? []),
        ]);
    }
}
