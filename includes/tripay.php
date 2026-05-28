<?php
// includes/tripay.php
// Helper registrasi toko dan pembayaran Tripay.

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/plans.php';

$tripayConfig = __DIR__ . '/../config/tripay.php';
if (file_exists($tripayConfig)) {
    require_once $tripayConfig;
}

if (!defined('TRIPAY_IS_SANDBOX')) define('TRIPAY_IS_SANDBOX', true);
if (!defined('TRIPAY_API_KEY')) define('TRIPAY_API_KEY', '');
if (!defined('TRIPAY_PRIVATE_KEY')) define('TRIPAY_PRIVATE_KEY', '');
if (!defined('TRIPAY_MERCHANT_CODE')) define('TRIPAY_MERCHANT_CODE', '');
if (!defined('TRIPAY_DEFAULT_METHOD')) define('TRIPAY_DEFAULT_METHOD', 'QRIS');

if (!function_exists('tripay_is_configured')) {
    function tripay_is_configured() {
        $values = [TRIPAY_API_KEY, TRIPAY_PRIVATE_KEY, TRIPAY_MERCHANT_CODE];
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value === '' || stripos($value, 'ISI_') === 0) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('tripay_api_base_url')) {
    function tripay_api_base_url() {
        return TRIPAY_IS_SANDBOX
            ? 'https://tripay.co.id/api-sandbox'
            : 'https://tripay.co.id/api';
    }
}

if (!function_exists('tripay_absolute_url')) {
    function tripay_absolute_url($path = '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host . app_url($path);
    }
}

if (!function_exists('tripay_packages')) {
    function tripay_packages() {
        $packages = [];
        foreach (kasirrapi_plans() as $code => $plan) {
            $packages[$code] = [
                'name' => $plan['name'],
                'price' => (int)$plan['monthly_price'],
                'yearly_price' => (int)$plan['yearly_price'],
                'label' => $plan['name'] . ' - ' . kasirrapi_format_price($plan['monthly_price']) . '/bulan',
                'max_users' => (int)$plan['max_users'],
                'max_products' => (int)$plan['max_products'],
                'max_transactions_per_month' => (int)$plan['max_transactions_per_month'],
                'addon_hrd_available' => (bool)$plan['addon_hrd_available'],
            ];
        }
        return $packages;
    }
}

if (!function_exists('tripay_payment_methods')) {
    function tripay_payment_methods() {
        return [
            'QRIS' => 'QRIS',
            'BRIVA' => 'BRI Virtual Account',
            'BCAVA' => 'BCA Virtual Account',
            'BNIVA' => 'BNI Virtual Account',
            'MANDIRIVA' => 'Mandiri Virtual Account',
            'PERMATAVA' => 'Permata Virtual Account',
            'ALFAMART' => 'Alfamart',
            'INDOMARET' => 'Indomaret',
        ];
    }
}

if (!function_exists('tripay_clean')) {
    function tripay_clean($value, $max = 150) {
        $value = trim((string)$value);
        $value = preg_replace('/\s+/', ' ', $value);
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('tripay_slug')) {
    function tripay_slug($value) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', (string)$value), '-'));
        return $slug !== '' ? substr($slug, 0, 50) : 'toko';
    }
}

