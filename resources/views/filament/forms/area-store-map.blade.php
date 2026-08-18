@once
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@endonce

<div
    wire:ignore
    x-data="routeStorePicker({
        styleUrl: @js(config('maps.style_url')),
        latitude: @js(config('maps.default_latitude')),
        longitude: @js(config('maps.default_longitude')),
        zoom: @js(config('maps.default_zoom')),
        dataUrl: @js(route('internal.store-map-data')),
        initialSelected: @js(array_values($get('stores') ?? [])),
        showLine: false,
        setSelected: value => $wire.set('data.stores', value),
        getSelected: () => $wire.get('data.stores'),
    })"
    x-init="init()"
    class="space-y-3"
>
    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="relative min-w-0">
            <div x-ref="map" class="map-container h-[760px] min-h-[680px] w-full rounded-xl border"></div>
            <div class="absolute bottom-3 left-3 rounded-lg bg-white/95 px-3 py-2 text-xs shadow dark:bg-gray-900/95">
                Hijau: belum dipilih · Jingga bernomor: masuk area
            </div>
        </div>
        <aside class="max-h-[760px] min-h-[320px] overflow-y-auto rounded-xl border bg-white p-4 dark:bg-gray-900">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="font-semibold">Toko dalam Area</h3>
                <span class="rounded-full bg-primary-100 px-2 py-1 text-xs text-primary-700" x-text="`${selected.length} toko`"></span>
            </div>
            <div x-show="selected.length === 0" class="py-8 text-center text-sm text-gray-500">
                Klik marker toko pada peta.
            </div>
            <ol class="space-y-2">
                <template x-for="(store, index) in selectedStores()" :key="store.id">
                    <li class="flex items-start gap-2 rounded-lg border p-2">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-600 text-xs font-bold text-white" x-text="index + 1"></span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium" x-text="store.name"></div>
                            <div class="truncate text-xs text-gray-500" x-text="store.address"></div>
                        </div>
                        <button type="button" class="text-danger-600" x-on:click="remove(store.id)" title="Keluarkan dari area">×</button>
                    </li>
                </template>
            </ol>
        </aside>
    </div>
</div>
