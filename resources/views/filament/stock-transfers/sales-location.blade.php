@php
    $location = $sales?->latestLocation;
    $isStale = $location?->recorded_at?->lt(now()->subMinutes(15));
@endphp

@if ($location)
    @php
        $latitude = (float) $location->latitude;
        $longitude = (float) $location->longitude;
        $delta = 0.012;
        $bbox = implode(',', [$longitude - $delta, $latitude - $delta, $longitude + $delta, $latitude + $delta]);
        $embedUrl = 'https://www.openstreetmap.org/export/embed.html?bbox='.urlencode($bbox).'&layer=mapnik&marker='.urlencode($latitude.','.$longitude);
    @endphp
    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs text-gray-500 dark:text-gray-400">Terakhir diperbarui</p>
                <p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $location->recorded_at->timezone(config('app.timezone'))->format('d M Y, H:i:s') }}</p>
                <p class="text-xs {{ $isStale ? 'text-danger-600' : 'text-success-600' }}">{{ $location->recorded_at->diffForHumans() }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs text-gray-500 dark:text-gray-400">Akurasi GPS</p>
                <p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $location->accuracy_meters !== null ? '± '.number_format($location->accuracy_meters).' meter' : 'Tidak tersedia' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs text-gray-500 dark:text-gray-400">Koordinat</p>
                <p class="mt-1 font-mono text-sm font-semibold text-gray-950 dark:text-white">{{ number_format($latitude, 7) }}, {{ number_format($longitude, 7) }}</p>
            </div>
        </div>

        @if ($isStale)
            <div class="rounded-xl bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:bg-warning-400/10 dark:text-warning-300">
                Posisi ini sudah lebih dari 15 menit. Sales mungkin menutup aplikasi, menonaktifkan GPS, atau sedang kehilangan koneksi.
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-white/10">
            <iframe title="Lokasi terakhir {{ $sales->name }}" src="{{ $embedUrl }}" class="h-96 w-full border-0" loading="lazy" referrerpolicy="no-referrer"></iframe>
        </div>

        <div class="flex justify-end">
            <a href="https://www.google.com/maps/dir/?api=1&destination={{ $latitude }},{{ $longitude }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                Buka navigasi ke lokasi Sales
            </a>
        </div>
    </div>
@else
    <div class="rounded-2xl border border-dashed border-gray-300 px-5 py-10 text-center dark:border-white/20">
        <p class="font-semibold text-gray-950 dark:text-white">Lokasi Sales belum tersedia</p>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Minta Sales membuka aplikasi melalui HTTPS dan mengizinkan akses lokasi pada browser atau PWA.</p>
    </div>
@endif
