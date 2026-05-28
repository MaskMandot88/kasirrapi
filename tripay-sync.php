<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/tripay.php';

function tripay_sync_redirect($ref, $type, $message) {
    $_SESSION['flash']['tripay_status_' . $type] = $message;
    header('Location: ' . app_url('registrasi-status.php?ref=' . rawurlencode($ref)));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('registrasi.php'));
    exit;
}

$ref = tripay_clean($_POST['ref'] ?? '', 100);
if ($ref === '') {
    header('Location: ' . app_url('registrasi.php'));
    exit;
}

if (!hash_equals($_SESSION['csrf_tripay_sync'] ?? '', $_POST['csrf'] ?? '')) {
    tripay_sync_redirect($ref, 'error', 'Sesi cek status tidak valid. Silakan buka ulang halaman status.');
}

try {
    tripay_registration_table($pdo);

    if (!tripay_is_configured()) {
        tripay_sync_redirect($ref, 'error', 'Konfigurasi Tripay belum lengkap.');
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM registrasi_toko
        WHERE merchant_ref = ? OR tripay_reference = ?
        LIMIT 1
    ");
    $stmt->execute([$ref, $ref]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        tripay_sync_redirect($ref, 'error', 'Registrasi tidak ditemukan.');
    }

    if (($registration['status'] ?? '') === 'PAID' && !empty($registration['tenant_id'])) {
        tripay_sync_redirect($registration['merchant_ref'], 'success', 'Akun toko sudah aktif.');
    }

    $tripayReference = trim((string)($registration['tripay_reference'] ?? ''));
    if ($tripayReference === '') {
        tripay_sync_redirect($registration['merchant_ref'], 'error', 'Referensi Tripay belum tersedia untuk registrasi ini.');
    }

    $response = tripay_transaction_detail($tripayReference);
    $payload = $response['data'] ?? [];
    if (!is_array($payload) || !$payload) {
        tripay_sync_redirect($registration['merchant_ref'], 'error', 'Detail transaksi Tripay kosong.');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM registrasi_toko WHERE id = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([(int)$registration['id']]);
    $lockedRegistration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lockedRegistration) {
        $pdo->rollBack();
        tripay_sync_redirect($registration['merchant_ref'], 'error', 'Registrasi tidak ditemukan saat sinkronisasi.');
    }

    $result = tripay_apply_registration_status($pdo, $lockedRegistration, $payload);
    $pdo->commit();

    if (($result['status'] ?? '') === 'PAID') {
        tripay_sync_redirect($registration['merchant_ref'], 'success', 'Pembayaran sudah terkonfirmasi. Akun toko berhasil diaktifkan.');
    }

    tripay_sync_redirect($registration['merchant_ref'], 'success', 'Status Tripay saat ini: ' . ($result['status'] ?? 'UNKNOWN') . '.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    tripay_sync_redirect($ref, 'error', $e->getMessage());
}
