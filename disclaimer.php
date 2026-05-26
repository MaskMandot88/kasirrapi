<?php
require_once __DIR__ . '/includes/public-pages.php';

render_public_page([
    'slug' => 'disclaimer',
    'title' => 'Disclaimer KasirRapi - Batasan Informasi dan Penggunaan Layanan',
    'description' => 'Disclaimer KasirRapi menjelaskan batasan informasi, penggunaan data, laporan, dan tanggung jawab pengguna aplikasi kasir online.',
    'eyebrow' => 'Disclaimer',
    'heading' => 'Disclaimer KasirRapi',
    'intro' => 'Halaman ini menjelaskan batasan informasi dan tanggung jawab terkait penggunaan KasirRapi sebagai aplikasi kasir online.',
    'sections' => [
        [
            'title' => 'Informasi dalam aplikasi',
            'paragraphs' => [
                'KasirRapi menyediakan alat bantu pencatatan transaksi, stok, piutang, absensi, gaji, dan laporan. Informasi yang muncul di dalam aplikasi bergantung pada data yang dimasukkan oleh pengguna.',
                'Kesalahan input, pengaturan harga, stok, role pengguna, atau data karyawan dapat memengaruhi hasil laporan dan tampilan informasi.',
            ],
        ],
        [
            'title' => 'Bukan nasihat keuangan, pajak, atau hukum',
            'paragraphs' => [
                'Laporan dan ringkasan yang tersedia di KasirRapi bersifat sebagai alat bantu operasional. Informasi tersebut tidak dimaksudkan sebagai nasihat keuangan, pajak, akuntansi, atau hukum.',
                'Untuk keputusan yang membutuhkan kepastian profesional, pengguna disarankan berkonsultasi dengan akuntan, konsultan pajak, penasihat hukum, atau pihak profesional terkait.',
            ],
        ],
        [
            'title' => 'Tanggung jawab pengguna',
            'items' => [
                'Memastikan data yang dimasukkan benar dan sesuai kondisi usaha.',
                'Memeriksa hasil transaksi, stok, piutang, absensi, gaji, dan laporan secara berkala.',
                'Menjaga keamanan akun, password, perangkat, dan jaringan internet yang digunakan.',
            ],
        ],
        [
            'title' => 'Perubahan layanan',
            'paragraphs' => [
                'Fitur, tampilan, harga paket, dan kebijakan layanan dapat berubah sesuai pengembangan KasirRapi. Informasi terbaru akan mengikuti pembaruan yang ditampilkan pada aplikasi atau halaman resmi.',
            ],
        ],
    ],
]);
