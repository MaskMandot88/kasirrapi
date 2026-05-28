<?php
// Salin file ini ke config/superadmin.php lalu ganti username dan hash password.
// Buat hash password dengan:
// php -r "echo password_hash('PASSWORD_BARU_ANDA', PASSWORD_DEFAULT), PHP_EOL;"

if (!defined('SUPERADMIN_USERNAME')) {
    define('SUPERADMIN_USERNAME', 'superadmin');
}

if (!defined('SUPERADMIN_PASSWORD_HASH')) {
    define('SUPERADMIN_PASSWORD_HASH', 'ISI_HASH_PASSWORD_DI_SINI');
}
