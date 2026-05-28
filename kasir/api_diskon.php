<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once '../config/database.php';
require_once '../includes/discounts.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['Owner', 'Admin', 'Kasir'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid.']);
    exit;
}

$tenant_id = (int)$_SESSION['tenant_id'];
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = [];
}

$cart = $payload['cart'] ?? [];
$metode_bayar = $payload['metode_bayar'] ?? 'Tunai';
if (!in_array($metode_bayar, ['Tunai','QRIS','Transfer','Hutang'], true)) {
    $metode_bayar = 'Tunai';
}

if (!is_array($cart) || count($cart) === 0) {
    echo json_encode([
        'success' => true,
        'subtotal' => 0,
        'diskon' => 0,
        'total' => 0,
        'items' => [],
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, harga_jual, harga_ecer, isi_per_kemasan
        FROM barang
        WHERE id = ? AND tenant_id = ?
    ");

    $items = [];

    foreach ($cart as $cartItem) {
        $barangId = isset($cartItem['id']) ? (int)$cartItem['id'] : 0;
        $qty = isset($cartItem['qty']) ? (int)$cartItem['qty'] : 0;
        $tipeSatuan = $cartItem['tipe_satuan'] ?? 'eceran';

        if ($barangId <= 0 || $qty <= 0) continue;

        $stmt->execute([$barangId, $tenant_id]);
        $barang = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$barang) continue;

        $isi = max(1, (int)($barang['isi_per_kemasan'] ?? 1));
        $hargaJual = (float)$barang['harga_jual'];
        $hargaEcer = $barang['harga_ecer'] !== null ? (float)$barang['harga_ecer'] : 0;

        if ($tipeSatuan === 'kemasan' && $isi > 1) {
            $harga = $hargaJual;
        } else {
            $harga = $hargaEcer > 0 ? $hargaEcer : $hargaJual;
        }

        $items[] = [
            'barang_id' => $barangId,
            'qty' => $qty,
            'subtotal' => $qty * $harga,
        ];
    }

    echo json_encode(['success' => true] + discounts_calculate($pdo, $tenant_id, $items, $metode_bayar), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal menghitung diskon.']);
}
