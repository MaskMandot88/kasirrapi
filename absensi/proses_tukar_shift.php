<?php
require_once '_auth.php';

$tanggal = $_POST['tanggal'] ?? '';
$jadwal_asal_id = (int)($_POST['jadwal_asal_id'] ?? 0);
$shift_id = (int)($_POST['shift_id'] ?? 0);
$user_pengganti_id = (int)($_POST['user_pengganti_id'] ?? 0);
$alasan = trim($_POST['alasan'] ?? '');

if (!$tanggal || strtotime($tanggal) === false) {
    redirect_with('tukar_shift.php', 'error', 'Tanggal tidak valid.');
}

if ($shift_id <= 0 || $user_pengganti_id <= 0 || $user_pengganti_id === (int)$user_id_login) {
    redirect_with('tukar_shift.php?tanggal=' . urlencode($tanggal), 'error', 'Data shift atau pengganti tidak valid.');
}

if (mb_strlen($alasan) < 5) {
    redirect_with('tukar_shift.php?tanggal=' . urlencode($tanggal), 'error', 'Alasan wajib diisi lebih jelas.');
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE tenant_id = ? AND id = ? LIMIT 1");
$stmt->execute([$tenant_id, $user_pengganti_id]);
if (!$stmt->fetch()) {
    redirect_with('tukar_shift.php?tanggal=' . urlencode($tanggal), 'error', 'Karyawan pengganti tidak ditemukan.');
}

$stmt = $pdo->prepare("SELECT id FROM shifts WHERE tenant_id = ? AND id = ? AND aktif = 1 LIMIT 1");
$stmt->execute([$tenant_id, $shift_id]);
if (!$stmt->fetch()) {
    redirect_with('tukar_shift.php?tanggal=' . urlencode($tanggal), 'error', 'Shift tidak valid.');
}

if ($jadwal_asal_id > 0) {
    $stmt = $pdo->prepare("SELECT id FROM jadwal_shift WHERE tenant_id = ? AND id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$tenant_id, $jadwal_asal_id, $user_id_login]);
    if (!$stmt->fetch()) {
        redirect_with('tukar_shift.php?tanggal=' . urlencode($tanggal), 'error', 'Jadwal asal tidak valid.');
    }
} else {
    $jadwal_asal_id = null;
}

$stmt = $pdo->prepare("INSERT INTO shift_tukar
                       (tenant_id, tanggal, jadwal_asal_id, user_asal_id, user_pengganti_id, shift_id, alasan, status, requested_by)
                       VALUES (?, ?, ?, ?, ?, ?, ?, 'Menunggu', ?)");
$stmt->execute([$tenant_id, $tanggal, $jadwal_asal_id, $user_id_login, $user_pengganti_id, $shift_id, $alasan, $user_id_login]);

redirect_with('pengajuan_saya.php', 'success', 'Pengajuan tukar shift berhasil dikirim dan menunggu approval.');
