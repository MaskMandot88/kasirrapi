<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/tripay.php';

function redirect_registrasi($message) {
    $_SESSION['flash']['registrasi_error'] = $message;
    header('Location: ' . app_url('registrasi.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url('registrasi.php'));
    exit;
}

if (!hash_equals($_SESSION['csrf_registrasi'] ?? '', $_POST['csrf'] ?? '')) {
    redirect_registrasi('Sesi registrasi tidak valid. Silakan coba lagi.');
}

$packages = tripay_packages();
$methods = tripay_payment_methods();

$namaToko = tripay_clean($_POST['nama_toko'] ?? '', 100);
$namaPemilik = tripay_clean($_POST['nama_pemilik'] ?? '', 100);
$email = strtolower(tripay_clean($_POST['email'] ?? '', 100));
$noWa = tripay_clean($_POST['no_wa'] ?? '', 20);
$password = (string)($_POST['password'] ?? '');
$passwordConfirm = (string)($_POST['password_confirm'] ?? '');
$paket = $_POST['paket_langganan'] ?? 'Basic';
$method = $_POST['payment_method'] ?? TRIPAY_DEFAULT_METHOD;
$billingCycle = $_POST['billing_cycle'] ?? 'monthly';
$startTrial = isset($_POST['start_trial']);
$addonHrd = isset($_POST['addon_hrd']);

if ($namaToko === '' || $namaPemilik === '' || $email === '') {
    redirect_registrasi('Nama toko, nama pemilik, dan email wajib diisi.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_registrasi('Format email tidak valid.');
}

if (strlen($password) < 6) {
    redirect_registrasi('Password minimal 6 karakter.');
}

if ($password !== $passwordConfirm) {
    redirect_registrasi('Konfirmasi password tidak sama.');
}

if (!isset($packages[$paket])) {
    redirect_registrasi('Paket tidak valid.');
}

if (!isset($methods[$method])) {
    redirect_registrasi('Metode pembayaran tidak valid.');
}

if (!in_array($billingCycle, ['monthly', 'yearly'], true)) {
    redirect_registrasi('Siklus pembayaran tidak valid.');
}

if ($startTrial) {
    $paket = 'Plus';
    $billingCycle = 'trial';
    $addonHrd = false;
}

if ($addonHrd && empty($packages[$paket]['addon_hrd_available'])) {
    redirect_registrasi('Add-on Absensi & Gaji hanya tersedia untuk paket Plus dan Pro.');
}

try {
    tripay_registration_table($pdo);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE email = ?");
    $stmt->execute([$email]);
    if ((int)$stmt->fetchColumn() > 0) {
        redirect_registrasi('Email ini sudah terdaftar. Silakan login atau gunakan email lain.');
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM registrasi_toko
        WHERE email = ?
          AND status IN ('PENDING','UNPAID','PAID')
          AND created_at >= (NOW() - INTERVAL 1 DAY)
    ");
    $stmt->execute([$email]);
    if ((int)$stmt->fetchColumn() > 0) {
        redirect_registrasi('Email ini sudah memiliki registrasi aktif. Silakan cek email/halaman pembayaran sebelumnya atau coba lagi besok.');
    }

    $plan = kasirrapi_plan($paket);
    $addons = kasirrapi_addons();
    $amount = $billingCycle === 'yearly'
        ? (int)$plan['yearly_price']
        : (int)$plan['monthly_price'];

    if ($addonHrd) {
        $amount += $billingCycle === 'yearly'
            ? (int)$addons['hrd']['yearly_price']
            : (int)$addons['hrd']['monthly_price'];
    }

    if ($billingCycle === 'trial') {
        $amount = 0;
    }

    $merchantRef = 'KRREG' . date('YmdHis') . random_int(100, 999);
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $slug = tripay_unique_slug($pdo, $namaToko);
    $expiredTime = time() + (24 * 60 * 60);
    $maxEmployees = $addonHrd ? (int)$addons['hrd']['max_employees'] : 0;

    if ($amount <= 0) {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO registrasi_toko
                (merchant_ref, nama_toko, slug, nama_pemilik, email, no_wa, password_hash,
                 paket_langganan, amount, billing_cycle, addon_hrd_enabled, max_users, max_products,
                 max_transactions_per_month, max_employees, max_outlets, status, paid_at, trial_ends_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'PAID', NOW(), ?)
        ");
        $trialEndsAt = $billingCycle === 'trial' ? date('Y-m-d H:i:s', strtotime('+14 days')) : null;
        $stmt->execute([
            $merchantRef,
            $namaToko,
            $slug,
            $namaPemilik,
            $email,
            $noWa ?: null,
            $passwordHash,
            $paket,
            0,
            $billingCycle,
            $addonHrd ? 1 : 0,
            (int)$plan['max_users'],
            (int)$plan['max_products'],
            (int)$plan['max_transactions_per_month'],
            $maxEmployees,
            $trialEndsAt,
        ]);

        $registrationId = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM registrasi_toko WHERE id = ? LIMIT 1");
        $stmt->execute([$registrationId]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);
        $tenantId = tripay_activate_registration($pdo, $registration);

        $stmt = $pdo->prepare("UPDATE registrasi_toko SET tenant_id = ? WHERE id = ?");
        $stmt->execute([$tenantId, $registrationId]);

        $pdo->commit();
        unset($_SESSION['csrf_registrasi']);

        header('Location: ' . app_url('registrasi-status.php?ref=' . rawurlencode($merchantRef)));
        exit;
    }

    if (!tripay_is_configured()) {
        redirect_registrasi('Konfigurasi Tripay belum lengkap untuk paket berbayar.');
    }

    $payload = [
        'method' => $method,
        'merchant_ref' => $merchantRef,
        'amount' => $amount,
        'customer_name' => $namaPemilik,
        'customer_email' => $email,
        'customer_phone' => $noWa,
        'order_items' => [
            [
                'sku' => 'KASIRRAPI-' . strtoupper($paket),
                'name' => 'Langganan KasirRapi Paket ' . $paket . ' (' . ($billingCycle === 'yearly' ? 'Tahunan' : 'Bulanan') . ')',
                'price' => $amount,
                'quantity' => 1,
            ],
        ],
        'callback_url' => tripay_absolute_url('tripay-callback.php'),
        'return_url' => tripay_absolute_url('registrasi-status.php?ref=' . rawurlencode($merchantRef)),
        'expired_time' => $expiredTime,
        'signature' => hash_hmac('sha256', TRIPAY_MERCHANT_CODE . $merchantRef . $amount, TRIPAY_PRIVATE_KEY),
    ];

    $response = tripay_create_transaction($payload);
    $data = $response['data'] ?? [];

    $stmt = $pdo->prepare("
        INSERT INTO registrasi_toko
            (merchant_ref, tripay_reference, checkout_url, payment_method, payment_name, pay_code, qr_url,
             nama_toko, slug, nama_pemilik, email, no_wa, password_hash, paket_langganan, amount, billing_cycle,
             addon_hrd_enabled, max_users, max_products, max_transactions_per_month, max_employees, max_outlets, status,
             raw_response, expired_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?))
    ");
    $stmt->execute([
        $merchantRef,
        $data['reference'] ?? null,
        $data['checkout_url'] ?? null,
        $data['payment_method'] ?? $method,
        $data['payment_name'] ?? ($methods[$method] ?? $method),
        $data['pay_code'] ?? null,
        $data['qr_url'] ?? null,
        $namaToko,
        $slug,
        $namaPemilik,
        $email,
        $noWa ?: null,
        $passwordHash,
        $paket,
        $amount,
        $billingCycle,
        $addonHrd ? 1 : 0,
        (int)$plan['max_users'],
        (int)$plan['max_products'],
        (int)$plan['max_transactions_per_month'],
        $maxEmployees,
        1,
        $data['status'] ?? 'UNPAID',
        json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        $expiredTime,
    ]);

    unset($_SESSION['csrf_registrasi']);

    if (!empty($data['checkout_url'])) {
        header('Location: ' . $data['checkout_url']);
        exit;
    }

    header('Location: ' . app_url('registrasi-status.php?ref=' . rawurlencode($merchantRef)));
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    redirect_registrasi($e->getMessage());
}
