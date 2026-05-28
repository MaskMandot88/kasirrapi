# KasirRapi

KasirRapi adalah aplikasi kasir online untuk UMKM, toko, warung, dan grosir. Aplikasi ini dibuat dengan PHP native dan MySQL/MariaDB via PDO.

## Fitur Utama

- Multi-tenant toko
- Kasir/POS
- Barcode scanner
- Barang dan stok
- Harga utama dan ecer
- Piutang
- Supplier dan pembelian
- Profil user
- Reset password via email SMTP
- Registrasi toko dengan pembayaran Tripay
- Paket Gratis, Basic, Plus, Pro, dan add-on Absensi & Gaji
- Live chat bantuan berbasis Gemini API dengan eskalasi tiket ke Super Admin

## Paket Harga

- Gratis: Rp0, 1 user, 100 barang, 100 transaksi/bulan.
- Basic: Rp49.000/bulan atau Rp490.000/tahun, 2 user, 500 barang, transaksi unlimited.
- Plus: Rp79.000/bulan atau Rp790.000/tahun, 5 user, 5.000 barang, supplier, pembelian, piutang, laporan, export.
- Pro: Rp129.000/bulan atau Rp1.290.000/tahun, 10 user, 20.000 barang, audit harga/stok, laporan detail, support prioritas.
- Add-on Absensi & Gaji: +Rp39.000/bulan atau +Rp390.000/tahun sampai 20 karyawan.
- Extra Outlet: +Rp49.000/outlet/bulan atau +Rp490.000/outlet/tahun.

Nilai limit paket disimpan pada tenant: `plan`, `plan_expired_at`, `max_users`, `max_products`, `max_transactions_per_month`, `addon_hrd_enabled`, `max_employees`, dan `max_outlets`.

## Instalasi di cPanel

1. Clone repo ke `public_html` atau folder target.
2. Buat database MySQL di cPanel.
3. Import `database/schema.sql`.
4. Copy `config/database.example.php` menjadi `config/database.php`.
5. Isi kredensial database asli.
6. Copy `config/mail.example.php` menjadi `config/mail.php`.
7. Isi SMTP cPanel asli.
8. Copy `config/tripay.example.php` menjadi `config/tripay.php`.
9. Isi credential Tripay asli.
10. Copy `config/gemini.example.php` menjadi `config/gemini.php`.
11. Isi API key Gemini dari Google AI Studio.
12. Pastikan folder `public/uploads` dan `uploads` writable jika fitur upload/foto wajah digunakan.

## Catatan Keamanan

- Jangan commit `config/database.php`.
- Jangan commit `config/mail.php`.
- Jangan commit `config/tripay.php`.
- Jangan commit `config/gemini.php`.
- Jangan commit isi `public/uploads`.
- Jangan commit isi `uploads`.
- Jangan commit file log atau `error_log`.
- Jangan commit `.env`, password, token, atau kredensial lain.

## Struktur Penting

- `config/database.example.php`: contoh konfigurasi database.
- `config/mail.example.php`: contoh konfigurasi SMTP.
- `config/tripay.example.php`: contoh konfigurasi Tripay.
- `config/gemini.example.php`: contoh konfigurasi Gemini API untuk live chat.
- `includes/live-chat-knowledge.php`: knowledge base CS AI KasirRapi.
- `database/schema.sql`: struktur database tanpa data asli.
- `public/uploads/.gitkeep`: menjaga folder upload publik tetap ada di repo.
- `uploads/.gitkeep`: menjaga folder upload internal tetap ada di repo.
