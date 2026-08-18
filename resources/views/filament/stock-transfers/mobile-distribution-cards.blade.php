@use('App\Filament\Resources\SalesStockTransfers\SalesStockTransferResource')

<div class="distribution-mobile-cards distribution-mobile-list block md:hidden">
    @forelse ($records as $record)
        @php
            $settlement = $record->returnSettlement ?? $record->pickupSettlement;
            $pickup = $record->transfer_type->value === 'return_from_sales' ? $record->pickupTransfer : $record;
            $return = $record->transfer_type->value === 'return_from_sales' ? $record : $record->returnTransfer;
            $totalPieces = $pickup?->items->sum('quantity_issued') ?? 0;
            $remainingPieces = $return?->items->sum('quantity_issued');
            $scheduleDate = $record->dailyRoute?->route_date;
        @endphp
        <article class="distribution-mobile-card">
            <div class="distribution-mobile-card__head">
                <div>
                    <span class="distribution-mobile-card__eyebrow">{{ $record->number }}</span>
                    <h3>{{ $record->sales?->name ?? 'Transfer Gudang' }}</h3>
                </div>
                <span class="distribution-mobile-card__status">{{ $record->displayStatusLabel() }}</span>
            </div>

            <div class="distribution-mobile-card__schedule">
                <span>Jadwal kerja</span>
                <strong>{{ $scheduleDate?->translatedFormat('d M Y') ?? 'Tidak terkait jadwal' }}</strong>
            </div>

            <div class="distribution-mobile-card__products">
                @foreach ($pickup?->items?->take(2) ?? [] as $item)
                    <div class="distribution-mobile-card__product">
                        <span class="distribution-mobile-card__cube">{{ mb_substr($item->product->name, 0, 1) }}</span>
                        <div><strong>{{ $item->product->name }}</strong><small>Dibawa {{ $item->product->formatStockQuantity($item->quantity_issued) }}</small></div>
                    </div>
                @endforeach
            </div>

            <div class="distribution-mobile-card__summary">
                <div><span>Total dibawa</span><strong>{{ number_format($totalPieces) }} bungkus</strong></div>
                <div><span>Total sisa kembali</span><strong>{{ $remainingPieces !== null ? number_format($remainingPieces).' bungkus' : 'Belum diajukan' }}</strong></div>
                <div><span>Jenis produk dibawa</span><strong>{{ $pickup?->items->count() ?? 0 }} jenis</strong></div>
                <div><span>Pengambilan</span><strong>{{ $record->transfer_type->value === 'return_from_sales' ? ($record->pickupTransfer?->issued_at?->format('d M, H:i') ?? '-') : ($record->issued_at?->format('d M, H:i') ?? '-') }}</strong></div>
                @if ($settlement)
                    <div><span>Tagihan final</span><strong>Rp {{ number_format($settlement->total_collection, 0, ',', '.') }}</strong></div>
                @endif
            </div>

            <div class="distribution-mobile-card__actions">
                @if (auth()->user()->isAdmin() && $record->transfer_type->value === 'issue_to_sales' && $record->status->value === 'received' && ! $record->returnTransfer)
                    @if ($record->sales?->latestLocation)
                        <a class="distribution-mobile-card__track" href="https://www.openstreetmap.org/?mlat={{ $record->sales->latestLocation->latitude }}&mlon={{ $record->sales->latestLocation->longitude }}#map=17/{{ $record->sales->latestLocation->latitude }}/{{ $record->sales->latestLocation->longitude }}" target="_blank" rel="noopener">Lacak Lokasi</a>
                    @else
                        <button class="distribution-mobile-card__track" type="button" disabled title="Lokasi belum tersedia. Sales harus membuka aplikasi dan mengizinkan GPS.">Lokasi Belum Ada</button>
                    @endif
                @endif
                <a href="{{ SalesStockTransferResource::getUrl('view', ['record' => $record]) }}">Lihat Detail <span>›</span></a>
            </div>
        </article>
    @empty
        <div class="distribution-mobile-empty">Belum ada data pada status ini.</div>
    @endforelse
</div>

@once
    <style>
        .distribution-mobile-cards{display:none}.distribution-desktop-table{display:block}
        @media(max-width:767px){
            .distribution-mobile-cards{display:block}.distribution-desktop-table{display:none!important}
            .fi-sc-tabs{overflow-x:auto;scrollbar-width:none}.fi-sc-tabs::-webkit-scrollbar{display:none}
            .distribution-mobile-list{display:grid;gap:12px}.distribution-mobile-card{overflow:hidden;border:1px solid rgba(148,163,184,.14);border-radius:18px;background:var(--gray-900,#171717);box-shadow:0 8px 24px rgba(0,0,0,.12)}
            .distribution-mobile-card__head{display:flex;justify-content:space-between;gap:12px;padding:16px 16px 12px}.distribution-mobile-card__eyebrow{font-size:11px;color:#9ca3af}.distribution-mobile-card h3{margin:3px 0 0;font-size:15px}.distribution-mobile-card__status{align-self:flex-start;color:#f59e0b;font-size:12px;font-weight:700}
            .distribution-mobile-card__schedule{display:flex;justify-content:space-between;gap:12px;margin:0 16px;padding:10px 0;border-block:1px solid rgba(148,163,184,.12);font-size:12px}.distribution-mobile-card__schedule span{color:#9ca3af}
            .distribution-mobile-card__products{display:grid;gap:10px;padding:14px 16px}.distribution-mobile-card__product{display:flex;align-items:center;gap:10px}.distribution-mobile-card__cube{display:grid;width:42px;height:42px;flex:none;place-items:center;border-radius:11px;background:linear-gradient(145deg,#92400e,#f59e0b);color:#fff;font-weight:800}.distribution-mobile-card__product div{display:grid;gap:2px}.distribution-mobile-card__product strong{font-size:13px}.distribution-mobile-card__product small{color:#9ca3af;font-size:11px}
            .distribution-mobile-card__summary{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:0 16px 14px}.distribution-mobile-card__summary div{display:grid;gap:3px}.distribution-mobile-card__summary span{color:#9ca3af;font-size:10px}.distribution-mobile-card__summary strong{font-size:12px}.distribution-mobile-card__accent{color:#f59e0b}
            .distribution-mobile-card__actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid rgba(148,163,184,.12)}.distribution-mobile-card__actions a,.distribution-mobile-card__actions button{display:flex;align-items:center;justify-content:center;gap:8px;border:1px solid #d97706;border-radius:10px;padding:8px 12px;background:transparent;color:#f59e0b;font-size:12px;font-weight:700}.distribution-mobile-card__actions .distribution-mobile-card__track{border-color:#0284c7;color:#38bdf8}.distribution-mobile-card__actions button:disabled{cursor:not-allowed;border-color:#6b7280;color:#9ca3af;opacity:.7}.distribution-mobile-card__actions a span{font-size:20px;line-height:10px}.distribution-mobile-empty{border:1px dashed rgba(148,163,184,.25);border-radius:16px;padding:30px 16px;text-align:center;color:#9ca3af;font-size:13px}
        }
        @media(max-width:767px){html:not(.dark) .distribution-mobile-card{background:#fff;color:#2b1b13}html.dark .distribution-mobile-card{background:#181411}}
    </style>
@endonce
