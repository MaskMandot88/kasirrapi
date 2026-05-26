<?php
require_once __DIR__ . '/includes/public-pages.php';

render_public_page([
    'slug' => 'kontak',
    'title' => 'Kontak KasirRapi - Informasi Dukungan Aplikasi Kasir Online',
    'description' => 'Hubungi tim KasirRapi untuk informasi aplikasi kasir online, dukungan penggunaan, kerja sama, atau pertanyaan seputar layanan untuk toko dan UMKM.',
    'eyebrow' => 'Kontak',
    'heading' => 'Hubungi KasirRapi',
    'intro' => 'Kami terbuka untuk pertanyaan seputar fitur, penggunaan, dukungan, dan kerja sama terkait KasirRapi.',
    'sections' => [
        [
            'title' => 'Informasi layanan',
            'paragraphs' => [
                'Untuk pertanyaan mengenai aplikasi kasir online, pengaturan toko, akses akun, atau kebutuhan operasional lain, gunakan kanal komunikasi resmi yang diberikan oleh tim KasirRapi saat proses penawaran, aktivasi, atau dukungan layanan.',
                'Jika Anda sudah memiliki akun, pastikan menyertakan nama toko, email akun, dan ringkasan kendala agar proses pengecekan lebih cepat.',
            ],
        ],
        [
            'title' => 'Jenis pertanyaan yang dapat kami bantu',
            'items' => [
                'Informasi fitur kasir, stok barang, piutang, laporan, absensi, dan gaji karyawan.',
                'Bantuan akses login, reset password, atau pengaturan akun.',
                'Konsultasi kebutuhan toko, grosir, retail, dan UMKM.',
                'Kerja sama, demo aplikasi, dan kebutuhan implementasi.',
            ],
        ],
        [
            'title' => 'Jam respons',
            'paragraphs' => [
                'Waktu respons dapat berbeda tergantung antrean dukungan dan jenis pertanyaan. Permintaan yang berhubungan dengan akses akun, transaksi, atau data toko akan diprioritaskan sesuai informasi yang tersedia.',
            ],
        ],
    ],
]);
