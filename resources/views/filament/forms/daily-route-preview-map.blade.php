@once
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@endonce

<div
    wire:ignore
    x-data="dailyRoutePreview({
        styleUrl: @js(config('maps.style_url')),
        latitude: @js(config('maps.default_latitude')),
        longitude: @js(config('maps.default_longitude')),
        zoom: @js(config('maps.default_zoom')),
        dataUrl: @js(route('internal.store-map-data')),
        getAreaStores: () => $wire.get('data.area_store_ids') ?? [],
        getExtraStores: () => $wire.get('data.extra_store_ids') ?? [],
    })"
    x-init="init()"
    class="space-y-3"
>
    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="relative">
            <div x-ref="map" class="map-container h-[760px] min-h-[680px] w-full rounded-xl border"></div>
            <div class="absolute bottom-3 left-3 rounded-lg bg-white/95 px-3 py-2 text-xs shadow dark:bg-gray-900/95">
                Hijau: toko area · Ungu: tambahan toko tutup
            </div>
        </div>
        <aside class="max-h-[760px] min-h-[320px] overflow-y-auto rounded-xl border bg-white p-4 dark:bg-gray-900">
            <div class="mb-3">
                <h3 class="font-semibold">Ringkasan Rute</h3>
                <p class="text-xs text-gray-500">Urutan awal mengikuti daftar toko dalam area.</p>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div class="rounded-lg bg-success-50 p-3 dark:bg-success-950">
                    <div class="text-2xl font-bold text-success-700" x-text="areaIds.length"></div>
                    <div class="text-xs">Toko area</div>
                </div>
                <div class="rounded-lg bg-purple-50 p-3 dark:bg-purple-950">
                    <div class="text-2xl font-bold text-purple-700" x-text="extraIds.length"></div>
                    <div class="text-xs">Tambahan</div>
                </div>
            </div>
            <ol class="mt-3 space-y-2">
                <template x-for="(store, index) in selectedStores()" :key="store.id">
                    <li class="flex items-start gap-2 rounded-lg border p-2">
                        <span
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                            :class="store.isExtra ? 'bg-purple-600' : 'bg-success-600'"
                            x-text="index + 1"
                        ></span>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium" x-text="store.name"></div>
                            <div class="truncate text-xs text-gray-500" x-text="store.address"></div>
                        </div>
                    </li>
                </template>
            </ol>
        </aside>
    </div>
</div>
