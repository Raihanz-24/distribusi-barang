<?php

namespace App\Services;

use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Models\Village;

class IndonesianRegionResolver
{
    public function fromGeocoder(array $address): array
    {
        $provinceName = $address['state'] ?? $address['province'] ?? null;
        $cityName = $address['county'] ?? $address['city'] ?? $address['municipality'] ?? null;
        $districtName = $address['city_district'] ?? $address['district'] ?? $address['suburb'] ?? null;
        $villageName = $address['village'] ?? $address['town'] ?? $address['neighbourhood'] ?? null;

        $province = $this->findByName(Province::query(), $provinceName);
        $city = $province
            ? $this->findByName(City::query()->where('province_code', $province->code), $cityName)
            : null;
        $district = $city
            ? $this->findByName(District::query()->where('city_code', $city->code), $districtName)
            : null;
        $village = $district
            ? $this->findByName(Village::query()->where('district_code', $district->code), $villageName)
            : null;

        return [
            'province_code' => $province?->code,
            'province' => $province?->name ?? $provinceName,
            'city_code' => $city?->code,
            'regency' => $city?->name ?? $cityName,
            'district_code' => $district?->code,
            'district' => $district?->name ?? $districtName,
            'village_code' => $village?->code,
            'village' => $village?->name ?? $villageName,
        ];
    }

    public function namesFromCodes(array $data): array
    {
        $data['province'] = filled($data['province_code'] ?? null)
            ? Province::where('code', $data['province_code'])->value('name')
            : null;
        $data['regency'] = filled($data['city_code'] ?? null)
            ? City::where('code', $data['city_code'])->value('name')
            : null;
        $data['district'] = filled($data['district_code'] ?? null)
            ? District::where('code', $data['district_code'])->value('name')
            : null;
        $data['village'] = filled($data['village_code'] ?? null)
            ? Village::where('code', $data['village_code'])->value('name')
            : null;

        return $data;
    }

    private function findByName($query, ?string $name)
    {
        if (blank($name)) {
            return null;
        }

        $normalized = trim(preg_replace(
            '/\b(provinsi|province|kabupaten|kab\.?|kota|kecamatan|kec\.?|desa|kelurahan)\b/i',
            '',
            $name
        ));

        return (clone $query)->where('name', 'like', '%'.$normalized.'%')->first();
    }
}
