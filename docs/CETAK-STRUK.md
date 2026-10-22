# Cetak struk langsung ke printer thermal (EPPOS USB)

Aplikasi dapat mengirim struk **langsung** ke printer thermal ESC/POS tanpa
dialog Ctrl+P. Tombol **🖨️ Cetak Struk** di halaman *Transaksi Toko* akan
memilih jalur yang tersedia secara otomatis.

## Cara kerja

1. Server menyusun byte ESC/POS di `app/Services/EscPos/ReceiptEscPosBuilder.php`
   dan menyajikannya di `GET /sales/receipts/{visit}/escpos`
   (`Content-Type: application/octet-stream`).
2. Browser mengambil byte tersebut, lalu mengirimkannya lewat salah satu jalur:

| Perangkat | Jalur | Perlu instal? |
|---|---|---|
| **Android / Chrome** | **WebUSB** (`navigator.usb`) | Tidak |
| **Windows** | **Print agent lokal** (`http://127.0.0.1:17777`) | Ya (sekali) |

Urutan percobaan: **print agent dulu**, lalu **WebUSB**. Jika keduanya gagal,
aplikasi menawarkan membuka tampilan struk untuk dicetak manual.

> **Mengapa bukan Web Bluetooth?** Printer thermal EPPOS umumnya memakai
> **Bluetooth Classic (SPP)**, sedangkan Web Bluetooth di browser hanya
> mendukung **BLE**. Jadi Bluetooth tidak bisa dipakai langsung dari halaman web.

## Windows: menjalankan print agent

1. Pasang printer EPPOS sampai muncul di **Printers & scanners**.
2. Jalankan:

```powershell
powershell -ExecutionPolicy Bypass -File tools\print-agent\agent.ps1
```

3. Jika nama printer tidak terdeteksi otomatis, sebutkan eksplisit:

```powershell
powershell -ExecutionPolicy Bypass -File tools\print-agent\agent.ps1 -PrinterName "POS-58"
```

Agent mendengarkan di `http://127.0.0.1:17777` (hanya localhost):

- `GET /health` → status + daftar printer
- `POST /print` → body byte ESC/POS dikirim **mentah** ke printer

Agent memilih printer secara berurutan: nama yang diberikan → nama mengandung
`POS/THERMAL/EPPOS/RECEIPT/58/80` → printer default Windows.

### Menjalankan otomatis saat Windows menyala

Buat Task Scheduler, trigger **At log on**, action:

```
powershell -ExecutionPolicy Bypass -WindowStyle Hidden -File "<path>\tools\print-agent\agent.ps1"
```

## Android: WebUSB

- Buka aplikasi lewat **HTTPS** (WebUSB wajib secure context).
- Sambungkan printer lewat **USB Type-C / OTG**.
- Tekan **Cetak Struk** → pilih perangkat printer pada dialog Chrome → izinkan.
- Izin tersimpan per-origin, jadi cukup sekali per perangkat.

Jika printer tidak muncul di dialog, cek `chrome://device-log` atau pastikan
kabel OTG mendukung transfer data (bukan hanya charging).

## Pengaturan kertas

```env
RECEIPT_PAPER_WIDTH_MM=58   # ganti 80 untuk printer 80mm
RECEIPT_STORE_NAME="PANCOR MAS"
RECEIPT_STORE_TAGLINE="Kopi Bubuk"
RECEIPT_FOOTER="Terima kasih"
```

Area cetak mengikuti lebar kertas: **58mm → 32 karakter**, **80mm → 48 karakter**.
Baris yang terlalu panjang dibungkus otomatis, tidak dipotong.

## Pemecahan masalah

| Gejala | Sebab & solusi |
|---|---|
| Struk keluar berupa teks acak | Driver bukan mode RAW. Pakai print agent (sudah RAW), atau ganti driver ke "POS-58/Generic". |
| Tombol meminta izin terus | Izin WebUSB per-origin; pastikan tetap di domain yang sama dan HTTPS. |
| Agent tidak terdeteksi | Pastikan `agent.ps1` berjalan; cek `http://127.0.0.1:17777/health` di browser. |
| Port 17777 dipakai | Jalankan agent dengan `-Port 17778` lalu sesuaikan `AGENT_URL` di `resources/js/receipt-printer.js`. |
| Kolom kanan terpotong | Ukuran kertas di `.env` tidak sesuai printer (58 vs 80). |
| Teks berbahasa Indonesia rusak | Printer memakai code page CP437; karakter non-ASCII ditransliterasi otomatis. |

## Pengujian

```bash
php artisan test --filter=ReceiptEscPosTest
```

Mencakup validitas byte (`ESC @` awal, potong kertas akhir), panjang baris per
lebar kertas, pembungkusan nama produk panjang, transliterasi karakter, serta
hak akses endpoint.
