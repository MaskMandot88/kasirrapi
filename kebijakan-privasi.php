<?php
require_once __DIR__ . '/includes/public-pages.php';

render_public_page([
    'slug' => 'kebijakan-privasi',
    'title' => 'Kebijakan Privasi KasirRapi - Perlindungan Data Pengguna',
    'description' => 'Pelajari bagaimana KasirRapi mengelola informasi pengguna, data toko, data transaksi, dan data operasional untuk layanan aplikasi kasir online.',
    'eyebrow' => 'Kebijakan Privasi',
    'heading' => 'Kebijakan Privasi KasirRapi',
    'intro' => 'Kebijakan ini menjelaskan jenis data yang dapat diproses oleh KasirRapi dan bagaimana data tersebut digunakan untuk mendukung layanan aplikasi kasir online.',
    'sections' => [
        [
            'title' => 'Data yang dapat dikumpulkan',
            'paragraphs' => [
                'KasirRapi dapat memproses data yang Anda masukkan saat menggunakan aplikasi, termasuk data akun, data toko, data karyawan, data barang, data transaksi, data piutang, absensi, gaji, dan laporan operasional.',
                'Data teknis seperti alamat IP, informasi perangkat, browser, waktu akses, dan catatan aktivitas tertentu juga dapat diproses untuk keamanan, audit, dan peningkatan layanan.',
            ],
        ],
        [
            'title' => 'Penggunaan data',
            'items' => [
                'Menyediakan fitur kasir, stok, piutang, absensi, gaji, laporan, dan pengaturan toko.',
                'Menjaga keamanan akun dan membantu proses verifikasi akses.',
                'Mengirim notifikasi terkait akun, reset password, atau informasi layanan.',
                'Menganalisis performa sistem dan memperbaiki pengalaman penggunaan.',
            ],
        ],
        [
            'title' => 'Penyimpanan dan keamanan',
            'paragraphs' => [
                'KasirRapi berupaya menerapkan langkah teknis dan organisasi yang wajar untuk menjaga keamanan data. Namun, tidak ada sistem elektronik yang dapat menjamin keamanan mutlak dari seluruh risiko.',
                'Pengguna bertanggung jawab menjaga kerahasiaan email, password, dan akses perangkat yang digunakan untuk masuk ke aplikasi.',
            ],
        ],
        [
            'title' => 'Pembagian data',
            'paragraphs' => [
                'Data pengguna tidak dijual kepada pihak ketiga. Data dapat dibagikan jika diperlukan untuk penyediaan layanan, kepatuhan hukum, keamanan sistem, atau atas permintaan sah dari pihak berwenang.',
            ],
        ],
        [
            'title' => 'Perubahan kebijakan',
            'paragraphs' => [
                'Kebijakan Privasi dapat diperbarui dari waktu ke waktu. Perubahan akan berlaku sejak ditampilkan pada halaman ini, kecuali dinyatakan lain.',
            ],
        ],
    ],
]);
