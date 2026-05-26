# Database KasirRapi

Folder ini berisi struktur database untuk instalasi awal KasirRapi.

## File

- `schema.sql`: struktur tabel, index, dan constraint. File ini tidak berisi data user, transaksi, barang, atau kredensial.

## Import di cPanel

1. Buat database MySQL baru dari cPanel.
2. Buat user database dan hubungkan ke database.
3. Buka phpMyAdmin.
4. Pilih database.
5. Import `database/schema.sql`.
6. Copy `config/database.example.php` menjadi `config/database.php`.
7. Isi nama database, username, dan password asli dari cPanel.
