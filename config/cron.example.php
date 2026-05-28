<?php
// Salin file ini ke config/cron.php lalu ganti token.
// Token dipakai jika cron dipanggil lewat URL.

if (!defined('CRON_SECRET_TOKEN')) {
    define('CRON_SECRET_TOKEN', 'GANTI_DENGAN_TOKEN_PANJANG_ACAK');
}
