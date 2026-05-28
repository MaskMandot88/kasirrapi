<?php
// piutang/proses_bayar.php
session_start();
require_once '../config/database.php';
require_once '../includes/plans.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Owner', 'Admin', 'Kasir'], true)) {
    http_response_code(403);
    die('Akses ditolak.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$tenant_id = (int) $_SESSION['tenant_id'];
$subscription = kasirrapi_tenant_subscription($pdo, $tenant_id);
if (!kasirrapi_feature_allowed($subscription, 'piutang')) {
    http_response_code(403);
    die('Fitur Piutang tersedia mulai paket Plus.');
}
$kasir_id = (int) $_SESSION['user_id'];
$piutang_id = isset($_POST['piutang_id']) ? (int) $_POST['piutang_id'] : 0;
$nominal_bayar = isset($_POST['nominal_bayar']) ? (float) $_POST['nominal_bayar'] : 0;
$metode_bayar = $_POST['metode_bayar'] ?? 'Tunai';
$catatan = trim((string)($_POST['catatan'] ?? ''));

if ($piutang_id <= 0) die('ID piutang tidak valid.');
if ($nominal_bayar <= 0) die('Nominal pembayaran tidak valid.');
if (!in_array($metode_bayar, ['Tunai', 'QRIS', 'Transfer'], true)) die('Metode bayar tidak valid.');

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT *
        FROM piutang
        WHERE id = ? AND tenant_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$piutang_id, $tenant_id]);
    $piutang = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$piutang) {
        throw new Exception('Data piutang tidak ditemukan.');
    }

    if ($piutang['status'] !== 'Belum Lunas') {
        throw new Exception('Piutang ini tidak dalam status Belum Lunas.');
    }

    $sisa_lama = (float) $piutang['sisa_piutang'];
    if ($nominal_bayar > $sisa_lama) {
        throw new Exception('Nominal bayar melebihi sisa piutang.');
    }

    $stmt_insert = $pdo->prepare("
        INSERT INTO piutang_pembayaran
            (tenant_id, piutang_id, kasir_id, tanggal_bayar, metode_bayar, nominal_bayar, catatan)
        VALUES
            (?, ?, ?, NOW(), ?, ?, ?)
    ");
    $stmt_insert->execute([
        $tenant_id,
        $piutang_id,
        $kasir_id,
        $metode_bayar,
        $nominal_bayar,
        $catatan !== '' ? $catatan : null,
    ]);

    $total_dibayar_baru = (float) $piutang['total_dibayar'] + $nominal_bayar;
    $sisa_baru = max(0, $sisa_lama - $nominal_bayar);
    $status_baru = $sisa_baru <= 0 ? 'Lunas' : 'Belum Lunas';

    $stmt_update = $pdo->prepare("
        UPDATE piutang
        SET total_dibayar = ?, sisa_piutang = ?, status = ?
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt_update->execute([$total_dibayar_baru, $sisa_baru, $status_baru, $piutang_id, $tenant_id]);

    $pdo->commit();

    header('Location: bayar.php?id=' . $piutang_id . '&sukses=1');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pesan = urlencode($e->getMessage());
    header('Location: bayar.php?id=' . (int)$piutang_id . '&error=' . $pesan);
    exit;
}
