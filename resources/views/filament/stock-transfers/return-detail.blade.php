@php
    $settlement = $transfer->returnSettlement;
    $visits = $settlement?->dailyRoute?->visits?->filter(fn ($visit) => $visit->items->isNotEmpty()) ?? collect();
@endphp

<div class="space-y-5">
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5"><div class="text-xs text-gray-500">Toko dikunjungi</div><strong>{{ $settlement?->total_stores ?? 0 }}</strong></div>
        <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5"><div class="text-xs text-gray-500">Produk laku</div><strong>{{ number_format($settlement?->total_sold_pieces ?? 0) }} bungkus</strong></div>
        <div class="rounded-xl bg-primary-50 p-3 dark:bg-primary-500/10"><div class="text-xs text-primary-600">Tagihan final</div><strong>Rp {{ number_format($settlement?->total_collection ?? 0, 0, ',', '.') }}</strong></div>
    </div>

    @if ($settlement?->status?->value === 'approved')
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-xl bg-gray-50 p-3 dark:bg-white/5"><div class="text-xs text-gray-500">Uang menurut sistem</div><strong>Rp {{ number_format($settlement->total_collection, 0, ',', '.') }}</strong></div>
            <div class="rounded-xl bg-success-50 p-3 dark:bg-success-500/10"><div class="text-xs text-success-600">Uang fisik diterima</div><strong>Rp {{ number_format($settlement->verified_collection, 0, ',', '.') }}</strong></div>
            <div class="rounded-xl {{ $settlement->cash_difference === 0 ? 'bg-success-50 dark:bg-success-500/10' : 'bg-danger-50 dark:bg-danger-500/10' }} p-3"><div class="text-xs text-gray-500">Selisih uang</div><strong>Rp {{ number_format($settlement->cash_difference, 0, ',', '.') }}</strong></div>
            <div class="rounded-xl {{ $settlement->stock_shortage_pieces === 0 ? 'bg-success-50 dark:bg-success-500/10' : 'bg-danger-50 dark:bg-danger-500/10' }} p-3"><div class="text-xs text-gray-500">Selisih stok</div><strong>{{ number_format($settlement->stock_shortage_pieces) }} bungkus</strong></div>
        </div>
        @if ($settlement->verification_notes)<div class="rounded-xl border border-gray-200 p-3 text-sm dark:border-white/10"><span class="text-gray-500">Catatan pemeriksaan:</span> {{ $settlement->verification_notes }}</div>@endif
    @endif

    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
        <h3 class="mb-3 font-semibold">Sisa yang dikembalikan</h3>
        @forelse ($transfer->items as $item)
            <div class="flex justify-between border-b border-gray-100 py-2 last:border-0 dark:border-white/5">
                <span>{{ $item->product->name }}</span><strong>{{ number_format($item->quantity_received ?? $item->quantity_issued) }} / {{ number_format($item->quantity_issued) }} bungkus</strong>
            </div>
        @empty
            <p class="text-sm text-gray-500">Tidak ada sisa stok bawaan.</p>
        @endforelse
    </div>

    <div class="space-y-3">
        <h3 class="font-semibold">Detail transaksi toko</h3>
        @forelse ($visits as $visit)
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div><strong>{{ $visit->store->name }}</strong><div class="text-xs text-gray-500">{{ $visit->completed_at?->format('d M Y, H:i') }}</div></div>
                    <div class="text-right text-sm"><strong class="block text-success-600">Dibayar Rp {{ number_format($visit->amount_paid, 0, ',', '.') }}</strong>@if ($visit->debt_amount > 0)<span class="text-danger-600">Hutang Rp {{ number_format($visit->debt_amount, 0, ',', '.') }}</span>@endif</div>
                </div>
                @foreach ($visit->items as $item)
                    <div class="grid grid-cols-2 gap-2 border-t border-gray-100 py-2 text-sm md:grid-cols-4 dark:border-white/5">
                        <span>{{ $item->product->name }}</span>
                        <span>Laku: {{ $item->sold_pieces }} bungkus</span>
                        <span>Titip: {{ $item->deposited_pieces }} · Tarik: {{ $item->withdrawn_pieces }} bungkus</span>
                        <strong>Wajib: Rp {{ number_format($item->amount_due, 0, ',', '.') }}</strong>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="rounded-xl bg-gray-50 p-4 text-sm text-gray-500 dark:bg-white/5">Belum ada transaksi toko yang tercatat pada rute ini.</div>
        @endforelse
    </div>
</div>