if (!function_exists('tripay_registration_table')) {
    function tripay_registration_table(PDO $pdo) {
        tripay_ensure_subscription_columns($pdo);

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS registrasi_toko (
                id INT AUTO_INCREMENT PRIMARY KEY,
                merchant_ref VARCHAR(50) NOT NULL UNIQUE,
                tripay_reference VARCHAR(100) DEFAULT NULL,
                checkout_url TEXT DEFAULT NULL,
                payment_method VARCHAR(50) DEFAULT NULL,
                payment_name VARCHAR(100) DEFAULT NULL,
                pay_code VARCHAR(100) DEFAULT NULL,
                qr_url TEXT DEFAULT NULL,
                nama_toko VARCHAR(100) NOT NULL,
                slug VARCHAR(60) NOT NULL,
                nama_pemilik VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                no_wa VARCHAR(20) DEFAULT NULL,
                password_hash VARCHAR(255) NOT NULL,
                paket_langganan ENUM('Gratis','Basic','Plus','Pro') NOT NULL DEFAULT 'Gratis',
                amount INT NOT NULL DEFAULT 0,
                billing_cycle ENUM('monthly','yearly','trial') NOT NULL DEFAULT 'monthly',
                addon_hrd_enabled TINYINT(1) NOT NULL DEFAULT 0,
                max_users INT NOT NULL DEFAULT 1,
                max_products INT NOT NULL DEFAULT 100,
                max_transactions_per_month INT NOT NULL DEFAULT 100,
                max_employees INT NOT NULL DEFAULT 0,
                max_outlets INT NOT NULL DEFAULT 1,
                status ENUM('PENDING','UNPAID','PAID','EXPIRED','FAILED','REFUND','CANCELLED') NOT NULL DEFAULT 'PENDING',
                tenant_id INT DEFAULT NULL,
                raw_response LONGTEXT DEFAULT NULL,
                callback_payload LONGTEXT DEFAULT NULL,
                expired_at DATETIME DEFAULT NULL,
                paid_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_registrasi_email (email),
                KEY idx_registrasi_status (status),
                KEY idx_registrasi_tripay_reference (tripay_reference)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        tripay_ensure_registration_columns($pdo);
    }
}

if (!function_exists('tripay_column_exists')) {
    function tripay_column_exists(PDO $pdo, $table, $column) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('tripay_ensure_subscription_columns')) {
    function tripay_ensure_subscription_columns(PDO $pdo) {
        try {
            $pdo->exec("UPDATE tenants SET paket_langganan = 'Pro' WHERE paket_langganan = 'VIP'");
            $pdo->exec("ALTER TABLE tenants MODIFY paket_langganan ENUM('Gratis','Basic','Plus','Pro') DEFAULT 'Gratis'");
        } catch (Throwable $e) {
            // Beberapa hosting bisa menolak MODIFY jika data lama belum rapi. Kolom plan di bawah tetap menjadi sumber utama baru.
        }

        $columns = [
            'plan' => "ALTER TABLE tenants ADD COLUMN plan VARCHAR(20) NOT NULL DEFAULT 'Gratis' AFTER paket_langganan",
            'plan_expired_at' => "ALTER TABLE tenants ADD COLUMN plan_expired_at DATETIME DEFAULT NULL AFTER plan",
            'max_users' => "ALTER TABLE tenants ADD COLUMN max_users INT NOT NULL DEFAULT 1 AFTER plan_expired_at",
            'max_products' => "ALTER TABLE tenants ADD COLUMN max_products INT NOT NULL DEFAULT 100 AFTER max_users",
            'max_transactions_per_month' => "ALTER TABLE tenants ADD COLUMN max_transactions_per_month INT NOT NULL DEFAULT 100 AFTER max_products",
            'addon_hrd_enabled' => "ALTER TABLE tenants ADD COLUMN addon_hrd_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER max_transactions_per_month",
            'max_employees' => "ALTER TABLE tenants ADD COLUMN max_employees INT NOT NULL DEFAULT 0 AFTER addon_hrd_enabled",
            'max_outlets' => "ALTER TABLE tenants ADD COLUMN max_outlets INT NOT NULL DEFAULT 1 AFTER max_employees",
            'trial_ends_at' => "ALTER TABLE tenants ADD COLUMN trial_ends_at DATETIME DEFAULT NULL AFTER max_outlets",
            'logo_struk' => "ALTER TABLE tenants ADD COLUMN logo_struk VARCHAR(255) DEFAULT NULL AFTER alamat_toko",
            'catatan_struk' => "ALTER TABLE tenants ADD COLUMN catatan_struk VARCHAR(180) DEFAULT NULL AFTER logo_struk",
        ];

        foreach ($columns as $column => $sql) {
            if (!tripay_column_exists($pdo, 'tenants', $column)) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable $e) {
                    // Biarkan instalasi lama tetap jalan; schema.sql sudah memuat struktur baru untuk instalasi fresh.
                }
            }
        }

        try {
            $pdo->exec("
                UPDATE tenants
                SET plan = CASE
                    WHEN paket_langganan = 'VIP' THEN 'Pro'
                    WHEN paket_langganan IN ('Gratis','Basic','Plus','Pro') THEN paket_langganan
                    ELSE 'Gratis'
                END
                WHERE plan = 'Gratis' AND paket_langganan <> 'Gratis'
            ");

            $pdo->exec("
                UPDATE tenants
                SET max_users = CASE plan WHEN 'Basic' THEN 2 WHEN 'Plus' THEN 5 WHEN 'Pro' THEN 10 ELSE 1 END,
                    max_products = CASE plan WHEN 'Basic' THEN 500 WHEN 'Plus' THEN 5000 WHEN 'Pro' THEN 20000 ELSE 100 END,
                    max_transactions_per_month = CASE plan WHEN 'Gratis' THEN 100 ELSE 0 END
                WHERE max_users = 1
                  AND max_products = 100
                  AND max_transactions_per_month = 100
            ");
        } catch (Throwable $e) {
            // Backfill limit bersifat best-effort untuk database lama.
        }
    }
}

if (!function_exists('tripay_ensure_registration_columns')) {
    function tripay_ensure_registration_columns(PDO $pdo) {
        try {
            $pdo->exec("UPDATE registrasi_toko SET paket_langganan = 'Pro' WHERE paket_langganan = 'VIP'");
            $pdo->exec("ALTER TABLE registrasi_toko MODIFY paket_langganan ENUM('Gratis','Basic','Plus','Pro') NOT NULL DEFAULT 'Gratis'");
            $pdo->exec("ALTER TABLE registrasi_toko MODIFY billing_cycle ENUM('monthly','yearly','trial') NOT NULL DEFAULT 'monthly'");
        } catch (Throwable $e) {
            // Abaikan untuk instalasi lama; kolom baru di bawah tetap dicoba.
        }

        $columns = [
            'billing_cycle' => "ALTER TABLE registrasi_toko ADD COLUMN billing_cycle ENUM('monthly','yearly','trial') NOT NULL DEFAULT 'monthly' AFTER amount",
            'addon_hrd_enabled' => "ALTER TABLE registrasi_toko ADD COLUMN addon_hrd_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER billing_cycle",
            'max_users' => "ALTER TABLE registrasi_toko ADD COLUMN max_users INT NOT NULL DEFAULT 1 AFTER addon_hrd_enabled",
            'max_products' => "ALTER TABLE registrasi_toko ADD COLUMN max_products INT NOT NULL DEFAULT 100 AFTER max_users",
            'max_transactions_per_month' => "ALTER TABLE registrasi_toko ADD COLUMN max_transactions_per_month INT NOT NULL DEFAULT 100 AFTER max_products",
            'max_employees' => "ALTER TABLE registrasi_toko ADD COLUMN max_employees INT NOT NULL DEFAULT 0 AFTER max_transactions_per_month",
            'max_outlets' => "ALTER TABLE registrasi_toko ADD COLUMN max_outlets INT NOT NULL DEFAULT 1 AFTER max_employees",
            'trial_ends_at' => "ALTER TABLE registrasi_toko ADD COLUMN trial_ends_at DATETIME DEFAULT NULL AFTER paid_at",
        ];

        foreach ($columns as $column => $sql) {
            if (!tripay_column_exists($pdo, 'registrasi_toko', $column)) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable $e) {
                    // Schema fresh sudah aman; ini hanya migrasi lunak.
                }
            }
        }
    }
}

