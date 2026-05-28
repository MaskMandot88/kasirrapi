<?php
// Helper notifikasi aplikasi dan reminder langganan.

if (!function_exists('app_notifications_ensure_tables')) {
    function app_notifications_ensure_tables(PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notifikasi (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                pengirim_id INT DEFAULT NULL,
                target_user_id INT DEFAULT NULL,
                target_role ENUM('Owner','Admin','Gudang','Kasir','HRD','Semua') DEFAULT 'Semua',
                tipe ENUM('Info','Pengumuman','Approval','Absensi','Gaji','Piutang','Stok','Sistem') NOT NULL DEFAULT 'Info',
                judul VARCHAR(150) NOT NULL,
                pesan TEXT NOT NULL,
                link VARCHAR(255) DEFAULT NULL,
                prioritas ENUM('Normal','Penting','Darurat') NOT NULL DEFAULT 'Normal',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_notifikasi_tenant_created (tenant_id, created_at),
                KEY idx_notifikasi_target_user (tenant_id, target_user_id),
                KEY idx_notifikasi_target_role (tenant_id, target_role),
                KEY idx_notifikasi_tipe (tenant_id, tipe)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notifikasi_read (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                notifikasi_id INT NOT NULL,
                user_id INT NOT NULL,
                read_at DATETIME NOT NULL,
                UNIQUE KEY uniq_read_user_notif (notifikasi_id, user_id),
                KEY idx_read_tenant_user (tenant_id, user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
}

if (!function_exists('app_notification_create')) {
    function app_notification_create(PDO $pdo, $tenantId, $pengirimId, $targetUserId, $targetRole, $tipe, $judul, $pesan, $link = null, $prioritas = 'Normal') {
        app_notifications_ensure_tables($pdo);

        $stmt = $pdo->prepare("
            INSERT INTO notifikasi
                (tenant_id, pengirim_id, target_user_id, target_role, tipe, judul, pesan, link, prioritas)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            (int)$tenantId,
            $pengirimId !== null ? (int)$pengirimId : null,
            $targetUserId !== null ? (int)$targetUserId : null,
            $targetRole,
            $tipe,
            $judul,
            $pesan,
            $link !== '' ? $link : null,
            $prioritas,
        ]);
    }
}

if (!function_exists('app_owner_expiry_notification_text')) {
    function app_owner_expiry_notification_text(array $tenant) {
        $expiredAt = (string)($tenant['plan_expired_at'] ?? '');
        $expiredLabel = $expiredAt !== '' ? date('d/m/Y H:i', strtotime($expiredAt)) : '-';
        $plan = $tenant['plan'] ?: ($tenant['paket_langganan'] ?? 'Paket');

        return [
            'judul' => 'Masa Aktif Tinggal 3 Hari',
            'pesan' => 'Masa aktif ' . $plan . ' untuk ' . ($tenant['nama_toko'] ?? 'toko Anda') . ' tersisa 3 hari dan akan berakhir pada ' . $expiredLabel . '. Segera perpanjang agar layanan tetap aktif.',
        ];
    }
}

if (!function_exists('app_notify_owner_expiry_if_needed')) {
    function app_notify_owner_expiry_if_needed(PDO $pdo, $tenantId) {
        app_notifications_ensure_tables($pdo);

        $stmt = $pdo->prepare("
            SELECT id, nama_toko, paket_langganan, plan, plan_expired_at, status
            FROM tenants
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([(int)$tenantId]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tenant || empty($tenant['plan_expired_at']) || ($tenant['status'] ?? '') !== 'Aktif') {
            return false;
        }

        $expiresAt = strtotime((string)$tenant['plan_expired_at']);
        if (!$expiresAt) {
            return false;
        }

        $expiresDate = date('Y-m-d', $expiresAt);
        $hMinusThreeDate = date('Y-m-d', strtotime('+3 days'));
        if ($expiresDate !== $hMinusThreeDate) {
            return false;
        }

        $text = app_owner_expiry_notification_text($tenant);
        $dedupeKey = date('Y-m-d H:i:s', $expiresAt);

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM notifikasi
            WHERE tenant_id = ?
              AND target_role = 'Owner'
              AND tipe = 'Sistem'
              AND judul = ?
              AND pesan LIKE ?
        ");
        $stmt->execute([(int)$tenantId, $text['judul'], '%' . $dedupeKey . '%']);
        if ((int)$stmt->fetchColumn() > 0) {
            return false;
        }

        $pesan = $text['pesan'] . "\n\nKode pengingat: " . $dedupeKey;
        app_notification_create($pdo, (int)$tenantId, null, null, 'Owner', 'Sistem', $text['judul'], $pesan, '../notifikasi/index.php', 'Penting');
        return true;
    }
}

if (!function_exists('app_notify_all_owner_expiry_warnings')) {
    function app_notify_all_owner_expiry_warnings(PDO $pdo) {
        $stmt = $pdo->query("
            SELECT id
            FROM tenants
            WHERE status = 'Aktif'
              AND plan_expired_at IS NOT NULL
              AND DATE(plan_expired_at) = DATE_ADD(CURDATE(), INTERVAL 3 DAY)
        ");

        $sent = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $tenantId) {
            if (app_notify_owner_expiry_if_needed($pdo, (int)$tenantId)) {
                $sent++;
            }
        }

        return $sent;
    }
}
