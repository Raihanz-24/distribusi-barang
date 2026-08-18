<x-filament-panels::page>
    @php
        $record = $this->record;
        $settlement = $record->returnSettlement ?? $record->pickupSettlement;
        $pickup = $record->transfer_type->value === 'return_from_sales' ? $record->pickupTransfer : $record;
        $return = $record->transfer_type->value === 'return_from_sales' ? $record : $record->returnTransfer;
        $route = $record->dailyRoute ?? $settlement?->dailyRoute;
        $visits = $settlement?->dailyRoute?->visits?->filter(fn ($visit) => $visit->items->isNotEmpty()) ?? collect();
    @endphp

    <div class="distribution-detail">
        <section class="distribution-detail__hero">
            <div><span>{{ $record->number }}</span><h2>{{ $record->sales?->name ?? 'Distribusi' }}</h2></div>
            <strong>{{ $record->displayStatusLabel() }}</strong>
        </section>

        <section class="distribution-detail__grid">
            <div><span>Jadwal kerja</span><strong>{{ $route?->route_date?->translatedFormat('d M Y') ?? '-' }}</strong></div>
            <div><span>Pengambilan</span><strong>{{ $pickup?->issued_at?->format('d M Y, H:i') ?? '-' }}</strong></div>
            <div><span>Pengembalian</span><strong>{{ $return?->received_at?->format('d M Y, H:i') ?? '-' }}</strong></div>
            <div><span>Sales</span><strong>{{ $record->sales?->name ?? '-' }}</strong></div>
        </section>

        <section class="distribution-detail__stock-grid">
            <div class="distribution-detail__section distribution-detail__stock distribution-detail__stock--out">
                <div class="distribution-detail__stock-head"><div><span>STOK AWAL SALES</span><h3>Dibawa saat pengambilan</h3></div><strong>{{ number_format($pickup?->items->sum('quantity_issued') ?? 0) }} bungkus</strong></div>
                @forelse ($pickup?->items ?? [] as $item)
                    <div class="distribution-detail__row"><span>{{ $item->product->name }}</span><strong>{{ $item->product->formatStockQuantity($item->quantity_issued) }}</strong></div>
                @empty
                    <p class="text-sm text-gray-500">Data pengambilan tidak tersedia.</p>
                @endforelse
            </div>
            <div class="distribution-detail__section distribution-detail__stock distribution-detail__stock--return">
                <div class="distribution-detail__stock-head"><div><span>STOK AKHIR SALES</span><h3>Sisa kembali ke Admin</h3></div><strong>{{ $return ? number_format($return->items->sum('quantity_issued')).' bungkus' : 'Belum diajukan' }}</strong></div>
                @forelse ($return?->items ?? [] as $item)
                    <div class="distribution-detail__row"><span>{{ $item->product->name }}</span><strong>{{ $item->product->formatStockQuantity($item->quantity_received ?? $item->quantity_issued) }}</strong></div>
                @empty
                    <p class="text-sm text-gray-500">Sisa produk akan tercatat saat Sales menekan Selesai.</p>
                @endforelse
            </div>
        </section>

        @if ($settlement)
            <section class="distribution-detail__money">
                <div><span>Setoran seharusnya</span><strong>Rp {{ number_format($settlement->total_collection, 0, ',', '.') }}</strong></div>
                <div><span>Uang fisik diterima</span><strong>Rp {{ number_format($settlement->verified_collection, 0, ',', '.') }}</strong></div>
                <div><span>Selisih uang</span><strong>Rp {{ number_format($settlement->cash_difference, 0, ',', '.') }}</strong></div>
                <div><span>Toko transaksi</span><strong>{{ $settlement->total_stores }}</strong></div>
                <div><span>Produk laku</span><strong>{{ number_format($settlement->total_sold_pieces) }} bungkus</strong></div>
                <div><span>Produk ditarik</span><strong>{{ number_format($settlement->total_withdrawn_pieces) }} bungkus</strong></div>
                <div><span>Kekurangan stok</span><strong>{{ number_format($settlement->stock_shortage_pieces) }} bungkus</strong></div>
            </section>

            <section class="distribution-detail__section">
                <h3>Transaksi Toko</h3>
                @forelse ($visits as $visit)
                    <details class="distribution-detail__visit">
                        <summary><span>{{ $visit->store->name }}</span><strong>Bayar Rp {{ number_format($visit->amount_paid, 0, ',', '.') }}@if ($visit->debt_amount > 0) · Hutang Rp {{ number_format($visit->debt_amount, 0, ',', '.') }}@endif</strong></summary>
                        @foreach ($visit->items as $item)
                            <div class="distribution-detail__visit-row">
                                <span><strong>{{ $item->product->name }}</strong> · Laku {{ $item->product->formatStockQuantity($item->sold_pieces) }} · Titip {{ $item->product->formatStockQuantity($item->deposited_pieces) }} · <b>Tarik {{ $item->product->formatStockQuantity($item->withdrawn_pieces) }}</b></span>
                            </div>
                        @endforeach
                    </details>
                @empty
                    <p class="text-sm text-gray-500">Belum ada transaksi toko.</p>
                @endforelse
            </section>
        @endif
    </div>

    <style>
        .distribution-detail{display:grid;gap:16px;max-width:1080px;margin:auto}.distribution-detail__hero,.distribution-detail__section,.distribution-detail__grid,.distribution-detail__money{border:1px solid rgba(148,163,184,.16);border-radius:18px;background:var(--distribution-detail-panel,#181411)}html:not(.dark) .distribution-detail{--distribution-detail-panel:#fff}html.dark .distribution-detail{--distribution-detail-panel:#181411}.distribution-detail__hero{display:flex;justify-content:space-between;gap:16px;padding:20px}.distribution-detail__hero span,.distribution-detail__grid span,.distribution-detail__money span,.distribution-detail__stock-head span{color:#9ca3af;font-size:11px}.distribution-detail__hero h2{margin:4px 0 0;font-size:22px}.distribution-detail__hero>strong{color:#f59e0b;font-size:13px}.distribution-detail__grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;padding:16px}.distribution-detail__money{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;padding:16px}.distribution-detail__grid div,.distribution-detail__money div{display:grid;gap:4px}.distribution-detail__stock-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.distribution-detail__section{padding:18px}.distribution-detail__section h3{margin:0 0 12px}.distribution-detail__stock-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px}.distribution-detail__stock-head h3{margin:3px 0 0}.distribution-detail__stock-head>strong{border-radius:10px;padding:7px 10px;font-size:12px}.distribution-detail__stock--out .distribution-detail__stock-head>strong{background:rgba(245,158,11,.12);color:#f59e0b}.distribution-detail__stock--return .distribution-detail__stock-head>strong{background:rgba(34,197,94,.12);color:#22c55e}.distribution-detail__row{display:flex;justify-content:space-between;gap:12px;padding:11px 0;border-top:1px solid rgba(148,163,184,.12)}.distribution-detail__row strong{text-align:right}.distribution-detail__visit{border-top:1px solid rgba(148,163,184,.12);padding:12px 0}.distribution-detail__visit summary{display:flex;justify-content:space-between;gap:12px;cursor:pointer}.distribution-detail__visit-row{display:flex;justify-content:space-between;gap:12px;padding:8px 0 0;color:#9ca3af;font-size:12px}.distribution-detail__visit-row b{color:#f59e0b}
        @media(max-width:767px){.fi-header-heading{font-size:20px}.distribution-detail{gap:12px}.distribution-detail__hero{padding:16px}.distribution-detail__hero h2{font-size:17px}.distribution-detail__grid,.distribution-detail__money{grid-template-columns:1fr 1fr;padding:14px}.distribution-detail__stock-grid{grid-template-columns:1fr}.distribution-detail__section{padding:15px}.distribution-detail__visit-row{display:grid}.distribution-detail__money strong{font-size:13px}.distribution-detail__row{display:grid}.distribution-detail__row strong{text-align:left}}
        .distribution-detail__money{grid-template-columns:repeat(auto-fit,minmax(130px,1fr))}
        @media(max-width:767px){.distribution-detail__money{grid-template-columns:1fr 1fr}}
    </style>
</x-filament-panels::page>
