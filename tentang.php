<?php
require_once __DIR__ . '/includes/public-pages.php';

render_public_page([
    'slug' => 'tentang',
    'title' => 'Tentang KasirRapi - Aplikasi Kasir Online untuk Toko dan UMKM',
    'description' => 'Kenali KasirRapi, aplikasi kasir online untuk membantu toko, grosir, retail, dan UMKM mengelola transaksi, stok, piutang, absensi, gaji, dan laporan.',
    'eyebrow' => 'Tentang KasirRapi',
    'heading' => 'Aplikasi kasir online untuk operasional toko yang lebih rapi',
    'intro' => 'KasirRapi dibuat untuk membantu pelaku usaha menjalankan transaksi harian, mengontrol stok, mencatat piutang, memantau karyawan, dan membaca laporan bisnis dalam satu sistem.',
    'sections' => [
        [
            'title' => 'Apa itu KasirRapi?',
            'paragraphs' => [
                'KasirRapi adalah aplikasi kasir online berbasis web untuk toko, grosir, retail, dan UMKM yang membutuhkan pencatatan penjualan lebih tertata. Sistem ini dirancang agar pemilik usaha dapat memantau proses kasir, barang, piutang, absensi, gaji, dan laporan tanpa berpindah-pindah aplikasi.',
                'Fokus utama KasirRapi adalah membantu bisnis kecil dan menengah bekerja lebih efisien. Setiap transaksi dapat tercatat, stok dapat dipantau, dan data operasional toko bisa digunakan sebagai dasar pengambilan keputusan.',
            ],
        ],
        [
            'title' => 'Untuk siapa KasirRapi dibuat?',
            'paragraphs' => [
                'KasirRapi cocok digunakan oleh toko sembako, minimarket lokal, toko kelontong, grosir, toko retail, usaha keluarga, dan UMKM yang mulai membutuhkan sistem pencatatan yang lebih profesional.',
            ],
            'items' => [
                'Pemilik toko yang ingin memantau omzet dan stok dengan lebih cepat.',
                'Kasir yang membutuhkan proses transaksi penjualan yang sederhana.',
                'Gudang yang perlu mengelola stok barang masuk dan keluar.',
                'Tim HRD atau owner yang ingin mencatat absensi dan gaji karyawan.',
            ],
        ],
        [
            'title' => 'Nilai yang kami utamakan',
            'paragraphs' => [
                'Kami percaya aplikasi bisnis harus mudah dipakai, cepat diakses, dan relevan dengan kebiasaan kerja toko di Indonesia. Karena itu KasirRapi mengutamakan tampilan yang sederhana, alur kerja yang jelas, dan fitur yang langsung berhubungan dengan kebutuhan harian usaha.',
                'Dengan tagline "Transaksi rapi, usaha lebih pasti", KasirRapi ingin membantu pemilik usaha melihat kondisi tokonya dengan data yang lebih tertib dan mudah dipahami.',
            ],
        ],
    ],
]);
