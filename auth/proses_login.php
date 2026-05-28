<?php
session_start();
require_once '../config/database.php';
require_once '../includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$tokoSlug = trim($_POST['toko'] ?? '');

$back = 'login.php' . ($tokoSlug !== '' ? ('?toko=' . urlencode($tokoSlug) . '&') : '?');

if ($email === '' || $password === '') {
    header("Location: " . $back . "error=empty");
    exit;
}

try {
    if ($tokoSlug !== '') {
        $stmt = $pdo->prepare("SELECT u.*, t.status AS tenant_status, t.nama_toko, t.slug
                               FROM users u
                               JOIN tenants t ON t.id = u.tenant_id
                               WHERE u.email = ? AND t.slug = ?
                               LIMIT 1");
        $stmt->execute([$email, $tokoSlug]);
    } else {
        $stmt = $pdo->prepare("SELECT u.*, t.status AS tenant_status, t.nama_toko, t.slug
                               FROM users u
                               JOIN tenants t ON t.id = u.tenant_id
                               WHERE u.email = ?
                               LIMIT 1");
        $stmt->execute([$email]);
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password'])) {
        header("Location: " . $back . "error=1");
        exit;
    }

    if (($user['tenant_status'] ?? '') !== 'Aktif') {
        header("Location: " . $back . "error=suspend");
        exit;
    }

    session_regenerate_id(true);

    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['tenant_id'] = (int)$user['tenant_id'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['nama']      = $user['nama'];
    $_SESSION['nama_user'] = $user['nama'];
    $_SESSION['nama_toko'] = $user['nama_toko'] ?? '';

    if (($user['role'] ?? '') === 'Owner') {
        app_notify_owner_expiry_if_needed($pdo, (int)$user['tenant_id']);
    }

    header("Location: ../dashboard/index.php");
    exit;
} catch (Throwable $e) {
    header("Location: " . $back . "error=1");
    exit;
}