if (!function_exists('tripay_unique_slug')) {
    function tripay_unique_slug(PDO $pdo, $namaToko) {
        $base = tripay_slug($namaToko);
        $slug = $base;
        $i = 2;

        while (true) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE slug = ?");
            $stmt->execute([$slug]);
            if ((int)$stmt->fetchColumn() === 0) {
                return $slug;
            }

            $suffix = '-' . $i++;
            $slug = substr($base, 0, 50 - strlen($suffix)) . $suffix;
        }
    }
}

if (!function_exists('tripay_create_transaction')) {
    function tripay_create_transaction(array $data) {
        if (!tripay_is_configured()) {
            throw new RuntimeException('Konfigurasi Tripay belum lengkap.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('Ekstensi cURL PHP belum aktif.');
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_URL => tripay_api_base_url() . '/transaction/create',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . TRIPAY_API_KEY],
            CURLOPT_FAILONERROR => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new RuntimeException('Gagal terhubung ke Tripay: ' . $error);
        }

        $json = json_decode((string)$response, true);
        if (!is_array($json)) {
            throw new RuntimeException('Respons Tripay tidak valid.');
        }

        if (empty($json['success'])) {
            throw new RuntimeException($json['message'] ?? 'Tripay menolak transaksi.');
        }

        return $json;
    }
}

