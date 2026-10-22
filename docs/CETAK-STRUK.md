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
| **Android / Chrome** | **Web Bluetooth** (`navigator.bluetooth`, hanya BLE) | Tidak |
| **Windows** | **Print agent lokal** (`http://127.0.0.1:17777`) | Ya (sekali) |

Jalur dapat dipilih lewat dropdown **Jalur cetak** (Otomatis / Print agent / USB /
Bluetooth). Pilihan disimpan di `localStorage` perangkat.

Urutan pada mode **Otomatis**: print agent → WebUSB → Web Bluetooth.

> **Mengapa Bluetooth sering tidak muncul?** Printer thermal EPPOS umumnya
> memakai **Bluetooth Classic (SPP)**, sedangkan Web Bluetooth di browser hanya
> mendukung **BLE**. Bila printer tidak tampil di dialog pemilihan, berarti ia
> memakai Classic — gunakan kabel USB atau print agent.
>
> Cara memastikan printer Anda BLE: cek aplikasi Android pihak ketiga seperti
> *nRF Connect*. Jika perangkat muncul beserta service/characteristic di sana,
> ia BLE. Jika hanya terlihat sebagai perangkat Bluetooth biasa, ia Classic.

### Catatan teknis penting

`requestDevice()` pada WebUSB maupun Web Bluetooth **wajib** dipanggil langsung
di dalam event klik. Chrome menolak dengan *"Must be handling a user gesture"*
bila didahului `await`. Karena itu aplikasi memilih perangkat **lebih dulu**,
baru mengambil byte struk dari server.

### UUID BLE yang dicoba

Service: `18f0`, `ff00`, `ffe0`, `fff0`, `49535343-fe7d-4ae5-8fa9-9fafd205e455`,
`e7810a71-73ae-499d-8c15-faa9aef0c3f2`.
Characteristic tulis: `2af1`, `ff02`, `ffe1`, `49535343-8841-43f4-a8d4-ecbe34729bb3`.
Jika tidak ada yang cocok, aplikasi memindai seluruh layanan dan memakai
characteristic pertama yang mendukung `write`.

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

## Android: Bluetooth (BLE saja)

- Buka lewat **HTTPS**.
- Pasangkan (pair) printer di pengaturan Bluetooth Android terlebih dahulu.
- Pilih **Jalur cetak → Bluetooth**, lalu tekan **Cetak Struk**.
- Pilih printer pada dialog Chrome.

Ukuran tulis per paket: 20 byte (with response) atau 180 byte
(without response), dengan jeda 20ms agar buffer printer tidak penuh.

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
| Tombol meminta izin terus | Izin WebUSB/Bluetooth per-origin; pastikan tetap di domain yang sama dan HTTPS. |
| Printer Bluetooth tidak muncul di dialog | Printer memakai Bluetooth Classic (SPP), bukan BLE. Gunakan USB atau print agent. |
| Bluetooth tersambung tapi struk tidak keluar | Characteristic salah atau MTU kecil. Coba jalur USB; laporkan UUID printer dari aplikasi *nRF Connect*. |
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
