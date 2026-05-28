<?php
// config/app.php
// Konfigurasi branding dan path global aplikasi.

if (!defined('APP_NAME')) {
    define('APP_NAME', 'KasirRapi');
}

if (!defined('APP_TAGLINE')) {
    define('APP_TAGLINE', 'Transaksi rapi, usaha lebih pasti.');
}

if (!defined('APP_SEO_TITLE')) {
    define('APP_SEO_TITLE', 'KasirRapi - Aplikasi Kasir Online untuk Toko, Grosir, dan UMKM');
}

if (!defined('APP_SEO_DESCRIPTION')) {
    define('APP_SEO_DESCRIPTION', 'KasirRapi membantu toko dan UMKM mengelola kasir, stok, piutang, absensi wajah, gaji karyawan, dan laporan dalam satu aplikasi.');
}

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.19');
}

/*
|--------------------------------------------------------------------------
| APP_BASE_URL
|--------------------------------------------------------------------------
| Kosong berarti aplikasi berada langsung di root domain:
| https://domain.com/
|
| Jika nanti aplikasi dipasang di subfolder, contoh:
| https://domain.com/kasirrapi/
| ubah menjadi:
| define('APP_BASE_URL', '/kasirrapi');
*/
if (!defined('APP_BASE_URL')) {
    define('APP_BASE_URL', '');
}

if (!function_exists('app_url')) {
    function app_url($path = '') {
        $base = rtrim(APP_BASE_URL, '/');
        $path = '/' . ltrim((string)$path, '/');
        return $base . $path;
    }
}

if (!function_exists('asset_url')) {
    function asset_url($path = '') {
        return app_url('assets/' . ltrim((string)$path, '/'));
    }
}
