<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $visit->store->name }}</title>
    <style>
        /*
         * Struk ini dirancang untuk printer thermal ESC/POS (mis. EPPOS 58mm/80mm).
         * Lebar isi sengaja lebih kecil dari lebar kertas karena kepala cetak tidak
         * pernah selebar kertas. Jika melebar 58mm penuh, kolom kanan terpotong.
         */
        *{box-sizing:border-box}
        body{margin:0;background:#eee;color:#000;font:12px/1.45 ui-monospace,SFMono-Regular,Consolas,"Courier New",monospace;-webkit-print-color-adjust:exact;print-color-adjust:exact}
        .receipt{width:{{ $paperWidth }}mm;min-height:80mm;margin:16px auto;background:#fff;padding:3mm 2mm}
        .center{text-align:center}
        .brand{font-size:15px;font-weight:800;letter-spacing:.5px}
        .muted{color:#333}
        .rule{margin:6px 0;border-top:1px dashed #000}
        .row{display:flex;justify-content:space-between;gap:6px;padding:1px 0}
        .row span:last-child{text-align:right;white-space:nowrap}
        .name{font-weight:700;word-break:break-word}
        .total{font-size:13px;font-weight:800;border-top:1px solid #000;margin-top:4px;padding-top:4px}
        .actions{display:flex;gap:8px;justify-content:center;margin:16px}
        .actions button{border:0;border-radius:8px;padding:10px 16px;background:#b45309;color:#fff;font-weight:700;cursor:pointer}
        .actions button:last-child{background:#333}
        @page{size:{{ $paperWidth }}mm auto;margin:0}
        @media print{
            body{background:#fff}
            .receipt{width:{{ $paperWidth }}mm;margin:0;padding:2mm 1mm;min-height:0}
            .actions{display:none}
        }
    </style>
</head>
<body>
    <main class="receipt">
        <div class="center">
            <div class="brand">{{ $storeName }}</div>
            @if ($storeTagline)<div>{{ $storeTagline }}</div>@endif
            <div class="muted">Bukti Pembayaran Toko</div>
        </div>

        <div class="rule"></div>

        <div>{{ $visit->completed_at?->format('d/m/Y H:i') ?? $visit->arrived_at?->format('d/m/Y H:i') }}</div>
        <div class="name">Toko: {{ $visit->store->name }}</div>
        <div>Sales: {{ $visit->sales->name }}</div>
        <div>No: KJN-{{ str_pad($visit->id, 6, '0', STR_PAD_LEFT) }}</div>

        <div class="rule"></div>

        @foreach ($visit->items as $item)
            <div class="name">{{ $item->product->name }}</div>
            <div class="row">
                <span>Laku {{ number_format($item->sold_pieces) }} bks</span>
                <span>Rp {{ number_format($item->amount_due, 0, ',', '.') }}</span>
            </div>
            @if ($item->deposited_pieces > 0)
                <div class="row muted"><span>Dititipkan</span><span>{{ $item->product->formatStockQuantity($item->deposited_pieces) }}</span></div>
            @endif
            @if ($item->withdrawn_pieces > 0)
                <div class="row muted"><span>Ditarik</span><span>{{ $item->product->formatStockQuantity($item->withdrawn_pieces) }}</span></div>
            @endif
            <div class="row muted"><span>Fee toko</span><span>Rp {{ number_format($item->store_fee, 0, ',', '.') }}</span></div>
        @endforeach

        <div class="rule"></div>

        <div class="row"><span>Tagihan transaksi</span><span>Rp {{ number_format($visit->amount_due, 0, ',', '.') }}</span></div>
        <div class="row"><span>Bayar transaksi</span><span>Rp {{ number_format($visit->current_payment_amount, 0, ',', '.') }}</span></div>
        @if ($visit->opening_debt > 0)
            <div class="row"><span>Hutang sebelumnya</span><span>Rp {{ number_format($visit->opening_debt, 0, ',', '.') }}</span></div>
        @endif
        @if ($visit->debt_payment_amount > 0)
            <div class="row"><span>Bayar hutang lama</span><span>Rp {{ number_format($visit->debt_payment_amount, 0, ',', '.') }}</span></div>
        @endif
        <div class="row total"><span>DIBAYARKAN</span><span>Rp {{ number_format($visit->amount_paid, 0, ',', '.') }}</span></div>
        <div class="row"><span>Sisa hutang</span><span>Rp {{ number_format($visit->closing_debt, 0, ',', '.') }}</span></div>

        <div class="rule"></div>

        <div class="center muted">{{ $footer }}<br>Struk ini sah sebagai bukti pembayaran.</div>
    </main>

    <div class="actions">
        <button type="button" onclick="window.print()">Cetak Struk</button>
        <button type="button" onclick="window.close()">Tutup</button>
    </div>

    <script>
        // Setelah halaman selesai dimuat, langsung buka dialog cetak agar sales
        // tidak perlu klik dua kali. Ditunda sedikit supaya font sempat termuat.
        window.addEventListener('load', function () {
            if (new URLSearchParams(window.location.search).has('auto')) {
                setTimeout(function () { window.print(); }, 300);
            }
        });
    </script>
</body>
</html>
