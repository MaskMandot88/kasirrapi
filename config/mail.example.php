<?php
// config/mail.php
// Konfigurasi SMTP untuk pengiriman email KasirRapi.
// Gunakan Secure SSL/TLS Settings dari cPanel.

if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'SMTP_HOST');
}

if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', 465);
}

if (!defined('SMTP_SECURE')) {
    define('SMTP_SECURE', 'ssl');
}

if (!defined('SMTP_USERNAME')) {
    define('SMTP_USERNAME', 'SMTP_USERNAME');
}

if (!defined('SMTP_PASSWORD')) {
    define('SMTP_PASSWORD', 'ISI_PASSWORD_SMTP');
}

if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', 'SMTP_FROM_EMAIL');
}

if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', 'KasirRapi');
}
