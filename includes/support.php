<?php
// includes/support.php
// Helper tiket eskalasi live chat untuk Super Admin.

if (!function_exists('support_tickets_table')) {
    function support_tickets_table(PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS support_tickets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ticket_code VARCHAR(40) NOT NULL UNIQUE,
                tenant_id INT DEFAULT NULL,
                user_id INT DEFAULT NULL,
                email VARCHAR(100) NOT NULL,
                nama_toko VARCHAR(100) DEFAULT NULL,
                nama_user VARCHAR(100) DEFAULT NULL,
                subject VARCHAR(180) NOT NULL,
                message TEXT NOT NULL,
                chat_history LONGTEXT DEFAULT NULL,
                cs_name VARCHAR(30) DEFAULT NULL,
                source VARCHAR(50) NOT NULL DEFAULT 'live_chat',
                status ENUM('Baru','Diproses','Selesai') NOT NULL DEFAULT 'Baru',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_support_status (status),
                KEY idx_support_email (email),
                KEY idx_support_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
}

if (!function_exists('support_find_registered_contact')) {
    function support_find_registered_contact(PDO $pdo, $email) {
        $email = strtolower(trim((string)$email));

        $stmt = $pdo->prepare("
            SELECT u.id AS user_id, u.tenant_id, u.nama AS nama_user, u.email,
                   t.nama_toko, t.nama_pemilik
            FROM users u
            LEFT JOIN tenants t ON t.id = u.tenant_id
            WHERE LOWER(u.email) = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;

        $stmt = $pdo->prepare("
            SELECT NULL AS user_id, id AS tenant_id, nama_pemilik AS nama_user, email,
                   nama_toko, nama_pemilik
            FROM tenants
            WHERE LOWER(email) = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;

        $stmt = $pdo->prepare("
            SELECT NULL AS user_id, tenant_id, nama_pemilik AS nama_user, email,
                   nama_toko, nama_pemilik
            FROM registrasi_toko
            WHERE LOWER(email) = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
