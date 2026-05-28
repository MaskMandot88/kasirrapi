<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/support.php';
require_once __DIR__ . '/../includes/live-chat-knowledge.php';

header('Content-Type: application/json; charset=utf-8');

function live_chat_escalate_json($ok, $payload = [], $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(['success' => $ok], $payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function live_chat_escalate_clean($value, $max = 2000) {
    $value = trim((string)$value);
    $value = preg_replace('/\s+/', ' ', $value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    live_chat_escalate_json(false, ['message' => 'Metode request tidak valid.'], 405);
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    live_chat_escalate_json(false, ['message' => 'Format request tidak valid.'], 400);
}

$email = strtolower(live_chat_escalate_clean($input['email'] ?? '', 120));
$message = live_chat_escalate_clean($input['message'] ?? '', 2500);
$history = is_array($input['history'] ?? null) ? array_slice($input['history'], -16) : [];
$csNames = kasirrapi_live_chat_cs_names();
$csName = live_chat_escalate_clean($input['cs_name'] ?? '', 30);
if (!in_array($csName, $csNames, true)) {
    $csName = $csNames[0];
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    live_chat_escalate_json(false, ['message' => 'Masukkan email akun yang valid.'], 422);
}

if ($message === '') {
    $message = 'User meminta bantuan lanjutan dari live chat.';
}

support_tickets_table($pdo);
$contact = support_find_registered_contact($pdo, $email);
if (!$contact) {
    live_chat_escalate_json(false, [
        'message' => 'Email ini belum cocok dengan data terdaftar. Pakai email yang sama dengan akun KasirRapi, ya.',
    ], 404);
}

$ticketCode = 'KRCS-' . date('Ymd-His') . '-' . random_int(100, 999);
$subject = 'Live chat mentok - ' . live_chat_escalate_clean($message, 90);
$historyJson = json_encode($history, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$stmt = $pdo->prepare("
    INSERT INTO support_tickets (
        ticket_code, tenant_id, user_id, email, nama_toko, nama_user,
        subject, message, chat_history, cs_name, source, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'live_chat', 'Baru')
");
$stmt->execute([
    $ticketCode,
    $contact['tenant_id'] ?? null,
    $contact['user_id'] ?? null,
    $email,
    $contact['nama_toko'] ?? null,
    $contact['nama_user'] ?? ($contact['nama_pemilik'] ?? null),
    $subject,
    $message,
    $historyJson,
    $csName,
]);

live_chat_escalate_json(true, [
    'ticket_code' => $ticketCode,
    'message' => 'Tiket ' . $ticketCode . ' sudah masuk ke tim teknis KasirRapi. Tim akan menghubungi email terdaftar Anda.',
]);
