# Deploy KasirRapi ke cPanel dari GitHub

Panduan ini untuk deploy KasirRapi dengan cara clone atau pull repository GitHub di hosting cPanel.

## Clone Repo

Masuk ke Terminal cPanel atau SSH, lalu jalankan:

```bash
cd ~/public_html
git clone https://github.com/USERNAME/REPOSITORY.git .
```

Jika aplikasi dipasang di subfolder:

```bash
cd ~/public_html
git clone https://github.com/USERNAME/REPOSITORY.git kasirrapi
```

## File Lokal yang Harus Dibuat Manual

File berikut tidak ikut GitHub dan harus dibuat di hosting:

- `config/database.php`
- `config/mail.php`
- `config/tripay.php`
- `config/gemini.php`
- `config/cron.php`

Buat dari file contoh:

```bash
cp config/database.example.php config/database.php
cp config/mail.example.php config/mail.php
cp config/tripay.example.php config/tripay.php
cp config/gemini.example.php config/gemini.php
cp config/cron.example.php config/cron.php
```

Lalu isi kredensial asli dari cPanel, Tripay, Gemini, dan token cron.

## Database

1. Buat database MySQL di cPanel.
2. Buat user database.
3. Hubungkan user ke database.
4. Import `database/schema.sql` lewat phpMyAdmin.
5. Isi nama database, username, dan password di `config/database.php`.

Schema sudah memuat kolom subscription untuk paket Gratis, Basic, Plus, Pro, add-on HRD, dan limit pemakaian tenant.

## Tripay

1. Copy `config/tripay.example.php` menjadi `config/tripay.php`.
2. Isi `TRIPAY_API_KEY`, `TRIPAY_PRIVATE_KEY`, dan `TRIPAY_MERCHANT_CODE`.
3. Untuk testing, biarkan `TRIPAY_IS_SANDBOX` bernilai `true`.
4. Untuk production, ubah `TRIPAY_IS_SANDBOX` menjadi `false`.
5. Atur callback URL Tripay ke:

```text
https://domain-anda.com/tripay-callback.php
```

Jika aplikasi berada di subfolder, sesuaikan URL callback dengan lokasi aplikasi.

## Gemini Live Chat

1. Copy `config/gemini.example.php` menjadi `config/gemini.php`.
2. Isi `GEMINI_API_KEY` dari Google AI Studio.
3. Pastikan hosting mengaktifkan ekstensi PHP cURL.
4. Pastikan server hosting bisa melakukan request keluar ke `generativelanguage.googleapis.com`.
5. Untuk mencoba di localhost, jalankan aplikasi seperti biasa lalu buka halaman landing/login/dashboard. Widget Live Chat muncul di pojok kanan bawah.
6. Jika CS AI tidak bisa menyelesaikan masalah, user akan diminta memasukkan email akun terdaftar. Tiketnya muncul di `superadmin/index.php`.

Jangan pernah commit `config/gemini.php` karena berisi API key.

## Cron Reminder Masa Aktif

Notifikasi H-3 masa aktif bisa dibuat otomatis lewat Cron Jobs cPanel.

Rekomendasi harian pukul 08:00:

```bash
0 8 * * * /usr/local/bin/php /home/USERNAME/public_html/cron/reminder-masa-aktif.php
```

Jika hosting hanya menyediakan cron URL, gunakan endpoint berikut dan ganti token sesuai `config/cron.php`:

```text
https://domain-anda.com/cron/reminder-masa-aktif.php?token=TOKEN_CRON_ANDA
```

## Folder Writable

Pastikan folder ini ada dan writable:

```bash
public/uploads
uploads
```

Contoh permission:

```bash
chmod 755 public/uploads
chmod 755 uploads
```

Jika upload gagal karena permission hosting, gunakan permission sesuai rekomendasi provider cPanel.

## Pull Update

Sebelum update, backup dulu database dan folder upload.

```bash
cd ~/public_html
git pull origin main
```

Jika branch utama repo memakai `master`, gunakan:

```bash
git pull origin master
```

## Backup Sebelum Update

Backup database dari phpMyAdmin:

1. Buka phpMyAdmin.
2. Pilih database KasirRapi.
3. Klik Export.
4. Pilih Quick atau Custom.
5. Download file `.sql`.

Backup upload:

```bash
tar -czf uploads-backup.tar.gz public/uploads uploads
```

## Peringatan Uploads

Jangan hapus folder `public/uploads` atau `uploads` saat deploy. Isi folder ini adalah file upload user di hosting dan tidak disimpan di GitHub.

Saat melakukan update manual, jangan overwrite folder upload hosting dengan folder kosong dari lokal.
