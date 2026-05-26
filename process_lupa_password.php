<?php
session_start();

require_once 'config/database.php';
require_once 'includes/ui.php';
require_once 'includes/mailer.php';

function redirect_lupa($status) {
    header('Location: lupa-password.php?status=' . urlencode($status));
    exit;
}

function base_public_url($path = '') {
    if (defined('APP_BASE_URL') && trim(APP_BASE_URL) !== '') {
        return rtrim(APP_BASE_URL, '/') . '/' . ltrim($path, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . '/' . ltrim($path, '/');
}

function column_exists($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_lupa('gagal');
}

$email = trim($_POST['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_lupa('email_invalid');
}

try {
    if (!column_exists($pdo, 'users', 'reset_token')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(255) NULL");
    }

    if (!column_exists($pdo, 'users', 'reset_token_expired')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN reset_token_expired DATETIME NULL");
    }

    $stmt = $pdo->prepare("
        SELECT id, tenant_id, email
        FROM users
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Untuk keamanan, jangan beri tahu apakah email ada atau tidak.
    if (!$user) {
        redirect_lupa('cek_email');
    }

    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $expired = date('Y-m-d H:i:s', time() + 60 * 60);

    $stmt = $pdo->prepare("
        UPDATE users
        SET reset_token = ?,
            reset_token_expired = ?
        WHERE id = ?
    ");
    $stmt->execute([$token_hash, $expired, (int)$user['id']]);

    $reset_link = base_public_url('reset-password.php?token=' . urlencode($token));

    $subject = 'Reset Password ' . APP_NAME;

    $textBody = "Halo,

Kami menerima permintaan reset password untuk akun Anda di " . APP_NAME . ".

Klik link berikut untuk membuat password baru:
" . $reset_link . "

Link ini berlaku selama 1 jam.

Jika Anda tidak meminta reset password, abaikan email ini.

Salam,
" . APP_NAME;

    $htmlBody = '
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,sans-serif;color:#0f172a;">
    <div style="max-width:560px;margin:0 auto;padding:24px;">
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;padding:24px;">
            <h2 style="margin:0 0 12px;color:#f97316;">Reset Password ' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . '</h2>

            <p style="font-size:15px;line-height:1.6;">
                Kami menerima permintaan reset password untuk akun Anda.
            </p>

            <p style="font-size:15px;line-height:1.6;">
                Klik tombol di bawah untuk membuat password baru. Link ini berlaku selama <b>1 jam</b>.
            </p>

            <p style="margin:24px 0;">
                <a href="' . htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8') . '" 
                   style="display:inline-block;background:#f97316;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:bold;">
                    Reset Password
                </a>
            </p>

            <p style="font-size:13px;line-height:1.6;color:#64748b;">
                Jika tombol tidak bisa diklik, salin link ini ke browser:
                <br>
                <span style="word-break:break-all;">' . htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8') . '</span>
            </p>

            <p style="font-size:13px;color:#64748b;margin-top:24px;">
                Jika Anda tidak meminta reset password, abaikan email ini.
            </p>
        </div>
    </div>
</body>
</html>';

    send_smtp_mail($email, $subject, $textBody, $htmlBody);

    redirect_lupa('cek_email');

} catch (Throwable $e) {
    $_SESSION['flash']['error'] = $e->getMessage();
    redirect_lupa('email_gagal');
}