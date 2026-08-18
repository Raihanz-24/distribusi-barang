# Sistem Distribusi Pancor Mas

Aplikasi Laravel 12 + Filament 5 untuk mengelola produk, stok, gudang, area dan rute, distribusi harian sales, transaksi konsinyasi toko, hutang toko, setoran, peta, dan audit aktivitas.

## Alur utama

1. Admin mencatat hasil produksi ke Gudang Utama.
2. Stok dipindahkan antar gudang dan kemudian dikirim kepada Sales untuk jadwal tertentu.
3. Sales mengunjungi toko dari halaman **Transaksi Toko**, mencatat barang laku, barang dititipkan/ditarik, pembayaran transaksi, dan pembayaran hutang lama.
4. Sales menekan selesai untuk mengajukan pengembalian.
5. Admin memeriksa uang fisik dan stok fisik. Selisih wajib diberi catatan.
6. Setelah disetujui, setoran masuk ke rekap dan rute dinyatakan selesai.

Semua kuantitas stok disimpan dalam bungkus. Tampilan dapat dikonversi ke renteng sesuai isi renteng pada produk. Harga baru membuat riwayat baru tanpa mengubah transaksi lama.

## Mulai development

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
php artisan serve --host=0.0.0.0 --port=8000
```

Isi `SEED_ADMIN_PASSWORD` sebelum menjalankan seeder. Jangan pernah menjalankan `migrate:fresh` pada database operasional.

Dokumentasi rinci:

- [Setup, peta, dan PWA](docs/FASE-1-SETUP.md)
- [Operasional dan deployment](docs/OPERATIONS.md)

## Pemeriksaan

```bash
php artisan test
php artisan pancor:check
php artisan about
```

`pancor:check` mendeteksi saldo stok yang tidak sesuai mutasi, stok negatif, produk tanpa tepat satu harga aktif, masalah database, dan tidak adanya Admin aktif.
