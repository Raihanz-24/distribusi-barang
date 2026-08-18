<?php

return [
    'style_url' => env('MAP_STYLE_URL'),
    'default_latitude' => (float) env('MAP_DEFAULT_LATITUDE', -7.8669),
    'default_longitude' => (float) env('MAP_DEFAULT_LONGITUDE', 113.3090),
    'default_zoom' => (float) env('MAP_DEFAULT_ZOOM', 10),
    'nearby_radius_meters' => (int) env('NEARBY_STORE_RADIUS_METERS', 150),
    'nearby_limit' => (int) env('NEARBY_STORE_LIMIT', 5),
    'routing_provider' => env('ROUTING_PROVIDER', 'local'),
    'osrm_base_url' => env('OSRM_BASE_URL'),
    'http_timeout_seconds' => (int) env('MAP_HTTP_TIMEOUT_SECONDS', 10),
    'cache_seconds' => (int) env('MAP_CACHE_SECONDS', 3600),
    'enforce_unique_area_per_date' => (bool) env('ROUTE_UNIQUE_AREA_PER_DATE', true),
    'allow_outside_route_visits' => (bool) env('ALLOW_OUTSIDE_ROUTE_VISITS', true),
    'geocoding_base_url' => env('GEOCODING_BASE_URL'),
    'geocoding_user_agent' => env('GEOCODING_USER_AGENT', 'PancorMasStoreManagement/1.0'),
    'geocoding_cache_seconds' => (int) env('GEOCODING_CACHE_SECONDS', 86400),
];
