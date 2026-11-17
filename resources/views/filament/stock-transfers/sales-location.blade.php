@php
    $location = $sales?->latestLocation;
    $isStale = $location?->recorded_at?->lt(now()->subMinutes(15));
    $endpoint = route('internal.sales-location.show', ['sales' => $sales?->id]);
@endphp

<div id="sales-location-panel" data-endpoint="{{ $endpoint }}" data-has-location="{{ $location ? '1' : '0' }}">
@if ($location)
    @php
        $latitude = (float) $location->latitude;
        $longitude = (float) $location->longitude;
        $delta = 0.012;
        $bbox = implode(',', [$longitude - $delta, $latitude - $delta, $longitude + $delta, $latitude + $delta]);
        $embedUrl = 'https://www.openstreetmap.org/export/embed.html?bbox='.urlencode($bbox).'&layer=mapnik&marker='.urlencode($latitude.','.$longitude);
    @endphp
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Lokasi {{ $sales->name }}</h3>
            <button type="button" data-sales-location-refresh class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-primary-500 disabled:cursor-wait disabled:opacity-70">
                <svg data-refresh-icon class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 12a9 9 0 1 1-2.64-6.36"/>
                    <path d="M21 3v6h-6"/>
                </svg>
                <svg data-refresh-spinner class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/>
                </svg>
                <span data-refresh-label>Refresh</span>
            </button>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs text-gray-500 dark:text-gray-400">Terakhir diperbarui</p>
                <p class="mt-1 font-semibold text-gray-950 dark:text-white" data-field="formatted">{{ $location->recorded_at->timezone(config('app.timezone'))->format('d M Y, H:i:s') }}</p>
                <p class="text-xs {{ $isStale ? 'text-danger-600' : 'text-success-600' }}" data-field="human">{{ $location->recorded_at->diffForHumans() }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs text-gray-500 dark:text-gray-400">Akurasi GPS</p>
                <p class="mt-1 font-semibold text-gray-950 dark:text-white" data-field="accuracy">{{ $location->accuracy_meters !== null ? '± '.number_format($location->accuracy_meters).' meter' : 'Tidak tersedia' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-3 dark:border-white/10">
                <p class="text-xs text-gray-500 dark:text-gray-400">Koordinat</p>
                <p class="mt-1 font-mono text-sm font-semibold text-gray-950 dark:text-white" data-field="coords">{{ number_format($latitude, 7) }}, {{ number_format($longitude, 7) }}</p>
            </div>
        </div>

        <div data-field="stale-banner">
        @if ($isStale)
            <div class="rounded-xl bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:bg-warning-400/10 dark:text-warning-300">
                Posisi ini sudah lebih dari 15 menit. Sales mungkin menutup aplikasi, menonaktifkan GPS, atau sedang kehilangan koneksi.
            </div>
        @endif
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-white/10">
            <iframe title="Lokasi terakhir {{ $sales->name }}" src="{{ $embedUrl }}" data-field="map" class="h-96 w-full border-0" loading="lazy" referrerpolicy="no-referrer"></iframe>
        </div>

        <div class="flex justify-end">
            <a href="https://www.google.com/maps/dir/?api=1&destination={{ $latitude }},{{ $longitude }}" target="_blank" rel="noopener" data-field="nav" class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                Buka navigasi ke lokasi Sales
            </a>
        </div>
    </div>
@else
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Lokasi {{ $sales->name }}</h3>
            <button type="button" data-sales-location-refresh class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-primary-500 disabled:cursor-wait disabled:opacity-70">
                <svg data-refresh-icon class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 12a9 9 0 1 1-2.64-6.36"/>
                    <path d="M21 3v6h-6"/>
                </svg>
                <svg data-refresh-spinner class="hidden h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/>
                </svg>
                <span data-refresh-label>Refresh</span>
            </button>
        </div>
        <div class="rounded-2xl border border-dashed border-gray-300 px-5 py-10 text-center dark:border-white/20" data-field="empty">
            <p class="font-semibold text-gray-950 dark:text-white">Lokasi Sales belum tersedia</p>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Minta Sales membuka aplikasi melalui HTTPS dan mengizinkan akses lokasi pada browser atau PWA.</p>
        </div>
    </div>
@endif
</div>

<script>
    (() => {
        const panel = document.getElementById('sales-location-panel');
        if (!panel) return;

        const btn = panel.querySelector('[data-sales-location-refresh]');
        if (!btn) return;

        const icon = btn.querySelector('[data-refresh-icon]');
        const spinner = btn.querySelector('[data-refresh-spinner]');
        const label = btn.querySelector('[data-refresh-label]');
        const endpoint = panel.dataset.endpoint;

        const setLoading = on => {
            btn.disabled = on;
            icon?.classList.toggle('hidden', on);
            spinner?.classList.toggle('hidden', !on);
            if (label) label.textContent = on ? 'Memuat...' : 'Refresh';
        };

        const mapUrl = (lat, lng) => {
            const delta = 0.012;
            const bbox = `${lng - delta},${lat - delta},${lng + delta},${lat + delta}`;
            return 'https://www.openstreetmap.org/export/embed.html?bbox=' + encodeURIComponent(bbox)
                + '&layer=mapnik&marker=' + encodeURIComponent(lat + ',' + lng);
        };

        const fmt = (lat, lng) => Number(lat).toFixed(7) + ', ' + Number(lng).toFixed(7);

        btn.addEventListener('click', async () => {
            setLoading(true);
            try {
                const res = await fetch(endpoint, { headers: { Accept: 'application/json' } });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();

                if (!data.found) {
                    // Tetap tampilkan kondisi "belum tersedia".
                    location.reload();
                    return;
                }

                const set = (name, value) => {
                    const el = panel.querySelector('[data-field="' + name + '"]');
                    if (el) el.textContent = value;
                };

                set('formatted', data.formatted);
                set('human', data.human);
                set('accuracy', data.accuracy_meters != null ? '± ' + Number(data.accuracy_meters).toLocaleString('id-ID') + ' meter' : 'Tidak tersedia');
                set('coords', fmt(data.latitude, data.longitude));

                const humanEl = panel.querySelector('[data-field="human"]');
                humanEl?.classList.remove('text-danger-600', 'text-success-600');
                humanEl?.classList.add(data.is_stale ? 'text-danger-600' : 'text-success-600');

                const banner = panel.querySelector('[data-field="stale-banner"]');
                if (banner) {
                    banner.innerHTML = data.is_stale
                        ? '<div class="rounded-xl bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:bg-warning-400/10 dark:text-warning-300">Posisi ini sudah lebih dari 15 menit. Sales mungkin menutup aplikasi, menonaktifkan GPS, atau sedang kehilangan koneksi.</div>'
                        : '';
                }

                const map = panel.querySelector('[data-field="map"]');
                map?.setAttribute('src', mapUrl(data.latitude, data.longitude));

                const nav = panel.querySelector('[data-field="nav"]');
                nav?.setAttribute('href', 'https://www.google.com/maps/dir/?api=1&destination=' + data.latitude + ',' + data.longitude);
            } catch (_) {
                // Gagal fetch: cukup biarkan nilai lama.
            } finally {
                setLoading(false);
            }
        });
    })();
</script>
