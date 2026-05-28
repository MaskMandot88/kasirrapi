<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/tripay.php';

header('Content-Type: application/json');

function tripay_callback_response($success, $message = '') {
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    tripay_registration_table($pdo);

    if (!tripay_is_configured()) {
        tripay_callback_response(false, 'Tripay config is incomplete');
    }

    $json = file_get_contents('php://input');
    $callbackSignature = $_SERVER['HTTP_X_CALLBACK_SIGNATURE'] ?? '';
    $callbackEvent = $_SERVER['HTTP_X_CALLBACK_EVENT'] ?? '';
    $signature = hash_hmac('sha256', $json, TRIPAY_PRIVATE_KEY);

    if (!hash_equals($signature, $callbackSignature)) {
        tripay_callback_response(false, 'Invalid signature');
    }

    if ($callbackEvent !== 'payment_status') {
        tripay_callback_response(false, 'Unrecognized callback event');
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        tripay_callback_response(false, 'Invalid JSON');
    }

    $merchantRef = tripay_clean($data['merchant_ref'] ?? '', 60);
    $reference = tripay_clean($data['reference'] ?? '', 100);
    $status = strtoupper(tripay_clean($data['status'] ?? '', 20));

    if ($merchantRef === '' || $reference === '') {
        tripay_callback_response(false, 'Missing reference');
    }

    $allowedStatus = ['PAID', 'EXPIRED', 'FAILED', 'REFUND'];
    if (!in_array($status, $allowedStatus, true)) {
        tripay_callback_response(false, 'Unsupported status');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT *
        FROM registrasi_toko
        WHERE merchant_ref = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$merchantRef]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        $pdo->rollBack();
        tripay_callback_response(false, 'Registration not found');
    }

    tripay_apply_registration_status($pdo, $registration, $data);

    $pdo->commit();
    tripay_callback_response(true);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    tripay_callback_response(false, $e->getMessage());
}
