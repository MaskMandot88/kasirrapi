<?php
require_once __DIR__ . '/includes/public-pages.php';

render_public_page([
    'slug' => 'kontak',
    'title' => 'Kontak KasirRapi - Registrasi, Pembayaran, dan Dukungan',
    'description' => 'Hubungi KasirRapi untuk registrasi toko, trial Plus, pembayaran Tripay, bantuan login, upgrade paket, dan dukungan aplikasi kasir online untuk UMKM.',
    'eyebrow' => 'Kontak',
    'heading' => 'Hubungi KasirRapi',
    'intro' => 'Pilih jalur yang sesuai: registrasi toko baru, login pengguna, bantuan pembayaran Tripay, upgrade paket, atau dukungan penggunaan aplikasi.',
    'actions' => [
        ['label' => 'Registrasi Toko', 'url' => app_url('registrasi.php')],
        ['label' => 'Login Pengguna', 'url' => app_url('auth/login.php')],
        ['label' => 'Lihat Paket Harga', 'url' => app_url('index.php#harga')],
    ],
    'sections' => [
        [
            'title' => 'Registrasi toko baru',
            'paragraphs' => [
                'Untuk membuat toko baru, gunakan tombol Registrasi Toko. Anda dapat memilih paket Gratis, Trial Plus 14 hari, Basic, Plus, Pro, serta add-on Absensi & Gaji.',
                'Paket Gratis dan Trial Plus dapat aktif tanpa pembayaran. Paket berbayar akan diarahkan ke checkout Tripay sesuai metode pembayaran yang dipilih.',
            ],
            'items' => [
                'Siapkan nama toko, nama pemilik, email login Owner, nomor WhatsApp, dan password Owner.',
                'Email yang didaftarkan akan menjadi akun utama Owner toko.',
                'Setelah pembayaran Tripay berstatus PAID, akun Owner akan aktif otomatis.',
            ],
        ],
        [
            'title' => 'Pembayaran Tripay',
            'paragraphs' => [
                'KasirRapi memproses pembayaran registrasi paket berbayar melalui Tripay. Setelah transaksi dibuat, Anda akan diarahkan ke halaman pembayaran Tripay.',
                'Jika pembayaran sudah dilakukan tetapi akun belum aktif, tunggu beberapa saat lalu buka kembali halaman status registrasi dari link return Tripay. Aktivasi bergantung pada callback pembayaran yang valid.',
            ],
            'items' => [
                'Pastikan nominal, paket, dan metode pembayaran sudah benar sebelum membayar.',
                'Simpan nomor referensi pembayaran untuk pengecekan.',
                'Jangan membuat registrasi baru berulang-ulang dengan email yang sama sebelum status pembayaran jelas.',
            ],
        ],
        [
            'title' => 'Dukungan pengguna aktif',
            'paragraphs' => [
                'Jika Anda sudah memiliki akun, login terlebih dahulu lalu cek menu yang tersedia sesuai paket toko. Beberapa fitur seperti Piutang, Supplier, Absensi, dan Gaji hanya tampil jika paket atau add-on mendukung.',
                'Saat menghubungi admin/support, sertakan data yang jelas agar pengecekan lebih cepat.',
            ],
            'items' => [
                'Nama toko dan email akun.',
                'Paket aktif: Gratis, Basic, Plus, Pro, atau add-on Absensi & Gaji.',
                'Nomor invoice atau referensi Tripay jika masalah terkait pembayaran.',
                'Screenshot kendala dan langkah yang dilakukan sebelum error muncul.',
            ],
        ],
        [
            'title' => 'Upgrade paket dan add-on',
            'paragraphs' => [
                'Upgrade diperlukan jika toko sudah melewati batas paket, misalnya barang aktif atau transaksi bulanan paket Gratis, jumlah user, atau kebutuhan fitur Supplier, Piutang, Export, Absensi, dan Gaji.',
            ],
            'items' => [
                'Basic cocok untuk kasir dan stok ringan.',
                'Plus cocok untuk toko aktif dengan supplier, pembelian, piutang, laporan, dan export.',
                'Pro cocok untuk kontrol lebih lengkap, audit harga/stok, dan support prioritas.',
                'Add-on Absensi & Gaji dapat ditambahkan untuk toko yang punya karyawan.',
            ],
        ],
        [
            'title' => 'Jam respons dan prioritas bantuan',
            'paragraphs' => [
                'Waktu respons dapat berbeda tergantung antrean dukungan dan jenis pertanyaan. Kendala akses akun, pembayaran, dan aktivasi toko diprioritaskan lebih dulu.',
                'Untuk deployment mandiri di cPanel, pastikan file config database, SMTP, dan Tripay sudah dibuat lokal sesuai panduan deploy.',
            ],
        ],
    ],
]);
