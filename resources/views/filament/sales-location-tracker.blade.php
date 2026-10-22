@persist('pancor-global-pwa-ui')
<div id="pancor-global-pwa-ui">
<button id="pancor-pwa-install" type="button" hidden style="position:fixed;right:16px;bottom:16px;z-index:50;border:0;border-radius:12px;background:#b45309;padding:10px 14px;color:#fff;font:600 13px system-ui;box-shadow:0 10px 25px rgba(0,0,0,.25);cursor:pointer">
    Unduh Aplikasi
</button>
<div id="pancor-offline-status" role="status" aria-live="polite" hidden style="position:fixed;left:50%;bottom:16px;z-index:60;max-width:calc(100% - 32px);transform:translateX(-50%);border-radius:12px;background:#b91c1c;padding:10px 14px;color:#fff;font:600 13px/1.4 system-ui;box-shadow:0 10px 25px rgba(0,0,0,.3);text-align:center">
    Offline — transaksi dan lokasi belum dapat disimpan
</div>

</div>
@endpersist

<script data-navigate-once>
    (() => {
        let installPrompt = null;
        const floatingInstallButton = document.getElementById('pancor-pwa-install');
        const loginInstallButton = document.getElementById('pancor-pwa-login-install');
        const loginInstallLabel = loginInstallButton?.querySelector('span');
        const installMessage = document.getElementById('pancor-pwa-install-message');
        const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;
        const showInstallMessage = message => {
            if (installMessage) installMessage.textContent = message;
        };
        const markAsInstalled = () => {
            floatingInstallButton.hidden = true;
            if (loginInstallButton) loginInstallButton.disabled = true;
            if (loginInstallLabel) loginInstallLabel.textContent = 'Aplikasi Sudah Terpasang';
            showInstallMessage('Aplikasi dapat dibuka dari layar utama perangkat Anda.');
        };
        const requestInstall = async () => {
            if (isStandalone()) {
                markAsInstalled();
                return;
            }

            if (!window.isSecureContext) {
                showInstallMessage('Instalasi PWA membutuhkan akses HTTPS.');
                return;
            }

            if (installPrompt) {
                await installPrompt.prompt();
                const choice = await installPrompt.userChoice;
                installPrompt = null;
                floatingInstallButton.hidden = true;
                showInstallMessage(choice.outcome === 'accepted'
                    ? 'Instalasi sedang diproses oleh browser.'
                    : 'Instalasi dibatalkan. Anda dapat mencobanya kembali dari menu browser.');
                return;
            }

            const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
            showInstallMessage(isIos
                ? 'Di Safari, tekan Bagikan lalu pilih Tambahkan ke Layar Utama.'
                : 'Buka menu browser lalu pilih Instal aplikasi atau Tambahkan ke layar utama.');
        };
        const offlineStatus = document.getElementById('pancor-offline-status');
        const updateConnectionStatus = () => offlineStatus.hidden = navigator.onLine;
        updateConnectionStatus();
        window.addEventListener('online', updateConnectionStatus);
        window.addEventListener('offline', updateConnectionStatus);
        window.addEventListener('beforeinstallprompt', event => {
            event.preventDefault();
            installPrompt = event;
            if (!loginInstallButton) floatingInstallButton.hidden = false;
            showInstallMessage('Aplikasi siap dipasang pada perangkat ini.');
        });
        floatingInstallButton?.addEventListener('click', requestInstall);
        loginInstallButton?.addEventListener('click', requestInstall);
        window.addEventListener('appinstalled', markAsInstalled);
        if (isStandalone()) markAsInstalled();

        if ('serviceWorker' in navigator && window.isSecureContext) {
            window.addEventListener('load', () => navigator.serviceWorker.register(@js(asset('service-worker.js'))).catch(() => {}), { once: true });
        }

        @if (auth()->user()?->isSales())
            if (!window.isSecureContext || !('geolocation' in navigator)) {
                // Geofence hanya tersedia di secure context. Beri tahu pengguna
                // agar tidak mengira aplikasi rusak.
                console.warn('[GPS] Geolocation diblokir browser: halaman harus diakses lewat HTTPS.');
                return;
            }

            let lastSentAt = 0;
            let sending = false;
            const sendLocation = async ({ coords }, force = false) => {
                if (sending || (!force && Date.now() - lastSentAt < 120000)) return;
                sending = true;
                try {
                    const response = await fetch(@js(route('internal.sales-location.update')), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': @js(csrf_token()),
                        },
                        body: JSON.stringify({
                            latitude: coords.latitude,
                            longitude: coords.longitude,
                            accuracy: coords.accuracy,
                            altitude: coords.altitude,
                            heading: coords.heading,
                            speed: coords.speed,
                        }),
                    });

                    if (response.ok) lastSentAt = Date.now();
                } catch (_) {
                    // Akan dicoba lagi ketika aplikasi dibuka kembali.
                } finally {
                    sending = false;
                }
            };

            navigator.geolocation.getCurrentPosition(position => sendLocation(position, true), () => {}, {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 60000,
            });
            navigator.geolocation.watchPosition(position => sendLocation(position), () => {}, {
                enableHighAccuracy: true,
                timeout: 30000,
                maximumAge: 60000,
            });
        @endif
    })();
</script>
