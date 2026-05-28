<?php
require_once '_auth.php';

$id = (int)($_POST['id'] ?? 0);
$metode = $_POST['metode_bayar'] ?? 'Tunai';
$tanggal = $_POST['tanggal_bayar'] ?? '';
$catatan = trim($_POST['catatan'] ?? '');

if (!in_array($metode, ['Tunai','Transfer','QRIS'], true)) {
    redirect_with('index.php', 'error', 'Metode bayar tidak valid.');
}
if (!$tanggal || strtotime($tanggal) === false) {
    redirect_with('index.php', 'error', 'Tanggal bayar tidak valid.');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM payroll_detail WHERE tenant_id = ? AND id = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$tenant_id, $id]);
    $d = $stmt->fetch();

    if (!$d) throw new RuntimeException('Detail gaji tidak ditemukan.');
    if ($d['status_bayar'] === 'Sudah Dibayar') throw new RuntimeException('Gaji ini sudah dibayar.');

    $dt = date('Y-m-d H:i:s', strtotime($tanggal));

    $stmt = $pdo->prepare("UPDATE payroll_detail
                           SET status_bayar = 'Sudah Dibayar',
                               metode_bayar = ?,
                               tanggal_bayar = ?,
                               dibayar_by = ?,
                               catatan = CONCAT(COALESCE(catatan,''), ?)
                           WHERE tenant_id = ? AND id = ?");
    $stmt->execute([$metode, $dt, $user_id_login, "\nPembayaran: " . $catatan, $tenant_id, $id]);

    $stmt = $pdo->prepare("INSERT INTO payroll_pembayaran
                           (tenant_id, payroll_id, payroll_detail_id, user_id, nominal, metode_bayar, tanggal_bayar, catatan, created_by)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$tenant_id, $d['payroll_id'], $id, $d['user_id'], $d['total_gaji'], $metode, $dt, $catatan, $user_id_login]);

    // Jika semua detail sudah dibayar, status periode menjadi Dibayar.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payroll_detail
                           WHERE tenant_id = ? AND payroll_id = ? AND status_bayar <> 'Sudah Dibayar'");
    $stmt->execute([$tenant_id, $d['payroll_id']]);
    $remaining = (int)$stmt->fetchColumn();

    if ($remaining === 0) {
        $stmt = $pdo->prepare("UPDATE payroll_periode
                               SET status = 'Dibayar', dibayar_by = ?, dibayar_at = NOW()
                               WHERE tenant_id = ? AND id = ?");
        $stmt->execute([$user_id_login, $tenant_id, $d['payroll_id']]);
    }

    $pdo->commit();
    redirect_with('detail.php?id=' . (int)$d['payroll_id'], 'success', 'Pembayaran gaji berhasil disimpan.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    redirect_with('index.php', 'error', $e->getMessage());
}
