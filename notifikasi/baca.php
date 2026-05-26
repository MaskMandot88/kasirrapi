<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT id FROM notifikasi
    WHERE tenant_id = ?
      AND id = ?
      AND (target_user_id = ? OR target_user_id IS NULL)
      AND (target_role = 'Semua' OR target_role = ? OR target_role IS NULL)
    LIMIT 1");
$stmt->execute([$tenant_id, $id, $user_id, $role]);

if ($stmt->fetch()) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO notifikasi_read (tenant_id, notifikasi_id, user_id, read_at)
                           VALUES (?, ?, ?, NOW())");
    $stmt->execute([$tenant_id, $id, $user_id]);
}

header('Location: index.php');
exit;
