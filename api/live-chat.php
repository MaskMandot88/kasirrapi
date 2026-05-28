<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/live-chat-knowledge.php';

$geminiConfig = __DIR__ . '/../config/gemini.php';
if (file_exists($geminiConfig)) {
    require_once $geminiConfig;
}

if (!defined('GEMINI_API_KEY')) define('GEMINI_API_KEY', '');
if (!defined('GEMINI_MODEL')) define('GEMINI_MODEL', 'gemini-2.5-flash');
if (!defined('GEMINI_CHAT_ENABLED')) define('GEMINI_CHAT_ENABLED', true);

header('Content-Type: application/json; charset=utf-8');

function live_chat_json($ok, $payload = [], $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(['success' => $ok], $payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function live_chat_clean($value, $max = 2000) {
    $value = trim((string)$value);
    $value = preg_replace('/\s+/', ' ', $value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function live_chat_configured() {
    $key = trim((string)GEMINI_API_KEY);
    return GEMINI_CHAT_ENABLED && $key !== '' && stripos($key, 'ISI_') !== 0;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    live_chat_json(false, ['message' => 'Metode request tidak valid.'], 405);
}

if (!live_chat_configured()) {
    live_chat_json(false, [
        'message' => 'Live chat belum aktif. Copy config/gemini.example.php menjadi config/gemini.php, lalu isi GEMINI_API_KEY.',
    ], 503);
}

if (!function_exists('curl_init')) {
    live_chat_json(false, ['message' => 'Ekstensi cURL PHP belum aktif.'], 500);
}

$raw = file_get_contents('php://input');
$input = json_decode((string)$raw, true);
if (!is_array($input)) {
    live_chat_json(false, ['message' => 'Format request tidak valid.'], 400);
}

$message = live_chat_clean($input['message'] ?? '', 1200);
$history = is_array($input['history'] ?? null) ? $input['history'] : [];
$csNames = kasirrapi_live_chat_cs_names();
$csName = live_chat_clean($input['cs_name'] ?? '', 30);
if (!in_array($csName, $csNames, true)) {
    $csName = $csNames[array_rand($csNames)];
}

if ($message === '') {
    live_chat_json(false, ['message' => 'Pesan tidak boleh kosong.'], 422);
}

$contents = [];
$safeHistory = array_slice($history, -8);
foreach ($safeHistory as $item) {
    $role = ($item['role'] ?? '') === 'model' ? 'model' : 'user';
    $text = live_chat_clean($item['text'] ?? '', 1000);
    if ($text === '') continue;
    $contents[] = [
        'role' => $role,
        'parts' => [['text' => $text]],
    ];
}

$contents[] = [
    'role' => 'user',
    'parts' => [['text' => $message]],
];

$userContext = '';
if (!empty($_SESSION['role'])) {
    $userContext = ' User sedang login sebagai role ' . live_chat_clean($_SESSION['role'], 30) . '.';
}

$systemPrompt = "Nama kamu {$csName}. Kamu adalah CS perempuan KasirRapi yang terasa natural, ramah, dan solutif. "
    . "Jangan kaku seperti bot. Jawab dalam Bahasa Indonesia yang hangat, singkat, praktis, dan tidak bertele-tele. "
    . "Untuk jawaban normal, batasi 2-4 kalimat pendek. Jika perlu instruksi, berikan maksimal 3 langkah dulu. "
    . "Pastikan jawaban selalu selesai utuh dan tidak berhenti di tengah kalimat. "
    . "Jika masalah memang butuh jawaban panjang, tulis lengkap tetapi pecah menjadi paragraf-paragraf pendek dengan baris kosong. "
    . "Jangan membuka semua penjelasan sekaligus; tunggu user meminta detail lanjutan. "
    . "Gunakan knowledge base berikut sebagai sumber utama. Jika jawaban tidak ada di knowledge base, jangan mengarang detail teknis.\n\n"
    . kasirrapi_live_chat_knowledge()
    . "\n\nKonteks sesi:" . $userContext;

$payload = [
    'systemInstruction' => [
        'parts' => [
            ['text' => $systemPrompt],
        ],
    ],
    'contents' => $contents,
    'generationConfig' => [
        'temperature' => 0.35,
        'maxOutputTokens' => 900,
    ],
];

$model = rawurlencode((string)GEMINI_MODEL);
$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';

$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-goog-api-key: ' . GEMINI_API_KEY,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => 35,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_FAILONERROR => false,
]);

$response = curl_exec($curl);
$curlError = curl_error($curl);
$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($curlError) {
    live_chat_json(false, ['message' => 'Gagal menghubungi Gemini: ' . $curlError], 502);
}

$json = json_decode((string)$response, true);
if (!is_array($json)) {
    live_chat_json(false, ['message' => 'Respons Gemini tidak valid.'], 502);
}

if ($statusCode >= 400) {
    $errorMessage = $json['error']['message'] ?? 'Gemini menolak request.';
    live_chat_json(false, ['message' => $errorMessage], 502);
}

$reply = '';
foreach (($json['candidates'][0]['content']['parts'] ?? []) as $part) {
    if (isset($part['text'])) {
        $reply .= (string)$part['text'];
    }
}

$reply = trim($reply);
if ($reply === '') {
    $reply = 'Maaf, saya belum mendapat jawaban dari Gemini. Coba ulangi pertanyaan dengan lebih singkat.';
}

$needsEscalation = strpos($reply, '[[ESCALATE]]') !== false;
$reply = trim(str_replace('[[ESCALATE]]', '', $reply));

live_chat_json(true, [
    'reply' => $reply,
    'model' => GEMINI_MODEL,
    'cs_name' => $csName,
    'escalate' => $needsEscalation,
]);