if (!function_exists('tripay_get')) {
    function tripay_get($path, array $params = []) {
        if (!tripay_is_configured()) {
            throw new RuntimeException('Konfigurasi Tripay belum lengkap.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('Ekstensi cURL PHP belum aktif.');
        }

        $url = tripay_api_base_url() . '/' . ltrim($path, '/');
        if ($params) {
            $url .= '?' . http_build_query($params);
        }

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . TRIPAY_API_KEY],
            CURLOPT_FAILONERROR => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new RuntimeException('Gagal terhubung ke Tripay: ' . $error);
        }

        $json = json_decode((string)$response, true);
        if (!is_array($json)) {
            throw new RuntimeException('Respons Tripay tidak valid.');
        }

        if (empty($json['success'])) {
            throw new RuntimeException($json['message'] ?? 'Tripay menolak request.');
        }

        return $json;
    }
}

if (!function_exists('tripay_transaction_detail')) {
    function tripay_transaction_detail($reference) {
        $reference = tripay_clean($reference, 100);
        if ($reference === '') {
            throw new RuntimeException('Referensi Tripay kosong.');
        }

        return tripay_get('transaction/detail', ['reference' => $reference]);
    }
}

if (!function_exists('tripay_registration_payload_amount')) {
    function tripay_registration_payload_amount(array $payload) {
        foreach (['total_amount', 'amount'] as $key) {
            if (isset($payload[$key]) && is_numeric($payload[$key])) {
                return (int)$payload[$key];
            }
        }
        return null;
    }
}

