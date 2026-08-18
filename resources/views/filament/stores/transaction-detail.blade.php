<div class="space-y-4">
    @if ($visit->reversed_at)
        <div class="rounded-xl bg-danger-50 px-3 py-2 text-sm font-semibold text-danger-700 dark:bg-danger-400/10 dark:text-danger-300">
            Transaksi dibatalkan {{ $visit->reversed_at->timezone(config('app.timezone'))->format('d M Y, H:i') }} · {{ $visit->reversal_reason }}
        </div>
    @endif
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Waktu</p>
            <p class="mt-1 font-medium text-gray-950 dark:text-white">{{ optional($visit->completed_at ?? $visit->arrived_at)->timezone(config('app.timezone'))->format('d M Y, H:i') }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Sales</p>
            <p class="mt-1 font-medium text-gray-950 dark:text-white">{{ $visit->sales?->name ?? '-' }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Wajib bayar</p>
            <p class="mt-1 font-semibold text-gray-950 dark:text-white">Rp {{ number_format($visit->amount_due, 0, ',', '.') }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Dibayarkan</p>
            <p class="mt-1 font-semibold text-success-600 dark:text-success-400">Rp {{ number_format($visit->amount_paid, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="space-y-2">
        <p class="text-sm font-semibold text-gray-950 dark:text-white">Detail produk</p>
        @foreach ($visit->items as $item)
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <p class="font-semibold text-gray-950 dark:text-white">{{ $item->product?->name ?? 'Produk dihapus' }}</p>
                    <p class="shrink-0 font-semibold text-gray-950 dark:text-white">Rp {{ number_format($item->amount_due, 0, ',', '.') }}</p>
                </div>
                <div class="grid grid-cols-3 gap-3 text-sm">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Laku</p>
                        <p class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $item->product?->formatStockQuantity($item->sold_pieces) ?? number_format($item->sold_pieces).' bungkus' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Dititipkan</p>
                        <p class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $item->product?->formatStockQuantity($item->deposited_pieces) ?? number_format($item->deposited_pieces).' bungkus' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Ditarik</p>
                        <p class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $item->product?->formatStockQuantity($item->withdrawn_pieces) ?? number_format($item->withdrawn_pieces).' bungkus' }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($visit->debt_amount > 0)
        <div class="rounded-xl bg-danger-50 px-3 py-2 text-sm font-medium text-danger-700 dark:bg-danger-400/10 dark:text-danger-300">
            Hutang baru dari transaksi ini: Rp {{ number_format($visit->debt_amount, 0, ',', '.') }}
        </div>
    @endif
</div>
