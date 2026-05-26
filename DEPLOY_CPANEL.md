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

Buat dari file contoh:

```bash
cp config/database.example.php config/database.php
cp config/mail.example.php config/mail.php
```

Lalu isi kredensial asli dari cPanel.

## Database

1. Buat database MySQL di cPanel.
2. Buat user database.
3. Hubungkan user ke database.
4. Import `database/schema.sql` lewat phpMyAdmin.
5. Isi nama database, username, dan password di `config/database.php`.

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
