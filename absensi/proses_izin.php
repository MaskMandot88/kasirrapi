<?php
require_once '_auth.php';

$jenis = trim($_POST['jenis'] ?? '');
$tanggal_mulai = $_POST['tanggal_mulai'] ?? '';
$tanggal_selesai = $_POST['tanggal_selesai'] ?? '';
$jam_koreksi = $_POST['jam_koreksi'] ?? null;
$alasan = trim($_POST['alasan'] ?? '');

$allowed = ['Izin','Cuti','Sakit','Manual Masuk','Manual Pulang','Koreksi Masuk','Koreksi Pulang'];

if (!in_array($jenis, $allowed, true)) {
    redirect_with('izin.php', 'error', 'Jenis pengajuan tidak valid.');
}

if (!$tanggal_mulai || !$tanggal_selesai || strtotime($tanggal_mulai) === false || strtotime($tanggal_selesai) === false) {
    redirect_with('izin.php', 'error', 'Tanggal tidak valid.');
}

if ($tanggal_selesai < $tanggal_mulai) {
    redirect_with('izin.php', 'error', 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
}

if (mb_strlen($alasan) < 5) {
    redirect_with('izin.php', 'error', 'Alasan wajib diisi lebih jelas.');
}

if (in_array($jenis, ['Manual Masuk','Manual Pulang','Koreksi Masuk','Koreksi Pulang'], true)) {
    if (!$jam_koreksi || !preg_match('/^\d{2}:\d{2}$/', $jam_koreksi)) {
        redirect_with('izin.php', 'error', 'Jam koreksi/manual wajib diisi.');
    }

    if ($tanggal_mulai !== $tanggal_selesai) {
        redirect_with('izin.php', 'error', 'Manual/Koreksi absensi hanya boleh untuk satu tanggal.');
    }
} else {
    $jam_koreksi = null;
}

$lampiranPath = null;

if (!empty($_FILES['lampiran']['name']) && is_uploaded_file($_FILES['lampiran']['tmp_name'])) {
    $maxSize = 3 * 1024 * 1024;
    if ($_FILES['lampiran']['size'] > $maxSize) {
        redirect_with('izin.php', 'error', 'Ukuran lampiran maksimal 3 MB.');
    }

    $ext = strtolower(pathinfo($_FILES['lampiran']['name'], PATHINFO_EXTENSION));
    $allowedExt = ['jpg','jpeg','png','webp','gif','pdf'];

    if (!in_array($ext, $allowedExt, true)) {
        redirect_with('izin.php', 'error', 'Format lampiran tidak diizinkan.');
    }

    $dir = __DIR__ . '/../uploads/absensi/lampiran';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = 'lampiran_t' . $tenant_id . '_u' . $user_id_login . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . '/' . $filename;

    if (!move_uploaded_file($_FILES['lampiran']['tmp_name'], $dest)) {
        redirect_with('izin.php', 'error', 'Gagal menyimpan lampiran.');
    }

    $lampiranPath = '../uploads/absensi/lampiran/' . $filename;
}

$stmt = $pdo->prepare("INSERT INTO pengajuan_absensi
                       (tenant_id, user_id, jenis, tanggal_mulai, tanggal_selesai, jam_koreksi, alasan, lampiran, status)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu')");
$stmt->execute([$tenant_id, $user_id_login, $jenis, $tanggal_mulai, $tanggal_selesai, $jam_koreksi, $alasan, $lampiranPath]);

redirect_with('pengajuan_saya.php', 'success', 'Pengajuan berhasil dikirim dan menunggu approval.');
