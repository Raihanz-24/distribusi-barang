<x-filament-panels::page>
    <div class="deposit-report">
        <section class="deposit-report__hero">
            <div><span>KEUANGAN · SETORAN HARIAN</span><h2>Rekap Setoran Sales</h2><p>Hanya setoran yang sudah diperiksa dan disetujui Admin yang masuk ke dalam rekap.</p></div>
            <div class="deposit-report__hero-total"><small>Uang fisik diterima periode ini</small><strong>Rp {{ number_format($summary['actual'], 0, ',', '.') }}</strong><span>Nominal yang sudah diverifikasi Admin</span></div>
        </section>

        <section class="deposit-filters">
            <div class="deposit-filter"><label>Dari tanggal</label><input type="date" wire:model="startDate"></div>
            <div class="deposit-filter"><label>Sampai tanggal</label><input type="date" wire:model="endDate"></div>
            <div class="deposit-filter"><label>Sales</label><select wire:model="salesId"><option value="">Semua Sales</option>@foreach ($salesOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div>
            <div class="deposit-filter__actions">
                <button type="button" wire:click="applyReport" wire:loading.attr="disabled" class="deposit-button deposit-button--primary">Tampilkan Rekap</button>
                <a href="{{ $exportUrl }}" class="deposit-button deposit-button--excel">↓ Export Excel</a>
            </div>
            <div class="deposit-filter__presets"><button type="button" wire:click="setToday">Hari ini</button><button type="button" wire:click="setThisMonth">Bulan ini</button><button type="button" wire:click="resetFilters">Reset</button></div>
        </section>

        <section class="deposit-summary">
            <article><span>Setoran seharusnya</span><strong>Rp {{ number_format($summary['expected'], 0, ',', '.') }}</strong><small>Menurut transaksi dari {{ number_format($summary['stores']) }} toko</small></article>
            <article class="deposit-summary__net"><span>Uang fisik diterima</span><strong>Rp {{ number_format($summary['actual'], 0, ',', '.') }}</strong><small>Hasil pemeriksaan Admin</small></article>
            <article class="deposit-summary__fee"><span>Selisih uang</span><strong>Rp {{ number_format($summary['difference'], 0, ',', '.') }}</strong><small>Fisik dikurangi nominal seharusnya</small></article>
            <article class="deposit-summary__debt"><span>Hutang toko</span><strong>Rp {{ number_format($summary['debt'], 0, ',', '.') }}</strong><small>Belum termasuk uang setoran</small></article>
        </section>

        <section class="deposit-table-panel">
            <div class="deposit-table-panel__head"><div><span>RINCIAN HARIAN</span><h3>{{ number_format($summary['records']) }} rekap setoran</h3></div><p>{{ \Carbon\Carbon::parse($startDate)->translatedFormat('d M Y') }} – {{ \Carbon\Carbon::parse($endDate)->translatedFormat('d M Y') }}</p></div>

            <div class="deposit-desktop-table">
                <table><thead><tr><th>Tanggal</th><th>Sales</th><th>Toko / Laku</th><th>Seharusnya</th><th>Fisik diterima</th><th>Selisih</th><th>Hutang toko</th><th>Kekurangan stok</th><th></th></tr></thead><tbody>
                    @forelse ($rows as $settlement)
                        <tr>
                            <td><strong>{{ $settlement->settlement_date->format('d M Y') }}</strong><small>{{ $settlement->returnTransfer?->number ?? '-' }}</small></td>
                            <td><strong>{{ $settlement->sales->name }}</strong><small>{{ '@'.$settlement->sales->username }}</small></td>
                            <td><strong>{{ $settlement->total_stores }} toko</strong><small>{{ number_format($settlement->total_sold_pieces) }} bungkus</small></td>
                            <td>Rp {{ number_format($settlement->total_collection, 0, ',', '.') }}</td>
                            <td><strong>Rp {{ number_format($settlement->verified_collection, 0, ',', '.') }}</strong></td>
                            <td class="{{ $settlement->cash_difference === 0 ? 'deposit-money--net' : 'deposit-money--debt' }}">Rp {{ number_format($settlement->cash_difference, 0, ',', '.') }}</td>
                            <td class="deposit-money--debt">Rp {{ number_format((int) $settlement->total_debt, 0, ',', '.') }}</td>
                            <td>{{ number_format($settlement->stock_shortage_pieces) }} bungkus</td>
                            <td>@if ($settlement->returnTransfer)<a class="deposit-detail-link" href="{{ \App\Filament\Resources\SalesStockTransfers\SalesStockTransferResource::getUrl('view', ['record' => $settlement->returnTransfer]) }}">Detail ›</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><div class="deposit-empty"><span>◎</span><strong>Belum ada setoran yang disetujui pada periode ini</strong><p>Rekap hanya menampilkan pengembalian yang sudah diperiksa dan disetujui Admin.</p></div></td></tr>
                    @endforelse
                </tbody></table>
            </div>

            <div class="deposit-mobile-list">
                @forelse ($rows as $settlement)
                    <article class="deposit-card">
                        <div class="deposit-card__head"><div><span>{{ $settlement->settlement_date->format('d M Y') }}</span><strong>{{ $settlement->sales->name }}</strong></div></div>
                        <div class="deposit-card__gross"><span>Setoran fisik</span><strong>Rp {{ number_format($settlement->verified_collection, 0, ',', '.') }}</strong></div>
                        <div class="deposit-card__grid"><div><span>Seharusnya</span><strong>Rp {{ number_format($settlement->total_collection, 0, ',', '.') }}</strong></div><div><span>Selisih uang</span><strong class="deposit-money--debt">Rp {{ number_format($settlement->cash_difference, 0, ',', '.') }}</strong></div><div><span>Toko</span><strong>{{ $settlement->total_stores }}</strong></div><div><span>Kekurangan stok</span><strong>{{ number_format($settlement->stock_shortage_pieces) }} bungkus</strong></div></div>
                        @if ($settlement->returnTransfer)<a href="{{ \App\Filament\Resources\SalesStockTransfers\SalesStockTransferResource::getUrl('view', ['record' => $settlement->returnTransfer]) }}">Lihat detail setoran <span>›</span></a>@endif
                    </article>
                @empty
                    <div class="deposit-empty"><strong>Belum ada setoran pada periode ini.</strong></div>
                @endforelse
            </div>
        </section>
    </div>

    <style>
        .deposit-report{display:grid;gap:16px}.deposit-report__hero{display:flex;align-items:center;justify-content:space-between;gap:24px;overflow:hidden;border-radius:22px;padding:25px 28px;background:radial-gradient(circle at 80% 10%,rgba(253,230,138,.25),transparent 32%),linear-gradient(130deg,#3b1d0b,#8a450d 60%,#c66a09);color:#fff;box-shadow:0 15px 40px rgba(74,37,8,.2)}.deposit-report__hero>div:first-child{max-width:650px}.deposit-report__hero span{color:#fcd34d;font-size:10px;font-weight:800;letter-spacing:.14em}.deposit-report__hero h2{margin:5px 0;font-size:27px}.deposit-report__hero p{margin:0;color:#fdebd3;font-size:12px}.deposit-report__hero-total{display:grid;min-width:270px;border:1px solid rgba(255,255,255,.15);border-radius:16px;padding:16px;background:rgba(24,12,5,.2)}.deposit-report__hero-total small{color:#fdebd3;font-size:10px}.deposit-report__hero-total strong{margin:3px 0;color:#fff;font-size:25px}.deposit-report__hero-total span{font-size:9px;letter-spacing:0}.deposit-filters{display:grid;grid-template-columns:repeat(4,minmax(130px,1fr)) auto;align-items:end;gap:12px;border:1px solid rgba(148,163,184,.14);border-radius:18px;padding:16px;background:var(--deposit-panel,rgba(255,255,255,.025))}.deposit-filter{display:grid;gap:6px}.deposit-filter label{color:#9ca3af;font-size:10px;font-weight:700}.deposit-filter input,.deposit-filter select{width:100%;height:41px;border:1px solid rgba(148,163,184,.22);border-radius:10px;padding:0 11px;background:rgba(127,127,127,.04);color:inherit;font-size:11px;outline:0}.deposit-filter input:focus,.deposit-filter select:focus{border-color:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,.1)}.deposit-filter__actions{display:flex;gap:8px}.deposit-button{display:inline-flex;height:41px;align-items:center;justify-content:center;border:0;border-radius:10px;padding:0 14px;font-size:11px;font-weight:800;white-space:nowrap}.deposit-button--primary{background:#d97706;color:#fff}.deposit-button--excel{background:#15803d;color:#fff}.deposit-filter__presets{grid-column:1/-1;display:flex;gap:8px;padding-top:3px}.deposit-filter__presets button{border:1px solid rgba(148,163,184,.18);border-radius:99px;padding:5px 10px;color:#9ca3af;font-size:9px;font-weight:700}.deposit-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.deposit-summary article{position:relative;overflow:hidden;border:1px solid rgba(148,163,184,.14);border-radius:16px;padding:16px;background:var(--deposit-panel,rgba(255,255,255,.025))}.deposit-summary article:before{position:absolute;inset:0 auto 0 0;width:4px;background:#d97706;content:""}.deposit-summary__fee:before{background:#8b5cf6!important}.deposit-summary__net:before{background:#22c55e!important}.deposit-summary__debt:before{background:#ef4444!important}.deposit-summary span{display:block;color:#9ca3af;font-size:10px}.deposit-summary strong{display:block;margin:7px 0 3px;font-size:20px}.deposit-summary small{color:#9ca3af;font-size:9px}.deposit-table-panel{overflow:hidden;border:1px solid rgba(148,163,184,.14);border-radius:19px;background:var(--deposit-panel,rgba(255,255,255,.025))}.deposit-table-panel__head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:17px 19px;border-bottom:1px solid rgba(148,163,184,.12)}.deposit-table-panel__head span{color:#d97706;font-size:9px;font-weight:800;letter-spacing:.13em}.deposit-table-panel__head h3{margin:2px 0 0;font-size:15px}.deposit-table-panel__head p{margin:0;color:#9ca3af;font-size:10px}.deposit-desktop-table{overflow-x:auto}.deposit-desktop-table table{width:100%;min-width:1050px;border-collapse:collapse;text-align:left}.deposit-desktop-table th{padding:11px 13px;background:rgba(127,127,127,.045);color:#9ca3af;font-size:9px;text-transform:uppercase;white-space:nowrap}.deposit-desktop-table td{padding:13px;border-top:1px solid rgba(148,163,184,.09);font-size:10px;white-space:nowrap}.deposit-desktop-table td>strong,.deposit-desktop-table td>small{display:block}.deposit-desktop-table td>small{margin-top:2px;color:#9ca3af;font-size:9px}.deposit-money--debt{color:#ef4444}.deposit-money--fee{color:#a78bfa}.deposit-money--net{color:#22c55e}.deposit-status{display:inline-flex;border-radius:99px;padding:5px 8px;font-size:8px;font-weight:800}.deposit-status--approved{background:rgba(34,197,94,.12);color:#22c55e}.deposit-status--pending{background:rgba(245,158,11,.12);color:#f59e0b}.deposit-detail-link{color:#f59e0b;font-weight:800}.deposit-mobile-list{display:none}.deposit-empty{padding:38px 15px;text-align:center;color:#9ca3af}.deposit-empty span{display:block;font-size:25px}.deposit-empty strong{display:block;color:inherit;font-size:11px}.deposit-empty p{margin:4px 0 0;font-size:9px}
        .deposit-filters{grid-template-columns:repeat(3,minmax(130px,1fr)) auto}
        html:not(.dark) .deposit-report{--deposit-panel:#fff}html:not(.dark) .deposit-filters,html:not(.dark) .deposit-summary article,html:not(.dark) .deposit-table-panel{box-shadow:0 7px 24px rgba(74,37,8,.045)}html.dark .deposit-report{--deposit-panel:#181411}
        @media(max-width:1100px){.deposit-filters{grid-template-columns:repeat(2,1fr)}.deposit-filter__actions{grid-column:1/-1}.deposit-button{flex:1}.deposit-summary{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:767px){.deposit-report{gap:12px}.deposit-report__hero{align-items:flex-start;flex-direction:column;padding:20px}.deposit-report__hero h2{font-size:22px}.deposit-report__hero-total{width:100%;min-width:0}.deposit-filters{grid-template-columns:1fr 1fr;padding:13px}.deposit-filter:nth-child(3),.deposit-filter:nth-child(4){grid-column:span 1}.deposit-filter__actions{display:grid;grid-template-columns:1fr 1fr}.deposit-summary{gap:8px}.deposit-summary article{padding:13px}.deposit-summary strong{font-size:15px}.deposit-summary small{display:none}.deposit-table-panel__head{padding:14px}.deposit-table-panel__head p{display:none}.deposit-desktop-table{display:none}.deposit-mobile-list{display:grid;gap:10px;padding:11px}.deposit-card{overflow:hidden;border:1px solid rgba(148,163,184,.14);border-radius:14px;padding:13px}.deposit-card__head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}.deposit-card__head>div{display:grid}.deposit-card__head>div span{color:#9ca3af;font-size:9px}.deposit-card__head>div strong{font-size:12px}.deposit-card__gross{display:flex;align-items:center;justify-content:space-between;margin:12px 0;padding:10px;border-radius:10px;background:rgba(245,158,11,.09)}.deposit-card__gross span{color:#9ca3af;font-size:9px}.deposit-card__gross strong{color:#f59e0b;font-size:15px}.deposit-card__grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}.deposit-card__grid div{display:grid}.deposit-card__grid span{color:#9ca3af;font-size:8px}.deposit-card__grid strong{font-size:10px}.deposit-card>a{display:flex;justify-content:flex-end;gap:5px;margin-top:12px;padding-top:10px;border-top:1px solid rgba(148,163,184,.1);color:#f59e0b;font-size:10px;font-weight:800}}
    </style>
</x-filament-panels::page>
