<x-filament-panels::page>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <div
        x-data="storeMap(@js($mapConfig))"
        x-init="init()"
        class="space-y-4"
    >
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <label class="space-y-1">
                <span class="text-xs font-medium text-gray-500">Cari toko</span>
                <input x-model.debounce.400ms="search" x-on:input="loadStores()" class="fi-input w-full" placeholder="Nama atau kode toko...">
            </label>
            <label class="space-y-1">
                <span class="text-xs font-medium text-gray-500">Area</span>
                <select x-model="areaId" x-on:change="loadStores()" class="fi-select-input w-full">
                    <option value="">Semua area</option>
                    <template x-for="[id, name] in Object.entries(config.areas)" :key="id">
                        <option :value="id" x-text="name"></option>
                    </template>
                </select>
            </label>
            <label class="space-y-1">
                <span class="text-xs font-medium text-gray-500">Verifikasi</span>
            <select x-model="verificationStatus" x-on:change="loadStores()" class="fi-select-input">
                <option value="">Semua verifikasi</option>
                <option value="pending">Menunggu</option>
                <option value="verified">Terverifikasi</option>
                <option value="rejected">Ditolak</option>
                <option value="duplicate">Duplikat</option>
            </select>
            </label>
            <label class="space-y-1">
                <span class="text-xs font-medium text-gray-500">Status toko</span>
                <select x-model="activeStatus" x-on:change="loadStores()" class="fi-select-input w-full">
                    <option value="">Semua status</option>
                    <option value="1">Aktif</option>
                    <option value="0">Tidak aktif</option>
                </select>
            </label>
            <label class="flex items-end gap-2 rounded-xl border p-3">
                <input type="checkbox" x-model="needsReschedule" x-on:change="loadStores()" class="fi-checkbox-input">
                <span class="text-sm">Perlu dijadwalkan ulang</span>
            </label>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <template x-for="card in statisticCards()" :key="card.label">
                <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900">
                    <div class="text-2xl font-bold" x-text="card.value"></div>
                    <div class="text-xs text-gray-500" x-text="card.label"></div>
                </div>
            </template>
            <button type="button" class="fi-btn fi-btn-color-primary min-h-16" x-on:click="fitBounds()">Fit semua marker</button>
        </div>

        <div class="flex flex-wrap gap-4 rounded-xl bg-white p-3 text-xs shadow-sm dark:bg-gray-900">
            <span class="font-medium">Legenda:</span>
            <span class="flex items-center gap-1"><i class="h-3 w-3 rounded-full bg-green-700"></i> Terverifikasi</span>
            <span class="flex items-center gap-1"><i class="h-3 w-3 rounded-full bg-amber-600"></i> Menunggu verifikasi</span>
            <span class="flex items-center gap-1"><i class="h-3 w-3 rounded-full bg-red-600"></i> Ditolak/duplikat</span>
            <span class="flex items-center gap-1"><i class="h-3 w-3 rounded-full bg-purple-600"></i> Perlu dijadwalkan ulang</span>
        </div>
        <div x-ref="map" class="map-container h-[68vh] min-h-[480px] rounded-xl border"></div>
    </div>
</x-filament-panels::page>
