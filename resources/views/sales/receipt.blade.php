<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $visit->store->name }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#eee;color:#111;font:12px/1.4 ui-monospace,SFMono-Regular,Consolas,monospace}.receipt{width:58mm;min-height:80mm;margin:16px auto;background:#fff;padding:4mm}.center{text-align:center}.brand{font-size:16px;font-weight:800}.muted{color:#555}.rule{margin:8px 0;border-top:1px dashed #222}.row{display:flex;justify-content:space-between;gap:8px;padding:2px 0}.row span:last-child{text-align:right}.total{font-size:14px;font-weight:800}.actions{display:flex;gap:8px;justify-content:center;margin:16px}.actions button{border:0;border-radius:8px;padding:10px 16px;background:#b45309;color:#fff;font-weight:700}.actions button:last-child{background:#333}@page{size:58mm auto;margin:0}@media print{body{background:#fff}.receipt{width:58mm;margin:0;padding:3mm}.actions{display:none}}
    </style>
</head>
<body>
    <main class="receipt">
        <div class="center"><div class="brand">PANCOR MAS</div><div>Kopi Bubuk</div><div class="muted">Bukti Pembayaran Toko</div></div>
        <div class="rule"></div>
        <div>{{ $visit->completed_at?->format('d/m/Y H:i') }}</div>
        <div>Toko: {{ $visit->store->name }}</div>
        <div>Sales: {{ $visit->sales->name }}</div>
        <div>No: KJN-{{ str_pad($visit->id, 6, '0', STR_PAD_LEFT) }}</div>
        <div class="rule"></div>
        @foreach ($visit->items as $item)
            <strong>{{ $item->product->name }}</strong>
            <div class="row"><span>Laku {{ $item->sold_pieces }} bungkus</span><span>Rp {{ number_format($item->amount_due, 0, ',', '.') }}</span></div>
            @if ($item->deposited_pieces > 0)<div class="row muted"><span>Dititipkan</span><span>{{ $item->product->formatStockQuantity($item->deposited_pieces) }}</span></div>@endif
            @if ($item->withdrawn_pieces > 0)<div class="row muted"><span>Ditarik</span><span>{{ $item->product->formatStockQuantity($item->withdrawn_pieces) }}</span></div>@endif
            <div class="row muted"><span>Fee toko</span><span>Rp {{ number_format($item->store_fee, 0, ',', '.') }}</span></div>
        @endforeach
        <div class="rule"></div>
        <div class="row"><span>Tagihan transaksi</span><span>Rp {{ number_format($visit->amount_due, 0, ',', '.') }}</span></div>
        <div class="row"><span>Bayar transaksi</span><span>Rp {{ number_format($visit->current_payment_amount, 0, ',', '.') }}</span></div>
        @if ($visit->opening_debt > 0)<div class="row"><span>Hutang sebelumnya</span><span>Rp {{ number_format($visit->opening_debt, 0, ',', '.') }}</span></div>@endif
        @if ($visit->debt_payment_amount > 0)<div class="row"><span>Bayar hutang lama</span><span>Rp {{ number_format($visit->debt_payment_amount, 0, ',', '.') }}</span></div>@endif
        <div class="row total"><span>DIBAYARKAN</span><span>Rp {{ number_format($visit->amount_paid, 0, ',', '.') }}</span></div>
        <div class="row"><span>Sisa seluruh hutang</span><span>Rp {{ number_format($visit->closing_debt, 0, ',', '.') }}</span></div>
        <div class="rule"></div>
        <div class="center muted">Terima kasih<br>Struk ini sah sebagai bukti pembayaran.</div>
    </main>
    <div class="actions"><button onclick="window.print()">Cetak Struk</button><button onclick="window.close()">Tutup</button></div>
</body>
</html>
