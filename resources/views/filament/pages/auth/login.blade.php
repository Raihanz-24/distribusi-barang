<div class="coffee-login-shell">
    <section class="coffee-login-story" aria-label="Pancor Mas">
        <div class="coffee-login-glow"></div>
        <div class="coffee-login-brand">
            <span class="coffee-login-mark" aria-hidden="true">
                <svg viewBox="0 0 64 64" fill="none">
                    <path d="M46 11C28 9 14 20 14 36c0 10 8 18 18 18 17 0 25-17 21-36-1-4-3-6-7-7Z" fill="currentColor" opacity=".96"/>
                    <path d="M44 15c-3 13-12 24-25 31" stroke="#2a160d" stroke-width="4" stroke-linecap="round"/>
                </svg>
            </span>
            <span>Pancor Mas</span>
        </div>

        <div class="coffee-login-copy">
            <p class="coffee-login-kicker">Sistem Distribusi Produk</p>
            <h1>Kelola distribusi kopi dalam satu alur yang rapi.</h1>
            <p>Pantau stok, toko, rute, dan aktivitas Sales dengan data yang selalu terhubung.</p>
        </div>

        <div class="coffee-login-origin">
            <span class="coffee-login-origin-dot"></span>
            <div>
                <strong>Dari produksi hingga toko</strong>
                <span>Setiap pergerakan tercatat dan mudah dipantau.</span>
            </div>
        </div>
    </section>

    <section class="coffee-login-panel">
        <div class="coffee-login-mobile-brand">Pancor Mas</div>
        <p class="coffee-login-eyebrow">Selamat datang kembali</p>
        <h2>Masuk ke akun Anda</h2>
        <p class="coffee-login-help">Gunakan username dan kata sandi yang diberikan Admin.</p>

        <div class="coffee-login-form">
            {{ $this->content }}
        </div>

        <div class="coffee-login-install">
            <span>atau gunakan sebagai aplikasi di HP</span>
            <button id="pancor-pwa-login-install" type="button">
                <x-filament::icon icon="heroicon-o-arrow-down-tray" />
                <span>Unduh Aplikasi</span>
            </button>
            <p id="pancor-pwa-install-message" role="status" aria-live="polite"></p>
        </div>

        <p class="coffee-login-security">
            <x-filament::icon icon="heroicon-o-shield-check" />
            Sesi masuk dilindungi dan dibatasi dari percobaan berulang.
        </p>
    </section>

    <x-filament-actions::modals />
</div>

