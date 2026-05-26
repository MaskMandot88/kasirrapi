<?php
// absensi/_auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

$tenant_id = (int) $_SESSION['tenant_id'];
$user_id_login = (int) $_SESSION['user_id'];
$role_login = $_SESSION['role'];
$nama_login = $_SESSION['nama'] ?? 'User';

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_hr_role() {
    $role = $_SESSION['role'] ?? '';
    return in_array($role, ['Owner', 'Admin', 'HRD'], true);
}

function require_hr_role() {
    if (!is_hr_role()) {
        http_response_code(403);
        die('Akses ditolak. Hanya Owner/Admin/HRD yang boleh mengakses halaman ini.');
    }
}

function redirect_with($url, $key, $message) {
    $_SESSION[$key] = $message;
    header('Location: ' . $url);
    exit;
}

function flash($key) {
    if (!empty($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

function save_base64_image($base64, $prefix, $tenant_id) {
    if (!$base64 || strpos($base64, 'data:image') !== 0) {
        return null;
    }
    if (!preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,/', $base64, $m)) {
        return null;
    }
    $clean = preg_replace('/^data:image\/(png|jpeg|jpg|webp);base64,/', '', $base64);
    $binary = base64_decode($clean, true);
    if ($binary === false || strlen($binary) < 1024) {
        return null;
    }
    if (strlen($binary) > 3 * 1024 * 1024) {
        throw new RuntimeException('Ukuran foto terlalu besar. Maksimal 3 MB.');
    }
    $dirRelative = 'uploads/absensi_wajah/tenant_' . (int)$tenant_id;
    $dirAbsolute = __DIR__ . '/../' . $dirRelative;
    if (!is_dir($dirAbsolute)) {
        mkdir($dirAbsolute, 0755, true);
    }
    $filename = $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
    $pathAbsolute = $dirAbsolute . '/' . $filename;
    file_put_contents($pathAbsolute, $binary);
    return $dirRelative . '/' . $filename;
}

function get_today_jadwal(PDO $pdo, $tenant_id, $user_id, $tanggal) {
    $stmt = $pdo->prepare("SELECT js.*, s.nama_shift, s.jam_mulai, s.jam_selesai, s.toleransi_terlambat_menit, s.toleransi_pulang_cepat_menit, s.lintas_hari
                           FROM jadwal_shift js
                           LEFT JOIN shifts s ON s.id = js.shift_id AND s.tenant_id = js.tenant_id
                           WHERE js.tenant_id = ? AND js.user_id = ? AND js.tanggal = ?
                           LIMIT 1");
    $stmt->execute([$tenant_id, $user_id, $tanggal]);
    return $stmt->fetch();
}
?>
