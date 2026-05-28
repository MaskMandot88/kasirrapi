<?php
require_once '_auth.php';
if (!is_hr_role() && (int)($_POST['user_id'] ?? 0) !== $user_id_login) {
    die('Akses ditolak.');
}
$target_user_id = (int)($_POST['user_id'] ?? 0);
$payload = json_decode($_POST['payload'] ?? '', true);
if (!$target_user_id || !is_array($payload) || count($payload) < 5) {
    redirect_with('daftar_wajah.php', 'error', 'Minimal 5 referensi wajah wajib diambil.');
}
$stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND tenant_id = ? LIMIT 1");
$stmt->execute([$target_user_id, $tenant_id]);
if (!$stmt->fetch()) redirect_with('daftar_wajah.php', 'error', 'Karyawan tidak valid.');

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT INTO karyawan_wajah (tenant_id, user_id, status, jumlah_embedding, threshold_similarity, created_by)
                           VALUES (?, ?, 'Aktif', 0, 0.520000, ?)
                           ON DUPLICATE KEY UPDATE status='Aktif', updated_at=CURRENT_TIMESTAMP, created_by=VALUES(created_by)");
    $stmt->execute([$tenant_id, $target_user_id, $user_id_login]);
    $stmt = $pdo->prepare("SELECT id FROM karyawan_wajah WHERE tenant_id=? AND user_id=? LIMIT 1");
    $stmt->execute([$tenant_id, $target_user_id]);
    $profile_id = (int)$stmt->fetchColumn();
    $pdo->prepare("UPDATE karyawan_wajah_embedding SET aktif = 0 WHERE tenant_id = ? AND user_id = ?")->execute([$tenant_id, $target_user_id]);
    $insert = $pdo->prepare("INSERT INTO karyawan_wajah_embedding (tenant_id, karyawan_wajah_id, user_id, foto_referensi, embedding_json, pose_label, quality_score, aktif) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
    $saved = 0;
    foreach ($payload as $idx => $row) {
        if (empty($row['descriptor']) || !is_array($row['descriptor']) || count($row['descriptor']) < 100) continue;
        $imgPath = save_base64_image($row['image'] ?? '', 'enroll_u'.$target_user_id, $tenant_id);
        $pose = substr((string)($row['pose'] ?? 'ref'), 0, 50);
        $insert->execute([$tenant_id, $profile_id, $target_user_id, $imgPath, json_encode(array_values($row['descriptor'])), $pose, 1.0]);
        $saved++;
    }
    if ($saved < 5) throw new RuntimeException('Data descriptor wajah kurang dari 5.');
    $pdo->prepare("UPDATE karyawan_wajah SET jumlah_embedding = ?, updated_at=CURRENT_TIMESTAMP WHERE id = ?")->execute([$saved, $profile_id]);
    $pdo->prepare("INSERT INTO face_absen_log (tenant_id, user_id_diklaim, jenis_absen, hasil, alasan_gagal, ip_address, user_agent) VALUES (?, ?, 'Enroll', 'Berhasil', ?, ?, ?)")
        ->execute([$tenant_id, $target_user_id, 'Enroll '.$saved.' referensi', $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
    $pdo->commit();
    redirect_with('daftar_wajah.php?user_id='.$target_user_id, 'success', 'Data wajah berhasil disimpan: '.$saved.' referensi.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    redirect_with('daftar_wajah.php?user_id='.$target_user_id, 'error', 'Gagal menyimpan wajah: '.$e->getMessage());
}