@once
    <style>
        .fi-simple-layout { background: #120b07 !important; color-scheme: dark; }
        .fi-simple-main { width: min(1120px, calc(100% - 32px)) !important; margin-block: 0 !important; padding: 32px 0 !important; border: 0 !important; background: transparent !important; box-shadow: none !important; }
        .fi-simple-page, .fi-simple-page-content { width: 100%; max-width: none !important; background: transparent !important; box-shadow: none !important; }
        .coffee-login-shell { min-height: min(720px, calc(100vh - 64px)); display: grid; grid-template-columns: 1.12fr .88fr; overflow: hidden; border: 1px solid rgba(255,255,255,.09); border-radius: 28px; background: #1b110c; box-shadow: 0 32px 90px rgba(0,0,0,.42); color: #f8f2ed; }
        .coffee-login-story { position: relative; isolation: isolate; display: flex; flex-direction: column; justify-content: space-between; padding: 48px; overflow: hidden; background: radial-gradient(circle at 15% 15%, rgba(217,145,70,.30), transparent 34%), linear-gradient(145deg,#4b2818 0%,#2a160e 54%,#160c08 100%); }
        .coffee-login-story::after { content: ''; position: absolute; z-index: -1; width: 440px; height: 440px; right: -160px; bottom: -170px; border: 70px solid rgba(255,255,255,.035); border-radius: 50%; }
        .coffee-login-glow { position: absolute; z-index: -1; inset: auto auto -120px -100px; width: 340px; height: 340px; border-radius: 50%; background: rgba(203,119,48,.13); filter: blur(10px); }
        .coffee-login-brand { display: flex; align-items: center; gap: 13px; font-size: 20px; font-weight: 800; letter-spacing: -.02em; }
        .coffee-login-mark { display: grid; width: 44px; height: 44px; place-items: center; border-radius: 14px; background: #e89a43; color: #f9c77e; box-shadow: 0 10px 30px rgba(232,154,67,.22); }
        .coffee-login-mark svg { width: 29px; height: 29px; }
        .coffee-login-copy { max-width: 570px; margin: 80px 0; }
        .coffee-login-kicker,.coffee-login-eyebrow { margin: 0 0 14px; color: #f0a954; font-size: 12px; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; }
        .coffee-login-copy h1 { margin: 0; max-width: 540px; font-size: clamp(36px,4.6vw,59px); line-height: 1.04; letter-spacing: -.045em; }
        .coffee-login-copy > p:last-child { max-width: 500px; margin: 24px 0 0; color: #d8c5b8; font-size: 16px; line-height: 1.75; }
        .coffee-login-origin { display: flex; align-items: center; gap: 14px; color: #f4e9df; }
        .coffee-login-origin-dot { width: 10px; height: 10px; border-radius: 50%; background: #efa54d; box-shadow: 0 0 0 7px rgba(239,165,77,.12); }
        .coffee-login-origin div { display: grid; gap: 3px; }
        .coffee-login-origin strong { font-size: 13px; }
        .coffee-login-origin span { color: #bda89b; font-size: 12px; }
        .coffee-login-panel { display: flex; flex-direction: column; justify-content: center; padding: clamp(34px,5vw,70px); background: linear-gradient(160deg,#211712,#17100c); }
        .coffee-login-panel h2 { margin: 0; color: #fffaf5; font-size: clamp(28px,3vw,38px); letter-spacing: -.035em; }
        .coffee-login-help { margin: 12px 0 34px; color: #a9988d; font-size: 14px; line-height: 1.6; }
        .coffee-login-form .fi-fo-field-label-content,
        .coffee-login-form .fi-checkbox-label,
        .coffee-login-form label { color: #eadfd7 !important; }
        .coffee-login-form .fi-fo-field-label-required-mark { color: #f2a75b !important; }
        .fi-simple-layout .coffee-login-form .fi-input-wrp { border: 1px solid rgba(255,255,255,.13) !important; background: rgba(255,255,255,.055) !important; box-shadow: inset 0 1px 0 rgba(255,255,255,.025) !important; }
        .fi-simple-layout .coffee-login-form .fi-input-wrp:focus-within { border-color: #d98a36 !important; background: rgba(255,255,255,.075) !important; box-shadow: 0 0 0 3px rgba(217,138,54,.13) !important; }
        .coffee-login-form .fi-input { background: transparent !important; color: #fffaf5 !important; caret-color: #eda24d; }
        .coffee-login-form .fi-input::placeholder { color: #8f8076 !important; opacity: 1; }
        .coffee-login-form .fi-input-wrp-prefix,
        .coffee-login-form .fi-input-wrp-suffix { border-color: rgba(255,255,255,.09) !important; }
        .coffee-login-form .fi-input-wrp-prefix > .fi-icon,
        .coffee-login-form .fi-input-wrp-suffix > .fi-icon,
        .coffee-login-form .fi-input-wrp-actions .fi-icon { color: #bc9f8b !important; }
        .coffee-login-form .fi-checkbox-input { border-color: rgba(255,255,255,.2) !important; background-color: rgba(255,255,255,.06) !important; color: #d98437 !important; }
        .coffee-login-form .fi-fo-field-wrp-error-message { color: #fca5a5 !important; }
        .coffee-login-form input:-webkit-autofill,
        .coffee-login-form input:-webkit-autofill:hover,
        .coffee-login-form input:-webkit-autofill:focus { border-radius: inherit; -webkit-text-fill-color: #fffaf5 !important; caret-color: #eda24d; box-shadow: 0 0 0 1000px #251a14 inset !important; transition: background-color 9999s ease-out 0s; }
        .coffee-login-form .fi-btn { min-height: 46px; border-radius: 12px; background: linear-gradient(135deg,#e99a42,#c66e25); color: #211108; font-weight: 800; box-shadow: 0 12px 28px rgba(198,110,37,.22); }
        .coffee-login-install { display: grid; gap: 10px; margin-top: 24px; text-align: center; }
        .coffee-login-install > span { display: flex; align-items: center; gap: 10px; color: #7f7168; font-size: 10px; }
        .coffee-login-install > span::before,.coffee-login-install > span::after { height: 1px; flex: 1; background: rgba(255,255,255,.09); content: ''; }
        #pancor-pwa-login-install { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; gap: 9px; border: 1px solid rgba(232,154,67,.38); border-radius: 12px; padding: 10px 16px; background: rgba(232,154,67,.08); color: #f0ad5f; font-size: 13px; font-weight: 800; cursor: pointer; transition: border-color .2s ease,background .2s ease,transform .2s ease; }
        #pancor-pwa-login-install:hover { border-color: #e89a43; background: rgba(232,154,67,.14); transform: translateY(-1px); }
        #pancor-pwa-login-install:disabled { cursor: default; opacity: .7; transform: none; }
        #pancor-pwa-login-install svg { width: 18px; height: 18px; }
        #pancor-pwa-install-message { min-height: 0; margin: 0; color: #a9988d; font-size: 10px; line-height: 1.5; }
        #pancor-pwa-install-message:empty { display: none; }
        .coffee-login-security { display: flex; align-items: center; gap: 8px; margin: 26px 0 0; color: #86786f; font-size: 11px; line-height: 1.5; }
        .coffee-login-security svg { width: 16px; height: 16px; flex: none; color: #b7814b; }
        .coffee-login-mobile-brand { display: none; color: #eda24d; font-size: 18px; font-weight: 800; }
        @media (max-width: 820px) { .fi-simple-main { width: min(520px,calc(100% - 24px)) !important; padding: 20px 0 !important; } .coffee-login-shell { min-height: auto; grid-template-columns: 1fr; border-radius: 22px; } .coffee-login-story { display: none; } .coffee-login-panel { min-height: calc(100vh - 40px); padding: 34px 26px; } .coffee-login-mobile-brand { display: block; margin-bottom: auto; } .coffee-login-eyebrow { margin-top: 72px; } .coffee-login-security { margin-top: auto; padding-top: 42px; } }
        @media (prefers-reduced-motion: reduce) { * { scroll-behavior: auto !important; transition-duration: .01ms !important; } }
    </style>
@endonce
