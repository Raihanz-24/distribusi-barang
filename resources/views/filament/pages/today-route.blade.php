<x-filament-panels::page>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if (! $this->route)
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-500/10 text-2xl">🗺️</div>
            <h2 class="text-lg font-bold text-gray-950 dark:text-white">Menyiapkan rute hari ini…</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-gray-500">Muat ulang halaman jika rute lapangan belum muncul.</p>
        </div>
    @else
        @php
            $routeStores = $this->route->routeStores->sortBy(fn ($item) => $item->optimized_order ?? $item->planned_order ?? 999);
            $stockSummary = $this->salesStockSummary;
            $salesStockBalances = $this->salesStockBalances;
            $finishedCount = $routeStores->filter(fn ($item) => $item->latestVisit)->count();
            $remainingCount = $routeStores->count() - $finishedCount;
            $progress = $routeStores->count() ? round(($finishedCount / $routeStores->count()) * 100) : 0;
        @endphp

        <div
            class="space-y-5"
            x-data="salesRouteWorkspace({
                styleUrl: @js(config('maps.style_url')),
                latitude: @js((float) ($this->route->default_start_latitude ?? -7.75)),
                longitude: @js((float) ($this->route->default_start_longitude ?? 113.42)),
                zoom: 12,
                features: @js($this->routeMapData['features']),
                line: @js($this->routeMapData['line']),
                selectStore: (id) => $wire.selectStore(id),
            })"
            x-on:store-selected.window="$nextTick(() => document.getElementById('transaction-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
        >
            <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-amber-600 via-amber-700 to-stone-900 p-5 text-white shadow-lg md:p-6">
                <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="mb-2 text-xs font-semibold uppercase tracking-[.18em] text-amber-200">Rute fleksibel hari ini</div>
                        <h2 class="text-2xl font-bold">Rencana Lapangan Saya</h2>
                        <p class="mt-1 text-sm text-amber-100">{{ $this->route->route_date->translatedFormat('l, d F Y') }} · {{ $routeStores->count() }} toko dipilih · Anda bebas menambah toko saat di lapangan</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center md:min-w-80">
                        <div class="rounded-xl bg-white/10 px-3 py-3 backdrop-blur"><div class="text-xl font-bold">{{ $finishedCount }}</div><div class="text-[11px] text-amber-100">Selesai</div></div>
                        <div class="rounded-xl bg-white/10 px-3 py-3 backdrop-blur"><div class="text-xl font-bold">{{ $remainingCount }}</div><div class="text-[11px] text-amber-100">Tersisa</div></div>
                        <div class="rounded-xl bg-white/10 px-3 py-3 backdrop-blur"><div class="text-xl font-bold">{{ $progress }}%</div><div class="text-[11px] text-amber-100">Progres</div></div>
                    </div>
                </div>
                <div class="mt-5 h-2 overflow-hidden rounded-full bg-black/25"><div class="h-full rounded-full bg-amber-300 transition-all" style="width: {{ $progress }}%"></div></div>
            </section>

            @if ($stockSummary['active'])
                <section class="rounded-2xl border border-green-500/30 bg-green-500/10 p-4 shadow-sm">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wider text-green-700 dark:text-green-300">Stok Sales aktif · {{ $stockSummary['transfer_number'] }}</div>
                                <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">Ringkasan otomatis diperbarui setiap transaksi toko berhasil disimpan.</div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 sm:min-w-80">
                                <div class="rounded-xl bg-white p-3 shadow-sm dark:bg-gray-900">
                                    <div class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Sisa stok dibawa</div>
                                    <div class="mt-1 text-xl font-black text-green-700 dark:text-green-300">{{ number_format($stockSummary['total_pieces']) }}</div>
                                    <div class="text-[10px] text-gray-500">bungkus</div>
                                </div>
                                <div class="rounded-xl bg-white p-3 shadow-sm dark:bg-gray-900">
                                    <div class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Hasil penagihan</div>
                                    <div class="mt-1 text-xl font-black text-amber-700 dark:text-amber-300">Rp {{ number_format($stockSummary['total_collection'], 0, ',', '.') }}</div>
                                    <div class="mt-1 text-[10px] leading-4 text-gray-500">Stok laku Rp {{ number_format($stockSummary['current_sales_collection'], 0, ',', '.') }} · Hutang lama Rp {{ number_format($stockSummary['old_debt_collection'], 0, ',', '.') }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="border-t border-green-600/15 pt-3">
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2"><span class="text-[10px] font-bold uppercase tracking-wide text-green-800/70 dark:text-green-300/70">Sisa stok per produk</span><span class="text-[10px] text-gray-500">Hasil penagihan dari {{ number_format($stockSummary['transaction_count']) }} transaksi; titipan baru tidak dihitung sebagai uang.</span></div>
                            <div class="flex flex-wrap gap-2">
                                @forelse ($stockSummary['items'] as $item)
                                    <span class="rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-green-800 shadow-sm dark:bg-gray-900 dark:text-green-300">{{ $item['name'] }}: {{ number_format($item['quantity']) }} bungkus</span>
                                @empty
                                    <span class="text-xs font-semibold text-amber-700 dark:text-amber-300">Saldo stok kosong; hanya penarikan produk atau pembayaran hutang yang dapat diproses.</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </section>
            @else
                <section class="rounded-2xl border-2 border-red-500/40 bg-red-500/10 p-5 shadow-sm">
                    <h3 class="font-bold text-red-800 dark:text-red-300">Transaksi toko dinonaktifkan</h3>
                    <p class="mt-1 text-sm leading-6 text-red-700 dark:text-red-400">Tidak ada distribusi stok aktif yang sedang Anda bawa. Stok mungkin belum diterima, sudah diserahkan, atau pengembalian ke Admin sedang menunggu persetujuan.</p>
                </section>
            @endif

            <section class="overflow-hidden rounded-2xl border-2 border-amber-500/30 bg-white shadow-sm dark:bg-gray-900">
                <div class="border-b border-gray-200 bg-amber-500/5 p-4 dark:border-white/10">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                        <label class="min-w-0 flex-1 text-sm">
                            <span class="mb-1.5 block font-bold text-gray-950 dark:text-white">🔎 Cari toko untuk dikunjungi</span>
                            <input wire:model.live.debounce.350ms="storeSearch" class="fi-input block min-h-12 w-full" placeholder="Ketik nama toko, kode, alamat, desa atau kecamatan…">
                        </label>
                        <label class="text-sm lg:w-56">
                            <span class="mb-1.5 block font-semibold">Prioritas pencarian</span>
                            <select wire:model.live="storeSearchFilter" class="fi-input block min-h-12 w-full">
                                <option value="all">Semua toko</option>
                                <option value="not_this_month">Belum dikunjungi bulan ini</option>
                                <option value="never">Belum pernah dikunjungi</option>
                            </select>
                        </label>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Minimal 2 huruf. Riwayat terakhir dihitung dari kunjungan seluruh Sales.</p>
                </div>
                @if (mb_strlen(trim($storeSearch)) >= 2)
                    <div class="grid max-h-[460px] gap-3 overflow-y-auto p-3 md:grid-cols-2 xl:grid-cols-3">
                        @forelse ($this->storeSearchResults as $store)
                            <article wire:key="search-store-{{ $store['id'] }}" class="rounded-xl border p-4 {{ $store['visited_today'] ? 'border-green-500/30 bg-green-500/5' : 'border-gray-200 dark:border-white/10' }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0"><div class="truncate font-bold">{{ $store['name'] }}</div><div class="mt-1 text-xs text-gray-500">{{ $store['code'] }}</div></div>
                                    @if ($store['in_route'])<span class="rounded-full bg-amber-500/15 px-2 py-1 text-[10px] font-bold text-amber-700 dark:text-amber-300">DI RUTE</span>@endif
                                </div>
                                <p class="mt-2 line-clamp-2 text-xs text-gray-500">{{ $store['address'] }}</p>
                                <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
                                    <span class="rounded-lg bg-gray-100 px-2 py-1 font-semibold dark:bg-white/5">{{ $store['last_visit_label'] }}</span>
                                    @if ($store['last_visit_sales'])<span class="rounded-lg bg-gray-100 px-2 py-1 text-gray-500 dark:bg-white/5">oleh {{ $store['last_visit_sales'] }}</span>@endif
                                    @if ($store['distance_meters'] !== null)<span class="rounded-lg bg-green-500/10 px-2 py-1 font-semibold text-green-700 dark:text-green-300">{{ $store['distance_meters'] < 1000 ? number_format($store['distance_meters']).' m' : number_format($store['distance_meters'] / 1000, 1, ',', '.').' km' }}</span>@endif
                                </div>
                                <div class="mt-3 flex gap-2">
                                    @if (! $store['in_route'])
                                        <button type="button" wire:click="addStoreToRoute({{ $store['id'] }})" class="min-h-10 flex-1 rounded-xl border border-amber-600 px-3 text-xs font-bold text-amber-700 hover:bg-amber-500/10 dark:text-amber-300">+ Tambah ke rute</button>
                                    @endif
                                    <button type="button" wire:click="selectStore({{ $store['id'] }})" @disabled($store['visited_today'] || ! $stockSummary['active']) class="min-h-10 flex-1 rounded-xl bg-amber-600 px-3 text-xs font-bold text-white hover:bg-amber-700 disabled:opacity-40">Buat transaksi</button>
                                </div>
                            </article>
                        @empty
                            <div class="col-span-full py-10 text-center text-sm text-gray-500">Toko tidak ditemukan. Coba kata kunci atau filter lain.</div>
                        @endforelse
                    </div>
                @endif
            </section>

            <section class="grid gap-4 xl:grid-cols-[minmax(0,1.65fr)_minmax(330px,.85fr)]">
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex flex-col gap-3 border-b border-gray-200 p-4 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between">
                        <div><h3 class="font-bold text-gray-950 dark:text-white">Peta Rute Hari Ini</h3><p class="text-xs text-gray-500">Titik jingga dapat dipilih · titik abu-abu sudah selesai</p></div>
                        <button type="button" x-on:click="locate($wire)" x-bind:disabled="locating" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-green-600 px-4 text-sm font-semibold text-white transition hover:bg-green-700 disabled:opacity-60">
                            <span x-text="locating ? 'Mencari GPS…' : '⌖ Lokasi Saya'"></span>
                        </button>
                    </div>
                    <div x-ref="map" wire:ignore class="h-[430px] w-full bg-stone-100 md:h-[570px] dark:bg-gray-950"></div>
                    <div class="border-t border-gray-200 px-4 py-3 text-xs text-gray-500 dark:border-white/10" x-show="message" x-text="message"></div>
                </div>

                <aside class="flex max-h-[680px] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="border-b border-gray-200 p-4 dark:border-white/10">
                        <h3 class="font-bold text-gray-950 dark:text-white">Rute Saya</h3>
                        <p class="mt-1 text-xs text-gray-500">Atur urutan atau hapus toko yang belum diproses.</p>
                    </div>
                    <div class="space-y-2 overflow-y-auto p-3">
                        @foreach ($routeStores as $routeStore)
                            @php($visit = $routeStore->latestVisit)
                            <div
                                wire:key="route-store-{{ $routeStore->id }}"
                                class="flex w-full items-start gap-3 rounded-xl border p-3 text-left transition {{ $visit ? 'cursor-not-allowed border-transparent bg-gray-100 opacity-55 dark:bg-white/5' : ($selectedStoreId === $routeStore->store_id ? 'border-amber-500 bg-amber-50 ring-2 ring-amber-500/15 dark:bg-amber-500/10' : 'border-gray-200 hover:border-amber-400 hover:bg-amber-50/60 dark:border-white/10 dark:hover:bg-white/5') }}"
                            >
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $visit ? 'bg-gray-500 text-white' : 'bg-amber-600 text-white' }} text-xs font-bold">{{ $routeStore->optimized_order ?? $routeStore->planned_order ?? $loop->iteration }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $routeStore->store->name }}</span>
                                    <span class="mt-1 block line-clamp-2 text-xs text-gray-500">{{ $routeStore->store->address }}</span>
                                    @if ($visit)
                                        <span class="mt-2 inline-flex rounded-full bg-gray-200 px-2 py-1 text-[10px] font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $visit->visit_status->label() }} · {{ $visit->completed_at?->format('H:i') }}</span>
                                    @else
                                        <span class="mt-2 inline-flex rounded-full bg-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ $routeStore->store->latestVisit ? 'Terakhir '.$routeStore->store->latestVisit->arrived_at->diffForHumans() : 'Belum pernah dikunjungi' }}</span>
                                    @endif
                                </span>
                                @if (! $visit)
                                    <span class="grid shrink-0 gap-1">
                                        <button type="button" wire:click="selectStore({{ $routeStore->store_id }})" @disabled(! $stockSummary['active']) title="Buat transaksi" class="rounded-lg bg-amber-600 px-2 py-1.5 text-xs font-bold text-white disabled:opacity-40">Pilih</button>
                                        <span class="flex gap-1"><button type="button" wire:click="moveRouteStore({{ $routeStore->id }}, 'up')" title="Naikkan" class="rounded bg-gray-100 px-2 py-1 text-xs dark:bg-white/10">↑</button><button type="button" wire:click="moveRouteStore({{ $routeStore->id }}, 'down')" title="Turunkan" class="rounded bg-gray-100 px-2 py-1 text-xs dark:bg-white/10">↓</button><button type="button" wire:click="removeStoreFromRoute({{ $routeStore->id }})" wire:confirm="Hapus toko ini dari rute hari ini?" title="Hapus dari rute" class="rounded bg-red-500/10 px-2 py-1 text-xs text-red-600">×</button></span>
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </aside>
            </section>

            @if ($currentLatitude !== null)
                <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="mb-3 flex items-center justify-between"><div><h3 class="font-bold text-gray-950 dark:text-white">Toko di Sekitar Saya</h3><p class="text-xs text-gray-500">Diurutkan berdasarkan jarak GPS saat ini.</p></div><button type="button" wire:click="recalculateRoute" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold dark:border-white/10">Hitung ulang rute</button></div>
                    <div class="flex gap-3 overflow-x-auto pb-1">
                        @forelse ($nearbyStores as $store)
                            <button type="button" wire:click="selectStore({{ $store['id'] }})" @disabled($store['visited'] || ! $stockSummary['active']) class="min-w-64 rounded-xl border p-3 text-left {{ $store['visited'] ? 'cursor-not-allowed bg-gray-100 opacity-50 dark:bg-white/5' : 'border-gray-200 hover:border-amber-500 disabled:opacity-40 dark:border-white/10' }}">
                                <span class="block truncate text-sm font-bold">{{ $store['name'] }}</span><span class="mt-1 block text-xs text-gray-500">{{ number_format($store['distance_meters']) }} meter</span><span class="mt-2 block text-[10px] font-semibold {{ $store['in_today_route'] ? 'text-green-600' : 'text-amber-600' }}">{{ $store['visited'] ? 'Sudah selesai' : ($store['in_today_route'] ? 'Dalam rute · '.$store['last_visit_label'] : '+ Otomatis masuk rute · '.$store['last_visit_label']) }}</span>
                            </button>
                        @empty
                            <p class="py-4 text-sm text-gray-500">Tidak ada toko dalam radius pencarian.</p>
                        @endforelse
                    </div>
                </section>
            @endif

            @if ($this->selectedStore)
                @php($transactionSummary = $this->transactionSummary($this->selectedStore->id))
                <section id="transaction-panel" class="scroll-mt-5 overflow-hidden rounded-2xl border border-amber-500/40 bg-white shadow-lg dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-4 bg-amber-500/10 p-5">
                        <div><div class="text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Transaksi toko</div><h3 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $this->selectedStore->name }}</h3><p class="mt-1 text-sm text-gray-500">{{ $this->selectedStore->address }}</p></div>
                        <button type="button" wire:click="$set('selectedStoreId', null)" class="rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-black/5">Tutup</button>
                    </div>
                    <div class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_260px]">
                        <div class="space-y-3">
                            @foreach ($this->products as $product)
                                @php($preview = $this->transactionPreview($this->selectedStore->id, $product->id))
                                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10" wire:key="product-{{ $this->selectedStore->id }}-{{ $product->id }}">
                                    <div class="mb-3 flex items-center justify-between gap-3"><span class="font-bold">{{ $product->name }}</span><span class="rounded-full bg-green-500/10 px-2.5 py-1 text-[11px] font-bold text-green-700 dark:text-green-300">Stok Sales: {{ number_format($salesStockBalances[$product->id] ?? 0) }}</span></div>
                                    @if (! empty($preview['price_missing']))
                                        <div class="mb-3 rounded-lg border border-red-300 bg-red-50 p-2 text-xs font-semibold text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-400">
                                            ⚠️ {{ $preview['price_missing'] }}
                                        </div>
                                    @endif
                                    <div class="grid gap-3 sm:grid-cols-3">
                                        <label class="text-sm"><span class="mb-1.5 block text-gray-500">Produk laku (bungkus)</span><input type="number" min="0" inputmode="numeric" wire:model.live.debounce.350ms="transactions.{{ $this->selectedStore->id }}.products.{{ $product->id }}.sold" class="fi-input block w-full" placeholder="0"></label>
                                        <label class="text-sm"><span class="mb-1.5 block text-gray-500">Titip baru (bungkus)</span><input type="number" min="0" max="{{ $salesStockBalances[$product->id] ?? 0 }}" inputmode="numeric" wire:model="transactions.{{ $this->selectedStore->id }}.products.{{ $product->id }}.deposited" class="fi-input block w-full" placeholder="0"><span class="mt-1 block text-[10px] text-gray-400">Maksimal stok yang sedang dibawa: {{ number_format($salesStockBalances[$product->id] ?? 0) }}</span></label>
                                        <label class="text-sm"><span class="mb-1.5 block text-gray-500">Tarik produk (bungkus)</span><input type="number" min="0" inputmode="numeric" wire:model="transactions.{{ $this->selectedStore->id }}.products.{{ $product->id }}.withdrawn" class="fi-input block w-full" placeholder="0"><span class="mt-1 block text-[10px] text-gray-400">{{ $product->pieces_per_renteng }} bungkus = 1 renteng</span></label>
                                    </div>
                                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs"><div class="rounded-lg bg-green-500/10 p-2 text-green-700 dark:text-green-400">Fee toko<br><strong class="text-sm">Rp {{ number_format($preview['store_fee'], 0, ',', '.') }}</strong></div><div class="rounded-lg bg-amber-500/10 p-2 text-amber-700 dark:text-amber-400">Wajib bayar<br><strong class="text-sm">Rp {{ number_format($preview['amount_due'], 0, ',', '.') }}</strong></div></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="space-y-3">
                            <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4">
                                <div class="flex items-center justify-between gap-3"><span class="text-xs font-semibold text-gray-500">Wajib dibayar</span><strong class="text-lg text-amber-700 dark:text-amber-400">Rp {{ number_format($transactionSummary['amount_due'], 0, ',', '.') }}</strong></div>
                                <label class="mt-4 block text-sm"><span class="mb-1.5 block font-semibold">Bayar transaksi hari ini</span><div class="flex overflow-hidden rounded-xl border border-gray-300 bg-white focus-within:border-amber-500 dark:border-white/10 dark:bg-gray-950"><span class="flex items-center border-r border-gray-200 px-3 text-sm text-gray-500 dark:border-white/10">Rp</span><input type="number" min="0" max="{{ $transactionSummary['amount_due'] }}" step="1000" inputmode="numeric" wire:model.live.debounce.250ms="transactions.{{ $this->selectedStore->id }}.payment_amount" class="min-w-0 flex-1 border-0 bg-transparent px-3 py-3 text-base font-bold outline-none" placeholder="0"></div></label>
                                <button type="button" wire:click="setFullPayment({{ $this->selectedStore->id }})" class="mt-2 text-xs font-bold text-amber-700 hover:underline dark:text-amber-400">Isi sesuai wajib bayar</button>
                                @if ($transactionSummary['opening_debt'] > 0)
                                    <div class="mt-4 border-t border-amber-500/20 pt-4">
                                        <div class="flex items-center justify-between text-xs text-red-600 dark:text-red-400"><span>Hutang lama toko</span><strong>Rp {{ number_format($transactionSummary['opening_debt'], 0, ',', '.') }}</strong></div>
                                        <label class="mt-2 block text-sm"><span class="mb-1.5 block font-semibold">Bayar hutang lama</span><div class="flex overflow-hidden rounded-xl border border-gray-300 bg-white focus-within:border-amber-500 dark:border-white/10 dark:bg-gray-950"><span class="flex items-center border-r border-gray-200 px-3 text-sm text-gray-500 dark:border-white/10">Rp</span><input type="number" min="0" max="{{ $transactionSummary['opening_debt'] }}" step="1000" inputmode="numeric" wire:model.live.debounce.250ms="transactions.{{ $this->selectedStore->id }}.debt_payment_amount" class="min-w-0 flex-1 border-0 bg-transparent px-3 py-3 text-base font-bold outline-none" placeholder="0"></div></label>
                                        <button type="button" wire:click="setFullDebtPayment({{ $this->selectedStore->id }})" class="mt-2 text-xs font-bold text-amber-700 hover:underline dark:text-amber-400">Bayar seluruh hutang lama</button>
                                    </div>
                                @endif
                                <div class="mt-4 space-y-2 rounded-lg {{ $transactionSummary['closing_debt'] > 0 ? 'bg-red-500/10 text-red-700 dark:text-red-400' : 'bg-green-500/10 text-green-700 dark:text-green-400' }} p-3"><div class="flex items-center justify-between"><span class="text-xs font-semibold">Total uang diterima</span><strong>Rp {{ number_format($transactionSummary['total_paid'], 0, ',', '.') }}</strong></div><div class="flex items-center justify-between border-t border-current/10 pt-2"><span class="text-xs font-semibold">Sisa seluruh hutang</span><strong>Rp {{ number_format($transactionSummary['closing_debt'], 0, ',', '.') }}</strong></div></div>
                            </div>
                            <div class="rounded-xl bg-gray-50 p-4 text-sm dark:bg-white/5">
                                <strong>Validasi kunjungan</strong>
                                @if ($locationNote)
                                    <div class="mt-2 rounded-lg border border-amber-300 bg-amber-50 p-2 text-xs font-semibold text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                                        ⚠️ {{ $locationNote }}
                                    </div>
                                @else
                                    <p class="mt-2 text-xs leading-5 text-gray-500">GPS wajib aktif agar transaksi tercatat pada toko dan jadwal yang benar.</p>
                                @endif
                                <div class="mt-3 text-xs font-semibold {{ $currentLatitude !== null ? 'text-green-600' : 'text-red-500' }}">
                                    {{ $currentLatitude !== null ? '✓ Lokasi sudah siap' : 'Lokasi belum diambil' }}
                                </div>
                                @if ($currentLatitude === null)
                                    <button type="button" x-on:click="useStoreLocation($wire, {{ $this->selectedStore->id }})" class="mt-3 w-full rounded-lg border border-amber-500/60 px-3 py-2 text-xs font-bold text-amber-700 hover:bg-amber-500/10 dark:text-amber-400">
                                        📍 Pakai koordinat toko
                                    </button>
                                    <p class="mt-2 text-[10px] leading-4 text-gray-500">Gunakan bila GPS perangkat tidak bisa dipakai (mis. akses non-HTTPS). Pilihan ini dicatat pada riwayat kunjungan.</p>
                                @endif
                            </div>
                            <button type="button" wire:click="saveTransaction({{ $this->selectedStore->id }})" wire:loading.attr="disabled" @disabled($currentLatitude === null) class="min-h-12 w-full rounded-xl bg-amber-600 px-4 font-bold text-white transition hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-40">Simpan & Selesaikan</button>
                            <div class="rounded-xl border border-dashed border-gray-300 p-3 text-center text-xs text-gray-500 dark:border-white/10">🧾 Tombol cetak struk muncul setelah transaksi berhasil disimpan.</div>
                            <div class="grid grid-cols-1 gap-2">
                                <button type="button" x-on:click="visit($wire, {{ $this->selectedStore->id }}, 'closed', true)" @disabled($currentLatitude === null) class="min-h-10 rounded-xl border border-gray-300 text-sm font-semibold disabled:opacity-40 dark:border-white/10">Toko tutup</button>
                                <button type="button" x-on:click="visit($wire, {{ $this->selectedStore->id }}, 'postponed', true)" @disabled($currentLatitude === null) class="min-h-10 rounded-xl border border-gray-300 text-sm font-semibold disabled:opacity-40 dark:border-white/10">Ditunda</button>
                                <button type="button" x-on:click="visit($wire, {{ $this->selectedStore->id }}, 'not_found', true)" @disabled($currentLatitude === null) class="min-h-10 rounded-xl border border-gray-300 text-sm font-semibold disabled:opacity-40 dark:border-white/10">Tidak ditemukan</button>
                            </div>
                        </div>
                    </div>
                </section>
            @endif

            {{-- Panel cetak: tampil setiap kali ada transaksi selesai pada hari ini,
                 sehingga struk transaksi mana pun dapat langsung dicetak. --}}
            @if (! empty($this->completedVisits))
                <section x-data="pancorPrinter" class="flex flex-col gap-3 rounded-2xl border-2 border-green-500/40 bg-green-500/10 p-5 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-green-800 dark:text-green-300">✓ Transaksi selesai — cetak struk</h3>
                            <p class="text-sm text-green-700/80 dark:text-green-400">Pilih transaksi lalu cetak langsung ke printer thermal.</p>
                            <p x-show="status" x-text="status" class="mt-1 text-xs font-semibold text-green-800 dark:text-green-300"></p>
                            <p x-show="lastError" x-text="lastError" class="mt-1 text-xs font-semibold text-red-700 dark:text-red-400"></p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-green-800/70 dark:text-green-300/70">Jalur cetak</span>
                        <select x-model="channel" x-on:change="setChannel(channel)"
                            class="min-h-10 rounded-xl border border-green-700/30 bg-white px-3 text-sm font-semibold text-green-900 dark:bg-gray-900 dark:text-green-200">
                            <option value="auto">Otomatis</option>
                            <option value="agent">Print agent (Windows)</option>
                            <option value="usb">USB</option>
                            <option value="bluetooth">Bluetooth (BLE)</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        @foreach ($this->completedVisits as $visit)
                            <div wire:key="visit-{{ $visit['id'] }}"
                                class="flex flex-col gap-2 rounded-xl border p-3 sm:flex-row sm:items-center sm:justify-between {{ $lastVisitId === $visit['id'] ? 'border-green-600 bg-white dark:bg-gray-900' : 'border-green-700/20 bg-white/60 dark:bg-gray-900/60' }}">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-bold text-gray-900 dark:text-white">
                                        {{ $visit['store'] }}
                                        @if ($lastVisitId === $visit['id'])
                                            <span class="ml-1 rounded bg-green-600 px-1.5 py-0.5 text-[10px] font-bold text-white">BARU</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $visit['time'] }} · Rp {{ number_format($visit['amount_due'], 0, ',', '.') }}
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" x-on:click="print('{{ $visit['escpos_url'] }}')" x-bind:disabled="busy"
                                        class="inline-flex min-h-10 items-center justify-center rounded-xl bg-green-700 px-4 text-sm font-bold text-white hover:bg-green-800 disabled:opacity-50">
                                        🖨️ Cetak
                                    </button>
                                    <button type="button" x-on:click="print('{{ $visit['escpos_url'] }}', 'usb')" x-bind:disabled="busy"
                                        class="inline-flex min-h-10 items-center justify-center rounded-xl bg-green-700/10 px-3 text-xs font-semibold text-green-800 hover:bg-green-700/20 disabled:opacity-50 dark:text-green-300">
                                        🔌 USB
                                    </button>
                                    <button type="button" x-on:click="print('{{ $visit['escpos_url'] }}', 'bluetooth')" x-bind:disabled="busy"
                                        class="inline-flex min-h-10 items-center justify-center rounded-xl bg-green-700/10 px-3 text-xs font-semibold text-green-800 hover:bg-green-700/20 disabled:opacity-50 dark:text-green-300">
                                        📶 Bluetooth
                                    </button>
                                    <a href="{{ $visit['receipt_url'] }}" target="_blank"
                                        class="inline-flex min-h-10 items-center justify-center rounded-xl border border-green-700/30 px-3 text-xs font-semibold text-green-800 hover:bg-green-700/10 dark:text-green-300">
                                        Lihat
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <p class="text-xs text-green-800/70 dark:text-green-300/70">
                        Bluetooth hanya untuk printer <strong>BLE</strong>. Jika printer tidak muncul di dialog,
                        berarti memakai Bluetooth Classic (SPP) — gunakan kabel USB atau print agent.
                    </p>
                </section>
            @endif

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 p-4 dark:border-white/10"><h3 class="font-bold">Monitoring Kunjungan</h3><p class="text-xs text-gray-500">Ringkasan hasil kerja untuk seluruh toko pada jadwal hari ini.</p></div>
                <div class="overflow-x-auto"><table class="w-full min-w-[820px] text-left text-sm"><thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5"><tr><th class="px-4 py-3">Urutan</th><th class="px-4 py-3">Toko</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Wajib bayar</th><th class="px-4 py-3">Dibayar</th><th class="px-4 py-3">Hutang</th><th class="px-4 py-3">Waktu</th><th class="px-4 py-3">Struk</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($routeStores as $routeStore)
                        @php($visit = $routeStore->latestVisit)
                        <tr class="{{ $visit ? 'bg-gray-50/60 text-gray-500 dark:bg-white/[.02]' : '' }}"><td class="px-4 py-3 font-bold">{{ $routeStore->optimized_order ?? $routeStore->planned_order ?? $loop->iteration }}</td><td class="px-4 py-3"><div class="font-semibold text-gray-950 dark:text-white">{{ $routeStore->store->name }}</div><div class="max-w-sm truncate text-xs text-gray-500">{{ $routeStore->store->address }}</div></td><td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $visit ? 'bg-green-500/10 text-green-700 dark:text-green-400' : 'bg-amber-500/10 text-amber-700 dark:text-amber-400' }}">{{ $visit?->visit_status->label() ?? 'Belum dikunjungi' }}</span></td><td class="px-4 py-3">{{ $visit?->items->isNotEmpty() ? 'Rp '.number_format($visit->amount_due, 0, ',', '.') : '—' }}</td><td class="px-4 py-3 font-semibold text-green-600">{{ $visit?->items->isNotEmpty() ? 'Rp '.number_format($visit->amount_paid, 0, ',', '.') : '—' }}</td><td class="px-4 py-3 font-semibold {{ ($visit?->debt_amount ?? 0) > 0 ? 'text-red-600' : '' }}">{{ $visit?->items->isNotEmpty() ? 'Rp '.number_format($visit->debt_amount, 0, ',', '.') : '—' }}</td><td class="px-4 py-3">{{ $visit?->completed_at?->format('H:i') ?? '—' }}</td><td class="px-4 py-3">@if ($visit?->items->isNotEmpty())<a target="_blank" href="{{ route('sales.receipts.show', $visit) }}" class="inline-flex rounded-lg bg-amber-500/10 px-3 py-2 font-semibold text-amber-700 hover:bg-amber-500/20 dark:text-amber-400">Cetak</a>@else — @endif</td></tr>
                    @endforeach
                </tbody></table></div>
            </section>
        </div>
    @endif
</x-filament-panels::page>
