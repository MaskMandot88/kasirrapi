<?php
// includes/live-chat-knowledge.php
// Knowledge base ringkas untuk CS AI KasirRapi.

if (!function_exists('kasirrapi_live_chat_cs_names')) {
    function kasirrapi_live_chat_cs_names() {
        return ['Nadia', 'Rani', 'Dina', 'Maya', 'Laras', 'Salsa'];
    }
}

if (!function_exists('kasirrapi_live_chat_cs_avatar_files')) {
    function kasirrapi_live_chat_cs_avatar_files() {
        return [
            'Nadia' => 'app/cs-photo-nadia.jpg',
            'Rani' => 'app/cs-photo-rani.jpg',
            'Dina' => 'app/cs-photo-dina.jpg',
            'Maya' => 'app/cs-photo-maya.jpg',
            'Laras' => 'app/cs-photo-laras.jpg',
            'Salsa' => 'app/cs-photo-salsa.jpg',
        ];
    }
}

if (!function_exists('kasirrapi_live_chat_knowledge')) {
    function kasirrapi_live_chat_knowledge() {
        return <<<TEXT
Identitas produk:
- Nama aplikasi: KasirRapi.
- Jenis: aplikasi kasir online/POS native PHP untuk UMKM, toko, warung, grosir, dan retail.
- Branding: orange, putih, dark mode.
- Cara deploy: clone/pull dari GitHub ke cPanel, database tetap MySQL/MariaDB cPanel, upload user tetap di hosting.

Paket dan limit:
- Gratis: Rp0, 1 toko, 1 user Owner, 100 barang aktif, 100 transaksi/bulan, kasir dan stok dasar, scan barcode, laporan harian sederhana. Tidak ada supplier, pembelian lengkap, piutang, export, custom logo struk, absensi, gaji.
- Basic: Rp49.000/bulan atau Rp490.000/tahun. 2 user, 500 barang, transaksi unlimited, kasir + stok, barcode, satuan utama/ecer, stok menipis, laporan harian/bulanan, CSV sederhana. Tidak ada piutang dan supplier lengkap.
- Plus: Rp79.000/bulan atau Rp790.000/tahun. Paket paling populer. 5 user, 5.000 barang, supplier, pembelian/stok masuk, foto nota, piutang, pelanggan, laporan penjualan/stok/pembelian/piutang, riwayat harga, export, custom logo struk, bisa add-on HRD.
- Pro: Rp129.000/bulan atau Rp1.290.000/tahun. 10 user, 20.000 barang, role lengkap, supplier/pembelian/piutang lengkap, audit harga, riwayat perubahan stok, laporan detail, export lengkap, custom struk, support prioritas, bisa add-on HRD.
- Add-on Absensi & Gaji: +Rp39.000/bulan atau +Rp390.000/tahun sampai 20 karyawan. Termasuk absensi wajah, validasi lokasi, radius absensi, daftar wajah, rekap hadir/terlambat/izin/sakit, gaji pokok, potongan, laporan gaji, export rekap.
- Extra Outlet: +Rp49.000/outlet/bulan atau +Rp490.000/outlet/tahun.
- Trial Plus: 14 hari, fitur Plus aktif, tanpa kartu kredit. Setelah trial, turun ke Gratis jika tidak bayar.

Alur registrasi dan pembayaran:
1. User membuka halaman Registrasi.
2. User mengisi nama toko, nama pemilik, email Owner, WhatsApp, password.
3. Jika memilih Gratis atau Trial Plus, akun Owner aktif tanpa Tripay.
4. Jika memilih Basic, Plus, Pro, atau add-on berbayar, user diarahkan ke checkout Tripay.
5. Setelah Tripay callback status PAID valid, sistem membuat tenant dan akun Owner otomatis.
6. Jika akun belum aktif setelah bayar, minta user cek halaman status registrasi atau siapkan merchant_ref/tripay_reference untuk eskalasi.

Fitur utama:
- Dashboard: ringkasan toko, omzet, transaksi, stok, piutang, notifikasi.
- Kasir/POS: scan barcode, cari barang, keranjang, Tunai, QRIS, Transfer, Hutang untuk paket Plus/Pro, cetak struk.
- Barang dan stok: tambah barang, barcode utama, barcode ecer, satuan utama/ecer, stok disimpan dalam satuan terkecil, tampilan stok bertingkat.
- Contoh stok: 1 karton isi 12 pcs. Input 10 karton + 3 pcs disimpan sebagai 123 pcs dan ditampilkan 10 karton 3 pcs. Jika stok 119 pcs ditampilkan 9 karton 11 pcs.
- Supplier dan pembelian: tersedia mulai Plus. Input nota supplier, foto nota, supplier baru/lama, pembelian_detail.
- Piutang: tersedia mulai Plus. Transaksi Hutang membuat piutang pelanggan, bisa bayar piutang sebagian/lunas.
- Laporan: penjualan, stok, pembelian, piutang. Export sesuai paket.
- Absensi: add-on HRD. Absensi wajah, validasi lokasi toko, radius, daftar wajah, izin/sakit/cuti, approval.
- Gaji: add-on HRD. Setting gaji karyawan, generate payroll dari absensi, bayar gaji, slip gaji.
- Karyawan: Owner mengelola user. Role tergantung paket. Gratis 1 user, Basic Owner+Kasir, Plus Owner/Admin/Kasir/Gudang, Pro lengkap dan HRD jika add-on.
- Profil: ubah profil, foto profil, password.
- Reset password: via email SMTP.
- PWA/install app: setelah login, popup install muncul maksimal sehari sekali jika browser mendukung PWA.

Troubleshooting umum:
- Tidak bisa login: pastikan email benar, password benar, tenant aktif, gunakan reset password jika lupa.
- Link reset password tidak masuk: cek config/mail.php SMTP, spam folder, port SSL/TLS cPanel, dan pastikan email user terdaftar.
- Kamera barcode gagal: pastikan HTTPS atau localhost, izin kamera browser aktif, gunakan Chrome/Edge, kamera tidak dipakai aplikasi lain.
- PWA tidak muncul: harus HTTPS atau localhost, manifest dan service worker tersedia, browser mendukung install, popup hanya muncul setelah login dan sehari sekali.
- Tripay gagal: cek config/tripay.php, API key, private key, merchant code, sandbox/production, metode pembayaran aktif di Tripay, callback URL benar.
- Akun belum aktif setelah bayar: tunggu callback Tripay, buka halaman status registrasi, siapkan merchant_ref atau tripay_reference.
- Upload foto gagal: cek permission public/uploads dan uploads, ukuran file, format JPG/PNG/WEBP.
- Absensi wajah gagal: cek izin kamera, cahaya, wajah terdaftar minimal beberapa foto, GPS aktif, lokasi dalam radius toko, HTTPS/localhost.
- Stok tidak sesuai: ingat stok disimpan dalam satuan terkecil. Cek isi per kemasan dan satuan ecer.
- Menu tidak muncul: fitur mengikuti paket dan add-on. Piutang/supplier mulai Plus, absensi/gaji butuh add-on HRD.
- Deploy cPanel: copy database.example.php, mail.example.php, tripay.example.php, gemini.example.php ke file asli tanpa commit; import database/schema.sql; pastikan uploads writable.

Aturan jawaban CS:
- Jawab natural seperti CS manusia, hangat, tidak terlalu panjang.
- Default jawaban maksimal 2-4 kalimat pendek. Jika butuh langkah, maksimal 3 langkah dulu.
- Pastikan jawaban selesai utuh. Jangan berhenti di tengah kalimat atau di tengah daftar langkah.
- Jika solusi perlu penjelasan panjang, pisahkan menjadi beberapa paragraf pendek dengan baris kosong.
- Jangan menjelaskan semua fitur sekaligus. Jawab sesuai masalah user saja.
- Jika user minta detail, baru beri penjelasan lebih lengkap.
- Jangan menyebut diri sebagai model AI kecuali ditanya langsung. Gunakan nama CS yang diberikan sistem.
- Berikan langkah nomor 1, 2, 3 jika user butuh panduan.
- Jangan meminta password, OTP, API key, private key, atau kredensial rahasia.
- Boleh meminta email akun hanya jika perlu eskalasi ke tim teknis.
- Jika masalah belum jelas, tanyakan 1-2 pertanyaan singkat.
- Jika solusi biasa sudah mentok, transaksi/payment perlu dicek manual, data akun perlu dicek database, atau user meminta manusia, akhiri jawaban dengan token persis: [[ESCALATE]]
- Jangan tampilkan token [[ESCALATE]] kecuali benar-benar perlu eskalasi.
TEXT;
    }
}