if (!function_exists('tripay_apply_registration_status')) {
    function tripay_apply_registration_status(PDO $pdo, array $registration, array $payload) {
        $status = strtoupper(tripay_clean($payload['status'] ?? '', 20));
        $allowedStatus = ['PENDING', 'UNPAID', 'PAID', 'EXPIRED', 'FAILED', 'REFUND', 'CANCELLED'];
        if (!in_array($status, $allowedStatus, true)) {
            throw new RuntimeException('Status Tripay tidak didukung.');
        }

        $reference = tripay_clean(
            $payload['reference'] ?? ($registration['tripay_reference'] ?? ''),
            100
        );
        $payloadAmount = tripay_registration_payload_amount($payload);
        $registrationAmount = (int)($registration['amount'] ?? 0);

        if ($status === 'PAID' && $payloadAmount !== null && $registrationAmount > 0 && $payloadAmount !== $registrationAmount) {
            throw new RuntimeException('Nominal pembayaran Tripay tidak sesuai dengan registrasi.');
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $paidAt = !empty($payload['paid_at']) ? (int)$payload['paid_at'] : time();

        if ($status === 'PAID') {
            $tenantId = tripay_activate_registration($pdo, $registration);
            $stmt = $pdo->prepare("
                UPDATE registrasi_toko
                SET status = 'PAID',
                    tenant_id = ?,
                    tripay_reference = COALESCE(NULLIF(?, ''), tripay_reference),
                    payment_method = COALESCE(?, payment_method),
                    payment_name = COALESCE(?, payment_name),
                    callback_payload = ?,
                    paid_at = FROM_UNIXTIME(?)
                WHERE id = ?
            ");
            $stmt->execute([
                $tenantId,
                $reference,
                $payload['payment_method_code'] ?? ($payload['payment_method'] ?? null),
                $payload['payment_name'] ?? ($payload['payment_method'] ?? null),
                $payloadJson,
                $paidAt,
                (int)$registration['id'],
            ]);

            return ['status' => 'PAID', 'tenant_id' => $tenantId];
        }

        $stmt = $pdo->prepare("
            UPDATE registrasi_toko
            SET status = ?,
                tripay_reference = COALESCE(NULLIF(?, ''), tripay_reference),
                callback_payload = ?
            WHERE id = ?
              AND status <> 'PAID'
        ");
        $stmt->execute([$status, $reference, $payloadJson, (int)$registration['id']]);

        return ['status' => $status, 'tenant_id' => (int)($registration['tenant_id'] ?? 0)];
    }
}

if (!function_exists('tripay_activate_registration')) {
    function tripay_activate_registration(PDO $pdo, array $registration) {
        if (!empty($registration['tenant_id'])) {
            return (int)$registration['tenant_id'];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE email = ?");
        $stmt->execute([$registration['email']]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException('Email sudah terdaftar sebagai tenant.');
        }

        $slug = tripay_unique_slug($pdo, $registration['nama_toko']);
        $plan = kasirrapi_plan($registration['paket_langganan'] ?? 'Gratis');
        $billingCycle = $registration['billing_cycle'] ?? 'monthly';
        $months = $billingCycle === 'yearly' ? 12 : 1;
        $addonHrd = !empty($registration['addon_hrd_enabled']);
        $maxEmployees = $addonHrd ? (int)(kasirrapi_addons()['hrd']['max_employees'] ?? 20) : 0;
        $isFree = ($registration['paket_langganan'] ?? 'Gratis') === 'Gratis';
        $isTrial = $billingCycle === 'trial';
        $planExpiredAt = $isFree
            ? null
            : ($isTrial ? date('Y-m-d H:i:s', strtotime('+14 days')) : date('Y-m-d H:i:s', strtotime('+' . $months . ' month')));
        $trialEndsAt = $isTrial ? $planExpiredAt : null;

        $stmt = $pdo->prepare("
            INSERT INTO tenants (
                nama_toko, slug, nama_pemilik, email, no_wa, paket_langganan, status,
                plan, plan_expired_at, max_users, max_products, max_transactions_per_month,
                addon_hrd_enabled, max_employees, max_outlets, trial_ends_at
            )
            VALUES (?, ?, ?, ?, ?, ?, 'Aktif', ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([
            $registration['nama_toko'],
            $slug,
            $registration['nama_pemilik'],
            $registration['email'],
            $registration['no_wa'] ?: null,
            $registration['paket_langganan'],
            $registration['paket_langganan'],
            $planExpiredAt,
            (int)$plan['max_users'],
            (int)$plan['max_products'],
            (int)$plan['max_transactions_per_month'],
            $addonHrd ? 1 : 0,
            $maxEmployees,
            $trialEndsAt,
        ]);

        $tenantId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO users (tenant_id, nama, email, password, role)
            VALUES (?, ?, ?, ?, 'Owner')
        ");
        $stmt->execute([
            $tenantId,
            $registration['nama_pemilik'],
            $registration['email'],
            $registration['password_hash'],
        ]);

        return $tenantId;
    }
}
