<?php
require_once __DIR__ . '/includes/public-pages.php';

render_public_page([
    'slug' => 'syarat-ketentuan',
    'title' => 'Syarat dan Ketentuan KasirRapi - Aturan Penggunaan Aplikasi',
    'description' => 'Baca syarat dan ketentuan penggunaan KasirRapi sebagai aplikasi kasir online untuk toko, grosir, retail, dan UMKM.',
    'eyebrow' => 'Syarat dan Ketentuan',
    'heading' => 'Syarat dan Ketentuan Penggunaan KasirRapi',
    'intro' => 'Dengan menggunakan KasirRapi, pengguna dianggap telah membaca, memahami, dan menyetujui ketentuan penggunaan layanan berikut.',
    'sections' => [
        [
            'title' => 'Penggunaan layanan',
            'paragraphs' => [
                'KasirRapi disediakan untuk membantu pencatatan transaksi dan operasional toko. Pengguna wajib menggunakan aplikasi sesuai hukum yang berlaku dan tidak menyalahgunakan sistem untuk aktivitas yang melanggar aturan.',
            ],
        ],
        [
            'title' => 'Akun dan akses',
            'items' => [
                'Pengguna bertanggung jawab atas kebenaran informasi akun dan toko yang dimasukkan.',
                'Pengguna wajib menjaga kerahasiaan password dan hak akses masing-masing akun.',
                'Setiap aktivitas yang dilakukan melalui akun pengguna dianggap sebagai aktivitas pemilik akun tersebut.',
            ],
        ],
        [
            'title' => 'Data dan tanggung jawab pengguna',
            'paragraphs' => [
                'Pengguna bertanggung jawab atas data barang, transaksi, harga, piutang, absensi, gaji, dan laporan yang dimasukkan ke dalam sistem. KasirRapi membantu pencatatan, tetapi keputusan bisnis tetap menjadi tanggung jawab pengguna.',
                'Pengguna disarankan melakukan pengecekan data secara berkala, terutama untuk laporan keuangan, stok, dan informasi penting lainnya.',
            ],
        ],
        [
            'title' => 'Ketersediaan layanan',
            'paragraphs' => [
                'KasirRapi berupaya menjaga layanan tetap tersedia dan berjalan baik. Namun, gangguan dapat terjadi karena pemeliharaan, jaringan internet, perangkat pengguna, penyedia hosting, atau keadaan lain di luar kendali langsung.',
            ],
        ],
        [
            'title' => 'Perubahan ketentuan',
            'paragraphs' => [
                'Syarat dan Ketentuan dapat diperbarui sesuai kebutuhan layanan. Penggunaan layanan setelah perubahan ditampilkan dianggap sebagai persetujuan terhadap ketentuan terbaru.',
            ],
        ],
    ],
]);
