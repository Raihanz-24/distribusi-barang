<x-filament-panels::page>
    @php($summary = $this->summary)

    <div class="space-y-5">
        <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-stone-900 via-amber-900 to-amber-600 p-5 text-white shadow-lg md:p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-xs font-bold uppercase tracking-[.18em] text-amber-200">Analisa cakupan toko</div>
                    <h2 class="mt-2 text-2xl font-black">Toko mana yang perlu dikunjungi?</h2>
                    <p class="mt-1 max-w-2xl text-sm text-amber-100">Pantau kunjungan dalam periode pilihan. Sales melihat aktivitasnya sendiri; Admin dapat membandingkan seluruh Sales.</p>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <div class="rounded-xl bg-white/10 px-4 py-3"><div class="text-2xl font-black">{{ $summary['total'] }}</div><div class="text-[11px] text-amber-100">Toko aktif</div></div>
                    <div class="rounded-xl bg-green-500/20 px-4 py-3"><div class="text-2xl font-black">{{ $summary['visited'] }}</div><div class="text-[11px] text-green-100">Dikunjungi</div></div>
                    <div class="rounded-xl bg-red-500/20 px-4 py-3"><div class="text-2xl font-black">{{ $summary['unvisited'] }}</div><div class="text-[11px] text-red-100">Belum</div></div>
                    <div class="rounded-xl bg-white/10 px-4 py-3"><div class="text-2xl font-black">{{ $summary['coverage'] }}%</div><div class="text-[11px] text-amber-100">Cakupan</div></div>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <label class="text-sm"><span class="mb-1 block font-semibold">Cari toko</span><input wire:model.live.debounce.350ms="search" class="fi-input block w-full" placeholder="Nama, kode, alamat, desa…"></label>
                <label class="text-sm"><span class="mb-1 block font-semibold">Periode</span><select wire:model.live="period" class="fi-input block w-full"><option value="today">Hari ini</option><option value="this_week">Minggu ini</option><option value="this_month">Bulan ini</option><option value="last_month">Bulan lalu</option></select></label>
                <label class="text-sm"><span class="mb-1 block font-semibold">Status</span><select wire:model.live="status" class="fi-input block w-full"><option value="all">Semua toko</option><option value="visited">Sudah dikunjungi</option><option value="unvisited">Belum dikunjungi</option><option value="never">Belum pernah dikunjungi</option></select></label>
                @if (auth()->user()->isAdmin())
                    <label class="text-sm"><span class="mb-1 block font-semibold">Sales</span><select wire:model.live="salesId" class="fi-input block w-full"><option value="">Semua Sales</option>@foreach ($this->salesOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 text-sm text-gray-500 dark:border-white/10">Menampilkan {{ $this->rows->count() }} toko</div>
            <div class="grid gap-3 p-3 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($this->rows as $store)
                    @php($periodVisit = $store->visits->first())
                    <article class="rounded-xl border p-4 {{ $periodVisit ? 'border-green-500/30 bg-green-500/5' : 'border-gray-200 dark:border-white/10' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><div class="truncate font-bold text-gray-950 dark:text-white">{{ $store->name }}</div><div class="mt-1 text-xs text-gray-500">{{ $store->code }} · {{ $store->district ?: 'Area belum diisi' }}</div></div>
                            <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold {{ $periodVisit ? 'bg-green-500/15 text-green-700 dark:text-green-300' : 'bg-red-500/10 text-red-600 dark:text-red-300' }}">{{ $periodVisit ? 'SUDAH' : 'BELUM' }}</span>
                        </div>
                        <p class="mt-3 line-clamp-2 text-xs text-gray-500">{{ $store->address }}</p>
                        <div class="mt-3 rounded-lg bg-gray-100 p-3 text-xs dark:bg-white/5">
                            @if ($periodVisit)
                                <strong>{{ $periodVisit->visit_status->label() }}</strong> · {{ $periodVisit->arrived_at->translatedFormat('d M Y, H:i') }}
                                <div class="mt-1 text-gray-500">oleh {{ $periodVisit->sales?->name ?? '-' }}</div>
                            @elseif ($store->latestVisit)
                                <strong>Terakhir {{ $store->latestVisit->arrived_at->diffForHumans() }}</strong>
                                <div class="mt-1 text-gray-500">{{ $store->latestVisit->sales?->name ?? '-' }} · {{ $store->latestVisit->visit_status->label() }}</div>
                            @else
                                <strong class="text-amber-700 dark:text-amber-300">Belum pernah dikunjungi</strong>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="col-span-full py-12 text-center text-sm text-gray-500">Tidak ada toko yang sesuai dengan filter.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
