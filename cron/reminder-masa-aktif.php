<?php
// Cron harian untuk mengirim notifikasi H-3 masa aktif ke Owner.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notifications.php';

$configPath = __DIR__ . '/../config/cron.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    $configuredToken = defined('CRON_SECRET_TOKEN') ? (string)CRON_SECRET_TOKEN : '';
    $requestToken = (string)($_GET['token'] ?? '');

    if ($configuredToken === '' || !hash_equals($configuredToken, $requestToken)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        exit;
    }
}

$sent = app_notify_all_owner_expiry_warnings($pdo);
$payload = [
    'ok' => true,
    'sent' => $sent,
    'mode' => $isCli ? 'cli' : 'web',
    'ran_at' => date('Y-m-d H:i:s'),
];

if ($isCli) {
    echo 'Reminder masa aktif H-3 terkirim: ' . $sent . PHP_EOL;
    exit;
}

header('Content-Type: application/json');
echo json_encode($payload);
