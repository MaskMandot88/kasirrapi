<?php
session_start();
require_once '../config/database.php';
require_once '../includes/ui.php';
require_once '../includes/notifications.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}
if (!ui_is_role(['Owner'])) {
    die('Akses ditolak.');
}

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id = (int)$_SESSION['user_id'];

$judul = trim($_POST['judul'] ?? '');
$pesan = trim($_POST['pesan'] ?? '');
$target_role = $_POST['target_role'] ?? 'Semua';
$target_user_id = (int)($_POST['target_user_id'] ?? 0);
$tipe = $_POST['tipe'] ?? 'Pengumuman';
$prioritas = $_POST['prioritas'] ?? 'Normal';
$link = trim($_POST['link'] ?? '');

$roles = ['Semua','Owner','Admin','Gudang','Kasir','HRD','User Tertentu'];
$tipes = ['Info','Pengumuman','Approval','Absensi','Gaji','Piutang','Stok','Sistem'];
$prioritasList = ['Normal','Penting','Darurat'];

if ($judul === '' || $pesan === '') {
    $_SESSION['flash']['error'] = 'Judul dan pesan wajib diisi.';
    header('Location: buat.php');
    exit;
}
if (!in_array($target_role, $roles, true)) $target_role = 'Semua';
if (!in_array($tipe, $tipes, true)) $tipe = 'Pengumuman';
if (!in_array($prioritas, $prioritasList, true)) $prioritas = 'Normal';

if ($target_role === 'User Tertentu') {
    if ($target_user_id <= 0) {
        $_SESSION['flash']['error'] = 'Pilih user tujuan.';
        header('Location: buat.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE tenant_id = ? AND id = ? LIMIT 1");
    $stmt->execute([$tenant_id, $target_user_id]);
    if (!$stmt->fetch()) {
        $_SESSION['flash']['error'] = 'User tujuan tidak valid.';
        header('Location: buat.php');
        exit;
    }

    $targetRoleDb = 'Semua';
} else {
    $target_user_id = null;
    $targetRoleDb = $target_role;
}

$link = $link !== '' ? $link : null;

app_notification_create($pdo, $tenant_id, $user_id, $target_user_id, $targetRoleDb, $tipe, $judul, $pesan, $link, $prioritas);

$_SESSION['flash']['success'] = 'Pemberitahuan berhasil dikirim.';
header('Location: index.php');
exit;
