<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Models\Village;
use Tests\TestCase;

class ReverseGeocodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_reverse_geocoding_resolves_indonesian_region_codes(): void
    {
        config(['maps.geocoding_base_url' => 'https://geocoder.test']);
        Province::create(['code' => '35', 'name' => 'JAWA TIMUR']);
        City::create(['code' => '3513', 'province_code' => '35', 'name' => 'KABUPATEN PROBOLINGGO']);
        District::create(['code' => '351314', 'city_code' => '3513', 'name' => 'KRAKSAAN']);
        Village::create(['code' => '3513142001', 'district_code' => '351314', 'name' => 'SIDOMUKTI']);
        Http::fake([
            'geocoder.test/*' => Http::response([
                'display_name' => 'Sidomukti, Kraksaan, Kabupaten Probolinggo, Jawa Timur',
                'address' => [
                    'state' => 'Jawa Timur',
                    'county' => 'Kabupaten Probolinggo',
                    'district' => 'Kecamatan Kraksaan',
                    'village' => 'Desa Sidomukti',
                ],
            ]),
        ]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->getJson('/internal/reverse-geocode?latitude=-7.75&longitude=113.41');

        $response->assertOk()->assertJson([
            'province_code' => '35',
            'city_code' => '3513',
            'district_code' => '351314',
            'village_code' => '3513142001',
        ]);
        Http::assertSentCount(1);
    }
}
