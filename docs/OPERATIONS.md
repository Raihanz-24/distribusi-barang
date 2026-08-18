# Operasional dan deployment

## Checklist sebelum production

1. Gunakan `.env.production.example` sebagai daftar variabel, lalu simpan rahasia hanya di server.
2. Pastikan `APP_ENV=production`, `APP_DEBUG=false`, URL HTTPS, cookie secure, dan password database khusus aplikasi.
3. Jalankan `composer install --no-dev --optimize-autoloader` dan `npm ci && npm run build` pada proses deployment.
4. Backup database sebelum migrasi, kemudian jalankan `php artisan migrate --force`.
5. Jalankan `php artisan optimize` dan `php artisan pancor:check`.
6. Jalankan queue worker dengan process supervisor dan scheduler Laravel setiap menit.

Contoh scheduler Linux:

```cron
* * * * * cd /var/www/pancor-mas && php artisan schedule:run >> /dev/null 2>&1
```

Di Windows gunakan Task Scheduler untuk menjalankan `php artisan schedule:run` setiap menit. Pemeriksaan integritas otomatis berjalan pukul 23:45 dan menulis hasil ke `storage/logs/system-check.log`.

## SOP harian Admin

1. Pastikan stok gudang dan jadwal hari itu tersedia.
2. Buat distribusi dengan jadwal yang benar, lalu serahkan stok kepada Sales.
3. Pantau toko yang sudah/belum dikunjungi dan lokasi terakhir Sales.
4. Saat Sales mengajukan pengembalian, hitung uang dan stok fisik sebelum menyetujui.
5. Isi catatan jika ada selisih. Sistem mencatat kekurangan stok sebagai mutasi kehilangan agar stok Sales kembali nol.
6. Periksa rekap setoran dan jalankan `php artisan pancor:check` jika angka tidak wajar.

## SOP Sales

1. Periksa nama akun, jadwal, rute, dan jumlah stok sebelum berangkat.
2. Catat transaksi hanya pada toko yang benar dan pastikan GPS aktif.
3. Pisahkan pembayaran transaksi hari ini dari pembayaran hutang lama.
4. Cetak atau buka struk setelah transaksi tersimpan.
5. Jangan mengulang transaksi ketika jaringan putus. Sambungkan kembali dan periksa status toko.
6. Setelah semua kunjungan selesai, ajukan pengembalian dan serahkan stok serta uang fisik kepada Admin.

## Backup dan pemulihan

- Buat backup database terenkripsi setiap hari dan sebelum setiap deployment.
- Simpan minimal satu salinan di media/server berbeda.
- Jangan menaruh password pada perintah atau repository. Gunakan file opsi MySQL yang hak aksesnya dibatasi.
- Uji restore secara berkala ke database non-production; backup yang belum pernah diuji belum dapat dianggap aman.

Contoh backup dengan file opsi MySQL:

```bash
mysqldump --defaults-extra-file=/path/aman/mysql-backup.cnf --single-transaction --routines --triggers nama_database > pancor-mas.sql
```

Restore harus dilakukan saat aplikasi maintenance dan setelah backup terbaru diverifikasi:

```bash
mysql --defaults-extra-file=/path/aman/mysql-backup.cnf nama_database < pancor-mas.sql
php artisan pancor:check
```

## Koreksi data

- Jangan menghapus transaksi final langsung dari database.
- Gunakan aksi **Batalkan transaksi** pada riwayat toko. Hanya Admin, transaksi aktif terakhir, dan transaksi yang belum masuk closing yang dapat dibatalkan.
- Pembatalan membuat mutasi stok dan ledger berlawanan serta menyimpan alasan di audit log.

## Pemantauan insiden

Periksa `storage/logs/laravel.log`, `storage/logs/system-check.log`, status queue worker, kapasitas disk, sertifikat HTTPS, dan hasil backup. Jika `pancor:check` gagal, hentikan transaksi yang terkait sampai penyebab saldo/harga/admin diperbaiki.
