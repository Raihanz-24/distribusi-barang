@php($latestVisit = $visits->first())

<div class="space-y-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total hutang saat ini</p>
                <p class="mt-1 text-lg font-bold {{ $outstandingDebt > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-600 dark:text-success-400' }}">Rp {{ number_format($outstandingDebt, 0, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Sisa stok di toko</p>
                <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ number_format($stockTotal) }} bungkus</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Total transaksi</p>
                <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ number_format($visits->count()) }} transaksi</p>
            </div>
        </div>

        <a href="{{ route('reports.stores.export', ['store' => $store]) }}" class="fi-btn fi-btn-size-md inline-flex items-center justify-center gap-2 rounded-lg bg-success-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-success-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-success-600">
            <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-5 w-5" />
            Export Excel Toko
        </a>
    </div>

    <div>
        <div class="mb-3 flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Sisa stok di toko</h3>
            <span class="text-xs text-gray-500 dark:text-gray-400">Diperbarui dari transaksi terakhir</span>
        </div>
        @if ($stockBalances->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 px-4 py-5 text-center text-sm text-gray-500 dark:border-white/20 dark:text-gray-400">Belum ada stok produk yang tersisa di toko.</div>
        @else
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($stockBalances as $balance)
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/5">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-gray-950 dark:text-white">{{ $balance->product?->name ?? 'Produk dihapus' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $balance->product?->code ?? '-' }}</p>
                        </div>
                        <p class="shrink-0 text-right text-sm font-semibold text-gray-950 dark:text-white">{{ $balance->product?->formatStockQuantity($balance->quantity_pieces) ?? number_format($balance->quantity_pieces).' bungkus' }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if ($latestVisit)
        <div>
            <div class="mb-3 flex items-center gap-2">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Transaksi terakhir</h3>
                <span class="rounded-full bg-success-50 px-2 py-1 text-xs font-medium text-success-700 dark:bg-success-400/10 dark:text-success-300">Terbaru</span>
            </div>
            <div class="rounded-2xl border border-gray-200 p-4 dark:border-white/10 sm:p-5">
                @include('filament.stores.transaction-detail', ['visit' => $latestVisit])
            </div>
        </div>

        @if ($visits->count() > 1)
            <div>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Riwayat sebelumnya</h3>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Klik transaksi untuk melihat detail</span>
                </div>
                <div class="space-y-2">
                    @foreach ($visits->skip(1) as $visit)
                        <details class="group overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-4 hover:bg-gray-50 dark:hover:bg-white/5">
                                <div class="grid min-w-0 flex-1 grid-cols-1 gap-2 sm:grid-cols-3">
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Waktu</p>
                                        <p class="font-medium text-gray-950 dark:text-white">{{ optional($visit->completed_at ?? $visit->arrived_at)->timezone(config('app.timezone'))->format('d M Y, H:i') }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Sales</p>
                                        <p class="truncate font-medium text-gray-950 dark:text-white">{{ $visit->sales?->name ?? '-' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Dibayarkan</p>
                                        <p class="font-semibold text-success-600 dark:text-success-400">Rp {{ number_format($visit->amount_paid, 0, ',', '.') }}</p>
                                    </div>
                                </div>
                                <x-filament::icon icon="heroicon-o-chevron-down" class="h-5 w-5 shrink-0 text-gray-400 transition group-open:rotate-180" />
                            </summary>
                            <div class="border-t border-gray-200 p-4 dark:border-white/10 sm:p-5">
                                @include('filament.stores.transaction-detail', ['visit' => $visit])
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
        @endif
    @else
        <div class="rounded-2xl border border-dashed border-gray-300 px-4 py-10 text-center dark:border-white/20">
            <p class="font-semibold text-gray-950 dark:text-white">Belum ada transaksi toko</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Transaksi yang diselesaikan sales akan tercatat otomatis di sini.</p>
        </div>
    @endif
</div>
