@if (auth()->user()?->isSales())
    <div id="pancor-gps-gate" class="pancor-gps-gate pancor-gps-gate--locked" role="dialog" aria-modal="true" aria-labelledby="pancor-gps-gate-title" aria-hidden="false">
        <div class="pancor-gps-gate__backdrop" aria-hidden="true"></div>
        <div class="pancor-gps-gate__card">
            <div class="pancor-gps-gate__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11Z"/>
                    <circle cx="12" cy="10" r="2.6"/>
                </svg>
            </div>
            <h2 id="pancor-gps-gate-title">GPS Belum Diaktifkan</h2>
            <p>Lokasi wajib aktif sebelum Anda masuk ke aplikasi. Nyalakan GPS atau izinkan akses lokasi pada perangkat/browser Anda, lalu tekan tombol di bawah.</p>
            <button type="button" class="pancor-gps-gate__retry" data-gps-retry>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 12a9 9 0 1 1-2.64-6.36"/>
                    <path d="M21 3v6h-6"/>
                </svg>
                Coba Lagi
            </button>
            <p class="pancor-gps-gate__hint">Jika tetap tidak muncul, pastikan aplikasi dibuka melalui HTTPS dan izin lokasi diizinkan.</p>
        </div>
    </div>

    <script data-navigate-once>
        (() => {
            const gate = document.getElementById('pancor-gps-gate');
            if (!gate) return;

            const unlock = () => { gate.classList.remove('pancor-gps-gate--locked'); gate.setAttribute('aria-hidden', 'true'); };
            const lock = () => { gate.classList.add('pancor-gps-gate--locked'); gate.setAttribute('aria-hidden', 'false'); };

            const check = () => {
                if (!('geolocation' in navigator) || !window.isSecureContext) {
                    lock();
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    unlock,
                    lock,
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 },
                );
            };

            // Auto-buka saat GPS dinyalakan di tengah sesi.
            if ('geolocation' in navigator && window.isSecureContext) {
                navigator.geolocation.watchPosition(unlock, () => {}, {
                    enableHighAccuracy: true,
                    timeout: 30000,
                    maximumAge: 30000,
                });
            }

            gate.querySelector('[data-gps-retry]')?.addEventListener('click', check);

            check();
        })();
    </script>

    <style>
        .pancor-gps-gate{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .25s ease,visibility .25s ease}
        .pancor-gps-gate--locked{opacity:1;visibility:visible;pointer-events:auto}
        .pancor-gps-gate__backdrop{position:absolute;inset:0;background:rgba(24,20,17,.55);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}
        .pancor-gps-gate__card{position:relative;z-index:1;max-width:400px;width:100%;border:1px solid rgba(255,255,255,.14);border-radius:22px;background:#fff;padding:32px 28px;text-align:center;box-shadow:0 30px 80px rgba(0,0,0,.35);color:#1f2937}
        html.dark .pancor-gps-gate__card{background:#1c1917;color:#f5f5f4}
        .pancor-gps-gate__icon{display:grid;place-items:center;width:72px;height:72px;margin:0 auto 18px;border-radius:22px;background:linear-gradient(145deg,#c97832,#92400e);color:#fff}
        .pancor-gps-gate__icon svg{width:38px;height:38px}
        .pancor-gps-gate__card h2{margin:0 0 10px;font-size:20px;font-weight:800}
        .pancor-gps-gate__card p{margin:0 0 20px;font-size:14px;line-height:1.6;opacity:.78}
        .pancor-gps-gate__retry{display:inline-flex;align-items:center;justify-content:center;gap:9px;border:0;border-radius:13px;background:#c97832;padding:13px 22px;color:#fff;font:700 14px/1 system-ui;cursor:pointer;box-shadow:0 10px 25px rgba(201,120,50,.35);transition:transform .12s ease,filter .12s ease}
        .pancor-gps-gate__retry:hover{filter:brightness(1.06)}
        .pancor-gps-gate__retry:active{transform:scale(.97)}
        .pancor-gps-gate__retry svg{width:18px;height:18px}
        .pancor-gps-gate__hint{margin:18px 0 0 !important;font-size:12px !important;opacity:.55 !important}
    </style>
@endif
