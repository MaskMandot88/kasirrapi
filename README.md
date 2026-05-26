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

## Instalasi di cPanel

1. Clone repo ke `public_html` atau folder target.
2. Buat database MySQL di cPanel.
3. Import `database/schema.sql`.
4. Copy `config/database.example.php` menjadi `config/database.php`.
5. Isi kredensial database asli.
6. Copy `config/mail.example.php` menjadi `config/mail.php`.
7. Isi SMTP cPanel asli.
8. Pastikan folder `public/uploads` dan `uploads` writable jika fitur upload/foto wajah digunakan.

## Catatan Keamanan

- Jangan commit `config/database.php`.
- Jangan commit `config/mail.php`.
- Jangan commit isi `public/uploads`.
- Jangan commit isi `uploads`.
- Jangan commit file log atau `error_log`.
- Jangan commit `.env`, password, token, atau kredensial lain.

## Struktur Penting

- `config/database.example.php`: contoh konfigurasi database.
- `config/mail.example.php`: contoh konfigurasi SMTP.
- `database/schema.sql`: struktur database tanpa data asli.
- `public/uploads/.gitkeep`: menjaga folder upload publik tetap ada di repo.
- `uploads/.gitkeep`: menjaga folder upload internal tetap ada di repo.
