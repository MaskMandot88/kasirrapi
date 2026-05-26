<?php
session_start();

require_once 'config/database.php';

function redirect_reset($token, $status) {
    header('Location: reset-password.php?token=' . urlencode($token) . '&status=' . urlencode($status));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: lupa-password.php');
    exit;
}

$token = trim($_POST['token'] ?? '');
$password_baru = $_POST['password_baru'] ?? '';
$password_konfirmasi = $_POST['password_konfirmasi'] ?? '';

if ($token === '') {
    header('Location: lupa-password.php?status=gagal');
    exit;
}

if ($password_baru === '' || $password_konfirmasi === '') {
    redirect_reset($token, 'password_kosong');
}

if (strlen($password_baru) < 6) {
    redirect_reset($token, 'password_pendek');
}

if ($password_baru !== $password_konfirmasi) {
    redirect_reset($token, 'password_tidak_sama');
}

try {
    $token_hash = hash('sha256', $token);

    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE reset_token = ?
          AND reset_token_expired IS NOT NULL
          AND reset_token_expired > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token_hash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        redirect_reset($token, 'token_invalid');
    }

    $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        UPDATE users
        SET password = ?,
            reset_token = NULL,
            reset_token_expired = NULL
        WHERE id = ?
    ");
    $stmt->execute([$password_hash, (int)$user['id']]);

    header('Location: login.php?status=reset_sukses');
    exit;

} catch (Throwable $e) {
    redirect_reset($token, 'gagal');
}