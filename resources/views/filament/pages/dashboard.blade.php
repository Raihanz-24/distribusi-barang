<x-filament-panels::page>
    @php
        $isAdmin = $mode === 'admin';
        $todayRoute = $routes->first();
        $planned = (int) ($todayRoute?->route_stores_count ?? 0);
        $finished = (int) ($todayRoute?->completed_stores_count ?? 0);
        $routeProgress = $planned > 0 ? min(100, round(($finished / $planned) * 100)) : 0;
    @endphp

    <div class="coffee-dashboard">
        <section class="coffee-dashboard__hero">
            <div class="coffee-dashboard__hero-copy">
                <div class="coffee-dashboard__eyebrow">PANCOR MAS · {{ now()->translatedFormat('l, d F Y') }}</div>
                <h2>Selamat {{ now()->hour < 11 ? 'pagi' : (now()->hour < 15 ? 'siang' : (now()->hour < 18 ? 'sore' : 'malam')) }}, {{ auth()->user()->name }}</h2>
                <p>{{ $isAdmin ? 'Pantau distribusi, stok, kunjungan, dan arus pembayaran dari satu halaman.' : 'Semua informasi rute dan hasil kerja Anda hari ini tersedia di sini.' }}</p>
                <div class="coffee-dashboard__hero-actions">
                    <a href="{{ $quickActions[0]['url'] }}">{{ $isAdmin ? 'Atur operasional hari ini' : 'Mulai kunjungan toko' }} <span>→</span></a>
                    @if ($isAdmin)<a class="coffee-dashboard__hero-link" href="{{ \App\Filament\Pages\StoreMap::getUrl() }}">Buka peta monitoring</a>@endif
                </div>
            </div>
            <div class="coffee-dashboard__hero-mark" aria-hidden="true"><span>☕</span><small>KOPI BUBUK</small><strong>PANCOR MAS</strong></div>
        </section>

        <section class="coffee-dashboard__metrics">
            <article class="coffee-metric coffee-metric--money"><span>Uang diterima hari ini</span><strong>Rp {{ number_format($metrics['collection'], 0, ',', '.') }}</strong><small>Dari transaksi toko yang sudah selesai</small></article>
            <article class="coffee-metric coffee-metric--danger"><span>{{ $isAdmin ? 'Total hutang toko' : 'Hutang tercatat hari ini' }}</span><strong>Rp {{ number_format($metrics['debt'], 0, ',', '.') }}</strong><small>{{ $metrics['debt'] > 0 ? 'Perlu dipantau pada kunjungan berikutnya' : 'Tidak ada hutang tercatat' }}</small></article>
            <article class="coffee-metric coffee-metric--route"><span>{{ $isAdmin ? 'Kunjungan selesai' : 'Progres rute hari ini' }}</span><strong>{{ number_format($metrics['finished_visits']) }}<b> / {{ number_format($metrics['active_stores']) }}</b></strong><small>{{ $isAdmin ? 'Kunjungan dari seluruh Sales hari ini' : $routeProgress.'% toko telah diproses' }}</small></article>
            <article class="coffee-metric coffee-metric--stock"><span>{{ $isAdmin ? 'Toko aktif' : 'Estimasi fee bulan ini' }}</span><strong>{{ $isAdmin ? number_format($metrics['active_stores']) : 'Rp '.number_format($metrics['month_fee'], 0, ',', '.') }}</strong><small>{{ $isAdmin ? 'Toko siap masuk penjadwalan rute' : 'Akumulasi settlement bulan berjalan' }}</small></article>
        </section>

        <div class="coffee-dashboard__layout">
            <div class="coffee-dashboard__main">
                <section class="coffee-panel">
                    <div class="coffee-panel__head"><div><span>OPERASIONAL HARI INI</span><h3>{{ $isAdmin ? 'Progres rute Sales' : 'Jadwal dan progres saya' }}</h3></div><a href="{{ $isAdmin ? \App\Filament\Resources\DailyRoutes\DailyRouteResource::getUrl() : \App\Filament\Pages\TodayRoute::getUrl() }}">Lihat semua</a></div>
                    <div class="coffee-routes">
                        @forelse ($routes as $route)
                            @php
                                $routeTotal = (int) $route->route_stores_count;
                                $routeDone = (int) $route->completed_stores_count;
                                $percent = $routeTotal > 0 ? min(100, round(($routeDone / $routeTotal) * 100)) : 0;
                                $areaNames = $route->areas->pluck('name');
                                $areaLabel = $areaNames->isNotEmpty() ? $areaNames->join(', ') : ($route->area?->name ?? 'Rute khusus');
                            @endphp
                            <article class="coffee-route">
                                <div class="coffee-route__top"><div class="coffee-route__avatar">{{ mb_strtoupper(mb_substr($route->sales?->name ?? auth()->user()->name, 0, 1)) }}</div><div class="coffee-route__identity"><strong>{{ $route->sales?->name ?? auth()->user()->name }}</strong><span>{{ $areaLabel }}</span></div><span class="coffee-route__status">{{ $route->status->label() }}</span></div>
                                <div class="coffee-route__numbers"><span><strong>{{ $routeDone }}</strong> selesai</span><span><strong>{{ max(0, $routeTotal - $routeDone) }}</strong> tersisa</span><b>{{ $percent }}%</b></div>
                                <div class="coffee-progress"><i style="width: {{ $percent }}%"></i></div>
                            </article>
                        @empty
                            <div class="coffee-empty"><span>🗓️</span><strong>Belum ada rute hari ini</strong><p>{{ $isAdmin ? 'Buat jadwal agar Sales dapat mulai bekerja.' : 'Hubungi Admin jika jadwal belum ditugaskan.' }}</p></div>
                        @endforelse
                    </div>
                </section>

                <section class="coffee-panel">
                    <div class="coffee-panel__head"><div><span>AKTIVITAS TERBARU</span><h3>Transaksi dan kunjungan toko</h3></div><small>{{ $visits->count() }} aktivitas hari ini</small></div>
                    <div class="coffee-activity-list">
                        @forelse ($visits->take(7) as $visit)
                            @php($withdrawn = $visit->items->sum('withdrawn_pieces'))
                            <article class="coffee-activity">
                                <div class="coffee-activity__time">{{ $visit->completed_at?->format('H:i') }}</div>
                                <div class="coffee-activity__dot"></div>
                                <div class="coffee-activity__body"><strong>{{ $visit->store?->name }}</strong><span>{{ $isAdmin ? ($visit->sales?->name.' · ') : '' }}{{ $visit->visit_status->label() }}@if ($withdrawn > 0) · Tarik {{ number_format($withdrawn) }} bungkus @endif</span></div>
                                <div class="coffee-activity__money"><strong>Rp {{ number_format($visit->amount_paid, 0, ',', '.') }}</strong>@if ($visit->debt_amount > 0)<span>Hutang Rp {{ number_format($visit->debt_amount, 0, ',', '.') }}</span>@endif</div>
                            </article>
                        @empty
                            <div class="coffee-empty coffee-empty--small"><strong>Belum ada aktivitas toko hari ini</strong><p>Transaksi akan muncul otomatis setelah kunjungan disimpan.</p></div>
                        @endforelse
                    </div>
                </section>
            </div>

            <aside class="coffee-dashboard__side">
                @if ($isAdmin)
                    <section class="coffee-panel coffee-panel--alerts">
                        <div class="coffee-panel__head"><div><span>PERLU PERHATIAN</span><h3>Kontrol operasional</h3></div></div>
                        <div class="coffee-alerts">
                            @foreach ($alerts as $alert)
                                <div class="coffee-alert coffee-alert--{{ $alert['tone'] }}"><span>{{ $alert['label'] }}</span><strong>{{ number_format($alert['value']) }}</strong></div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="coffee-panel">
                    <div class="coffee-panel__head"><div><span>STOK TERKINI</span><h3>{{ $isAdmin ? 'Gudang' : 'Bawaan saya' }}</h3></div><a href="{{ \App\Filament\Resources\StockBalances\StockBalanceResource::getUrl() }}">Detail</a></div>
                    <div class="coffee-stock-list">
                        @forelse ($warehouses as $warehouse)
                            <div class="coffee-stock-location"><div class="coffee-stock-location__title"><span>{{ $warehouse->type->label() }}</span><strong>{{ number_format($warehouse->balances->sum('quantity_pieces')) }} bungkus</strong></div>
                                @forelse ($warehouse->balances->filter(fn ($balance) => $balance->quantity_pieces > 0) as $balance)
                                    <div class="coffee-stock-row"><span>{{ $balance->product->name }}</span><strong>{{ $balance->product->formatStockQuantity($balance->quantity_pieces) }}</strong></div>
                                @empty
                                    <p>Belum ada stok pada lokasi ini.</p>
                                @endforelse
                            </div>
                        @empty
                            <div class="coffee-empty coffee-empty--small"><p>Lokasi stok belum tersedia.</p></div>
                        @endforelse
                    </div>
                </section>

                <section class="coffee-panel">
                    <div class="coffee-panel__head"><div><span>AKSES CEPAT</span><h3>Menu utama</h3></div></div>
                    <div class="coffee-quick-actions">
                        @foreach ($quickActions as $action)
                            <a href="{{ $action['url'] }}"><span class="coffee-quick-actions__icon">{{ match($action['icon']) { 'route' => '⌁', 'truck' => '⇄', 'map' => '⌖', 'money' => 'Rp', default => '▦' } }}</span><span><strong>{{ $action['label'] }}</strong><small>{{ $action['description'] }}</small></span><b>›</b></a>
                        @endforeach
                    </div>
                </section>
            </aside>
        </div>
    </div>

    <style>
        .coffee-dashboard{display:grid;gap:18px}.coffee-dashboard__hero{position:relative;display:flex;min-height:235px;overflow:hidden;border-radius:24px;padding:30px;background:radial-gradient(circle at 80% 15%,rgba(251,191,36,.24),transparent 30%),linear-gradient(125deg,#2a160d 0%,#713b14 55%,#b8620b 100%);box-shadow:0 20px 50px rgba(50,25,8,.22);color:#fff}.coffee-dashboard__hero:after{position:absolute;right:-80px;bottom:-140px;width:360px;height:360px;border:1px solid rgba(255,255,255,.12);border-radius:50%;content:""}.coffee-dashboard__hero-copy{position:relative;z-index:2;max-width:720px}.coffee-dashboard__eyebrow{margin-bottom:10px;color:#fcd34d;font-size:11px;font-weight:800;letter-spacing:.16em}.coffee-dashboard__hero h2{margin:0;font-size:31px;line-height:1.2}.coffee-dashboard__hero p{max-width:600px;margin:10px 0 0;color:#fdebd3;font-size:14px}.coffee-dashboard__hero-actions{display:flex;align-items:center;gap:18px;margin-top:24px}.coffee-dashboard__hero-actions a{display:inline-flex;align-items:center;gap:12px;border-radius:12px;padding:11px 16px;background:#f59e0b;color:#1f130c;font-size:13px;font-weight:800}.coffee-dashboard__hero-actions .coffee-dashboard__hero-link{padding:0;background:transparent;color:#fff}.coffee-dashboard__hero-mark{position:absolute;z-index:1;right:7%;top:50%;display:grid;width:150px;height:150px;place-content:center;transform:translateY(-50%) rotate(-4deg);border:1px solid rgba(255,255,255,.18);border-radius:50%;background:rgba(25,12,5,.25);box-shadow:inset 0 0 0 8px rgba(255,255,255,.035);text-align:center}.coffee-dashboard__hero-mark span{font-size:42px}.coffee-dashboard__hero-mark small{color:#fcd34d;font-size:8px;letter-spacing:.22em}.coffee-dashboard__hero-mark strong{font-size:13px}.coffee-dashboard__metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.coffee-metric{position:relative;overflow:hidden;border:1px solid rgba(148,163,184,.14);border-radius:18px;padding:18px;background:var(--coffee-panel,rgba(255,255,255,.035));box-shadow:0 8px 28px rgba(0,0,0,.05)}.coffee-metric:before{position:absolute;inset:0 auto 0 0;width:4px;background:#f59e0b;content:""}.coffee-metric--danger:before{background:#ef4444}.coffee-metric--route:before{background:#3b82f6}.coffee-metric--stock:before{background:#22c55e}.coffee-metric>span{display:block;color:#9ca3af;font-size:11px;font-weight:700}.coffee-metric>strong{display:block;margin:8px 0 5px;font-size:24px;color:var(--coffee-text,#fff)}.coffee-metric>strong b{color:#6b7280;font-size:14px}.coffee-metric>small{color:#9ca3af;font-size:10px}.coffee-dashboard__layout{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(320px,.75fr);gap:18px}.coffee-dashboard__main,.coffee-dashboard__side{display:grid;align-content:start;gap:18px}.coffee-panel{overflow:hidden;border:1px solid rgba(148,163,184,.14);border-radius:20px;background:var(--coffee-panel,rgba(255,255,255,.025));box-shadow:0 10px 35px rgba(0,0,0,.045)}.coffee-panel__head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:18px 20px;border-bottom:1px solid rgba(148,163,184,.12)}.coffee-panel__head span{color:#d97706;font-size:9px;font-weight:800;letter-spacing:.15em}.coffee-panel__head h3{margin:2px 0 0;font-size:16px}.coffee-panel__head a{color:#f59e0b;font-size:11px;font-weight:700}.coffee-panel__head small{color:#9ca3af;font-size:10px}.coffee-routes{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;padding:16px}.coffee-route{border:1px solid rgba(148,163,184,.13);border-radius:15px;padding:14px}.coffee-route__top{display:flex;align-items:center;gap:10px}.coffee-route__avatar{display:grid;width:38px;height:38px;flex:none;place-items:center;border-radius:12px;background:linear-gradient(145deg,#92400e,#f59e0b);color:#fff;font-weight:900}.coffee-route__identity{display:grid;min-width:0;flex:1}.coffee-route__identity strong,.coffee-route__identity span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.coffee-route__identity strong{font-size:12px}.coffee-route__identity span{color:#9ca3af;font-size:10px}.coffee-route__status{border-radius:99px;padding:4px 7px;background:rgba(245,158,11,.12);color:#f59e0b;font-size:9px;font-weight:700}.coffee-route__numbers{display:flex;align-items:center;gap:12px;margin:14px 0 7px;color:#9ca3af;font-size:9px}.coffee-route__numbers strong{color:var(--coffee-text,#fff);font-size:12px}.coffee-route__numbers b{margin-left:auto;color:#f59e0b;font-size:10px}.coffee-progress{height:6px;overflow:hidden;border-radius:99px;background:rgba(148,163,184,.13)}.coffee-progress i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#d97706,#fbbf24)}.coffee-activity-list{padding:4px 20px 10px}.coffee-activity{display:grid;grid-template-columns:42px 10px minmax(0,1fr) auto;align-items:center;gap:10px;padding:13px 0;border-bottom:1px solid rgba(148,163,184,.1)}.coffee-activity:last-child{border:0}.coffee-activity__time{color:#9ca3af;font-size:10px}.coffee-activity__dot{width:8px;height:8px;border:2px solid #f59e0b;border-radius:50%}.coffee-activity__body{display:grid;min-width:0}.coffee-activity__body strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px}.coffee-activity__body span{color:#9ca3af;font-size:10px}.coffee-activity__money{display:grid;text-align:right}.coffee-activity__money strong{font-size:11px}.coffee-activity__money span{color:#ef4444;font-size:9px}.coffee-alerts{display:grid;gap:8px;padding:14px}.coffee-alert{display:flex;align-items:center;justify-content:space-between;gap:10px;border-radius:12px;padding:11px 12px;background:rgba(59,130,246,.09);font-size:11px}.coffee-alert strong{display:grid;min-width:28px;height:28px;place-items:center;border-radius:9px;background:rgba(59,130,246,.15);color:#60a5fa}.coffee-alert--warning{background:rgba(245,158,11,.09)}.coffee-alert--warning strong{background:rgba(245,158,11,.15);color:#f59e0b}.coffee-alert--danger{background:rgba(239,68,68,.09)}.coffee-alert--danger strong{background:rgba(239,68,68,.15);color:#ef4444}.coffee-alert--success{background:rgba(34,197,94,.09)}.coffee-alert--success strong{background:rgba(34,197,94,.15);color:#22c55e}.coffee-stock-list{display:grid;gap:12px;padding:14px}.coffee-stock-location{border:1px solid rgba(148,163,184,.12);border-radius:14px;padding:12px}.coffee-stock-location__title{display:flex;justify-content:space-between;gap:10px;margin-bottom:8px}.coffee-stock-location__title span{font-size:11px;font-weight:800}.coffee-stock-location__title strong{color:#f59e0b;font-size:10px}.coffee-stock-row{display:flex;justify-content:space-between;gap:12px;padding:7px 0;border-top:1px solid rgba(148,163,184,.09);color:#9ca3af;font-size:10px}.coffee-stock-row strong{text-align:right;color:var(--coffee-text,#fff)}.coffee-stock-location p{margin:0;color:#9ca3af;font-size:10px}.coffee-quick-actions{display:grid;padding:6px 14px 14px}.coffee-quick-actions>a{display:flex;align-items:center;gap:11px;padding:11px 0;border-bottom:1px solid rgba(148,163,184,.1)}.coffee-quick-actions>a:last-child{border:0}.coffee-quick-actions__icon{display:grid;width:36px;height:36px;flex:none;place-items:center;border-radius:11px;background:rgba(245,158,11,.12);color:#f59e0b;font-size:19px}.coffee-quick-actions>a>span:nth-child(2){display:grid;min-width:0;flex:1}.coffee-quick-actions strong{font-size:11px}.coffee-quick-actions small{overflow:hidden;color:#9ca3af;font-size:9px;text-overflow:ellipsis;white-space:nowrap}.coffee-quick-actions b{color:#9ca3af;font-size:20px}.coffee-empty{grid-column:1/-1;padding:34px 16px;text-align:center;color:#9ca3af}.coffee-empty>span{display:block;font-size:28px}.coffee-empty strong{display:block;margin-top:5px;color:var(--coffee-text,#fff);font-size:12px}.coffee-empty p{margin:4px 0 0;font-size:10px}.coffee-empty--small{padding:20px 12px}
        html:not(.dark) .coffee-dashboard{--coffee-panel:#fff;--coffee-text:#2b1b13}html:not(.dark) .coffee-panel,html:not(.dark) .coffee-metric{box-shadow:0 8px 30px rgba(65,38,15,.06)}html.dark .coffee-dashboard{--coffee-panel:#181411;--coffee-text:#f7f1eb}
        @media(max-width:1100px){.coffee-dashboard__metrics{grid-template-columns:repeat(2,1fr)}.coffee-dashboard__layout{grid-template-columns:1fr}.coffee-dashboard__side{grid-template-columns:repeat(2,minmax(0,1fr))}.coffee-panel--alerts{grid-column:1/-1}.coffee-dashboard__hero-mark{right:3%}}
        @media(max-width:767px){.fi-header-heading{font-size:21px}.coffee-dashboard{gap:12px}.coffee-dashboard__hero{min-height:auto;padding:22px 19px}.coffee-dashboard__hero h2{font-size:23px}.coffee-dashboard__hero p{font-size:12px}.coffee-dashboard__hero-mark{display:none}.coffee-dashboard__hero-actions{align-items:flex-start;flex-direction:column;gap:13px;margin-top:19px}.coffee-dashboard__metrics{grid-template-columns:1fr 1fr;gap:9px}.coffee-metric{padding:14px}.coffee-metric>strong{font-size:17px}.coffee-metric>small{display:none}.coffee-dashboard__layout,.coffee-dashboard__main,.coffee-dashboard__side{gap:12px}.coffee-dashboard__side{grid-template-columns:1fr}.coffee-panel--alerts{grid-column:auto}.coffee-routes{grid-template-columns:1fr;padding:12px}.coffee-panel__head{padding:15px}.coffee-activity-list{padding-inline:14px}.coffee-activity{grid-template-columns:36px 8px minmax(0,1fr)}.coffee-activity__money{grid-column:3;text-align:left}.coffee-stock-row{display:grid;gap:2px}.coffee-stock-row strong{text-align:left}}
    </style>
</x-filament-panels::page>
