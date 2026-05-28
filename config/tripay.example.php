<?php
// config/tripay.php
// Konfigurasi Tripay untuk pembayaran registrasi KasirRapi.
// Copy file ini menjadi config/tripay.php lalu isi credential asli dari dashboard Tripay.

if (!defined('TRIPAY_IS_SANDBOX')) {
    define('TRIPAY_IS_SANDBOX', true);
}

if (!defined('TRIPAY_API_KEY')) {
    define('TRIPAY_API_KEY', 'ISI_API_KEY_TRIPAY');
}

if (!defined('TRIPAY_PRIVATE_KEY')) {
    define('TRIPAY_PRIVATE_KEY', 'ISI_PRIVATE_KEY_TRIPAY');
}

if (!defined('TRIPAY_MERCHANT_CODE')) {
    define('TRIPAY_MERCHANT_CODE', 'ISI_KODE_MERCHANT_TRIPAY');
}

if (!defined('TRIPAY_DEFAULT_METHOD')) {
    define('TRIPAY_DEFAULT_METHOD', 'QRIS');
}
