# Panduan Deploy — Koperasi Konsumen Cinde Barokah Sejahtera

Hosting: RumahHosting SuperLite Mini · Domain: koperasikonsumencindebarokah.web.id
Panel: **DirectAdmin** (paket Super/Lite RumahHosting memang pakai DirectAdmin, bukan cPanel — cPanel hanya ada di paket Pro. Jadi pilihan "admin panel"-mu ini sudah benar.)

## Yang dicari di DirectAdmin (nama menunya beda dikit dari cPanel)

| Yang KITA butuh | Di DirectAdmin | Tinggal di: |
|---|---|---|
| Ganti versi PHP + extension | "Select PHP Version" (Alt-PHP/CloudLinux), kadang di menu "PHP Settings" | User Level |
| Buat database + user MySQL | "MySQL Management" | Account/Admin |
| Impor file .sql | phpMyAdmin (buka dari MySQL Management + tombol "phpMyAdmin") | User |
| Upload & kelola file kode | "File Manager" | User |
| Aktifkan HTTPS (Let's Encrypt) | "SSL Certificates" | User |
| Atur dokumen root / domain | "Domain Setup" | Account |

## A. Persiapan di LOKAL (laptop ini)

1. **Commit kode** biar beres:
   ```bash
   git add -A
   git commit -m "siap deploy"
   ```
2. **Dump database lokal** (HeidiSQL/phpMyAdmin lokal → Export → file .sql, pilih database `aplikasi_penjualan`).
3. **Siapkan paket upload** (zip): `app`, `bootstrap`, `config`, `database`, `public`, `resources`, `routes`, `vendor` + `composer.json`/`composer.lock`.
   - JANGAN ikutkan: `.env` (nanti dibuat khusus server), `node_modules`, `.git`, `tests`, folder `storage` isi (data cache) — cukup folder kosong `storage` + `storage/app/public` ada file `.gitignore`-nya.

## B. Di DirectAdmin hosting

1. **PHP version** → menu "Select PHP Version" / "PHP Settings" → set PHP 8.2 + aktifkan extension: `pdo_mysql`, `mbstring`, `openssl`, `dom`, `xml`, `fileinfo`, `gd`, `zip`, `curl`.
   (Kalau menu pemilih versi tidak nungul → tinggal edan/ticket support RumahHosting, mereka set-kan.)
2. **MySQL Databases** → buat DB + user (biasanya otomatis ada prefix) → user diberi ALL PRIVILEGES.
3. **Import data** → buka phpMyAdmin di panel → pilih database → Import → file `.sql` dump lokal.
4. **Upload kode** → File Manager → masuk folder domain (`public_html` / folder domain) → upload zip → extract.
   - Target akhir: root domain = folder aplikasi, bukan folder `public`.
5. **Pindahkan isi `public/` ke folder domain**:
   - Cara umum & rapi: salin isi `public/` (termasuk `index.php`, folder `assets/`, `.htaccess`) ke folder domain, lalu edit `index.php`:
     ```php
     require __DIR__.'/../vendor/autoload.php';
     $app = require_once __DIR__.'/../bootstrap/app.php';
     ```
   - Atau kalau panel mendukung atur document root → langsung tunjuk ke folder `public/` aplikasi.
6. **Buat `.env` di server** (jangan pakai .env lokal):
   ```
   APP_NAME="Koperasi Konsumen Cinde Barokah Sejahtera"
   APP_ENV=production
   APP_KEY=          # generate: jalankan `php artisan key:generate --show` di lokal, copy isinya
   APP_URL=https://koperasikonsumencindebarokah.web.id
   APP_DEBUG=false
   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_DATABASE=<nama db dari panel>
   DB_USERNAME=<user db dari panel>
   DB_PASSWORD=<password user db>
   SESSION_DRIVER=file
   CACHE_DRIVER=file
   QUEUE_CONNECTION=sync
   LOG_LEVEL=error
   ```
7. **Permission**: folder `storage` dan `bootstrap/cache` → 755 (atau 775), file `.env` → 600.
8. **SSL/HTTPS** → menu SSL di panel → aktifkan Let's Encrypt / AutoSSL → nanti domain jadi https.
9. Cek hasil: buka domain → harus login normal dengan akun yang sama di lokal (data lama ikut karena dump diimport).

## C. Troubleshoot umum

- "500 Internal Server Error" → cek `storage/logs/laravel.log` di File Manager; biasanya `APP_KEY` kosong atau path `index.php` salah.
- "Access denied for user" database → sudahi 🤦 cek `DB_DATABASE`/`DB_USERNAME` di `.env` cocok persis (termasuk prefix) dengan yang dibuat panel.
- Halaman shape/checkbox berantakan → pastikan `APP_URL` https:// + folder `assets/` ikut keupload.
- Import .sql gagal batas waktu/ukuran → pecah dump jadi beberapa bagian; phpMyAdmin umumnya batas wording 50MB; kalau lebih, babe bagi per tabel.
- Ganti PHP version: kalau di panel nggak bisa → ticket support; aplikasi butuh PHP 8.1+ (ideal 8.2).

## Catatan penting

- Data (transaksi, stok, pemilik, user) **tidak ikut otomatis** — hanya via dump `.sql` langkah A.2 + B.3.
- File upload impor Excel/PDF **tidak disimpan** di server → tidak perlu dipindah.
- Domain `.web.id` gratis tahun pertama; tahun berikutnya berbayar — ingat-ingat tanggal perpanjang.
- kalau ada fitur yang tidak tersedia di DirectAdmin (mis. cron, SSH) → tinggal buat ticket ke support RumahHosting; bila benar-benar perlu cPanel (bayar tambahan, biasanya di paket Pro).
