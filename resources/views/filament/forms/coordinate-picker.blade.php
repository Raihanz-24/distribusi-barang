@once
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@endonce

<div
    wire:ignore
    x-data="coordinatePicker({
        latitude: @js((float) ($get('latitude') ?: config('maps.default_latitude'))),
        longitude: @js((float) ($get('longitude') ?: config('maps.default_longitude'))),
        zoom: @js(config('maps.default_zoom')),
        styleUrl: @js(config('maps.style_url')),
        reverseUrl: @js(route('internal.reverse-geocode')),
        setLatitude: value => $wire.set('data.latitude', value),
        setLongitude: value => $wire.set('data.longitude', value),
        setAccuracy: value => $wire.set('data.location_accuracy', value),
        setAddress: value => $wire.set('data.address', value),
        setProvince: value => $wire.set('data.province_code', value),
        setCity: value => $wire.set('data.city_code', value),
        setDistrict: value => $wire.set('data.district_code', value),
        setVillage: value => $wire.set('data.village_code', value),
    })"
    x-init="init()"
    class="space-y-3"
>
    <div x-ref="map" class="map-container h-80 rounded-xl border"></div>
    <button type="button" class="fi-btn fi-btn-color-primary" x-on:click="useMyLocation()">
        Gunakan lokasi saya
    </button>
    <span x-show="locating" class="text-sm text-primary-600">Mengambil GPS dan mencari alamat...</span>
    <p x-show="message" x-text="message" class="text-sm text-gray-600 dark:text-gray-300"></p>
    <p class="text-sm text-gray-500">Klik pada peta atau gunakan GPS perangkat untuk memilih koordinat. GPS memerlukan HTTPS atau localhost.</p>
</div>
