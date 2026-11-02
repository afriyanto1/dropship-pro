# DropshipPro — PHP & MySQL

Aplikasi dasar manajemen dropship dengan dashboard, produk, supplier, pesanan, dan laporan.

## Kebutuhan
- PHP 8.1+ (disarankan PHP 8.2+) dengan ekstensi PDO MySQL
- MySQL 8 atau MariaDB
- Apache/Nginx atau XAMPP
- Koneksi internet untuk memuat Bootstrap CDN

## Instalasi di XAMPP (Windows)
1. Ekstrak folder `aplikasi_dropshipper` ke `C:\xampp\htdocs\`.
2. Jalankan Apache dan MySQL dari XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin`.
4. Pilih menu **Import**, lalu impor file `database/dropship_db.sql`.
5. Periksa `config/database.php`. Konfigurasi default:
   - host: `127.0.0.1`
   - database: `dropship_db`
   - user: `root`
   - password: kosong
6. Buka `http://localhost/aplikasi_dropshipper/`.

## Instalasi di Linux
1. Pasang PHP dengan ekstensi `pdo_mysql`, Apache/Nginx, dan MySQL/MariaDB.
2. Salin folder aplikasi ke document root, misalnya `/var/www/html/aplikasi_dropshipper`.
3. Impor SQL: `mysql -u root -p < database/dropship_db.sql`
4. Atur kredensial database di `config/database.php` atau melalui environment variables `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
5. Pastikan permission file sesuai user web server.

## Fitur saat ini
- Dashboard ringkasan produk, supplier, pesanan aktif, omzet lunas, dan estimasi laba kotor.
- CRUD produk dan supplier.
- Pembuatan pesanan dengan banyak item.
- Status pesanan, status pembayaran, dan nomor resi.
- Laporan penjualan dengan filter tanggal.
- PDO prepared statements, output escaping, dan token CSRF pada form mutasi.

## Catatan penting sebelum produksi
Ini adalah starter application, belum sistem siap produksi. Belum ada login/role admin, upload foto produk, integrasi marketplace/payment gateway/ekspedisi, notifikasi otomatis, dan audit log. Tambahkan autentikasi dan HTTPS sebelum dipublikasikan ke internet. Stok pada katalog merupakan informasi referensi dan tidak otomatis dikurangi saat pesanan dibuat. Penghitungan laba adalah margin barang, belum laba bersih.
