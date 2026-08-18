# Setup aplikasi, peta, dan PWA

## Kebutuhan

- PHP 8.2 atau lebih baru dengan ekstensi MySQL, mbstring, intl, dan fileinfo.
- MySQL/MariaDB untuk data operasional.
- Node.js dan npm untuk membangun aset.
- HTTPS untuk geolocation pada HP dan pemasangan PWA.

## Instalasi lokal

1. Salin `.env.example` menjadi `.env`.
2. Atur database, URL aplikasi, dan `SEED_ADMIN_PASSWORD` yang kuat.
3. Jalankan:

```bash
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
php artisan optimize:clear
```

Seeder lokal membuat data demo. Pada production, seeder tidak membuat data demo dan akan menolak berjalan jika `SEED_ADMIN_PASSWORD` kosong.

## Testing dari HP di jaringan lokal

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Buka `http://IP-KOMPUTER:8000/admin` dari HP yang berada pada Wi-Fi yang sama. Izinkan port 8000 pada Windows Firewall jika diperlukan.

Catatan: sebagian browser hanya mengizinkan GPS pada HTTPS atau `localhost`. Akses melalui IP lokal dengan HTTP dapat membuka aplikasi, tetapi GPS/PWA mungkin diblokir. Untuk pengujian GPS gunakan HTTPS lokal melalui reverse proxy atau tunnel yang dipercaya.

## Konfigurasi peta

- `MAP_STYLE_URL`: style vector MapLibre milik provider atau server sendiri.
- `MAP_DEFAULT_LATITUDE` dan `MAP_DEFAULT_LONGITUDE`: pusat awal peta.
- `NEARBY_STORE_RADIUS_METERS`: radius toko sekitar lokasi sales.
- `ROUTING_PROVIDER=local`: optimasi lokal Haversine, nearest-neighbor, dan 2-opt.
- `ROUTING_PROVIDER=osrm`: gunakan `OSRM_BASE_URL` milik sendiri.

Public demo routing tidak disarankan untuk production. Aplikasi menyimpan marker toko internal dan tetap menampilkan atribusi sumber peta.

## Perilaku PWA

- PWA menyimpan aset statis dan halaman penjelasan offline.
- Halaman yang berisi data akun tidak disimpan ke cache browser.
- Saat offline, transaksi dan lokasi tidak dianggap tersimpan. Sales harus kembali online dan memeriksa riwayat sebelum mengulangi tindakan.
- Lokasi terakhir hanya diperbarui untuk Sales dengan pengiriman aktif. Riwayat pergerakan lengkap tidak disimpan.

## Akun dan akses

- Satu orang harus menggunakan satu akun.
- Username dinormalisasi menjadi huruf kecil.
- Kata sandi minimal delapan karakter serta mengandung huruf dan angka.
- Akun yang tidak lagi bertugas harus dinonaktifkan, bukan dipakai ulang.
- Sistem mencegah Admin menurunkan akses dirinya sendiri dan mencegah hilangnya Admin aktif terakhir.
