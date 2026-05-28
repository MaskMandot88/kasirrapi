<?php
// includes/receipt.php
// Helper pengaturan struk per tenant.

if (file_exists(__DIR__ . '/../config/app.php')) {
    require_once __DIR__ . '/../config/app.php';
}

if (!function_exists('receipt_column_exists')) {
    function receipt_column_exists(PDO $pdo, $table, $column) {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$column]);
            return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('receipt_ensure_settings_schema')) {
    function receipt_ensure_settings_schema(PDO $pdo) {
        static $done = false;
        if ($done) return;

        $columns = [
            'logo_struk' => [
                "ALTER TABLE tenants ADD COLUMN logo_struk VARCHAR(255) DEFAULT NULL AFTER alamat_toko",
                "ALTER TABLE tenants ADD COLUMN logo_struk VARCHAR(255) DEFAULT NULL",
            ],
            'catatan_struk' => [
                "ALTER TABLE tenants ADD COLUMN catatan_struk VARCHAR(180) DEFAULT NULL AFTER logo_struk",
                "ALTER TABLE tenants ADD COLUMN catatan_struk VARCHAR(180) DEFAULT NULL",
            ],
        ];

        foreach ($columns as $column => $queries) {
            if (!receipt_column_exists($pdo, 'tenants', $column)) {
                foreach ($queries as $sql) {
                    try {
                        $pdo->exec($sql);
                        break;
                    } catch (Throwable $e) {
                        // Coba fallback tanpa AFTER untuk instalasi lama.
                    }
                }
            }
        }

        $done = true;
    }
}

if (!function_exists('receipt_upload_dir')) {
    function receipt_upload_dir($tenantId) {
        return __DIR__ . '/../public/uploads/tenant_' . (int)$tenantId . '/struk';
    }
}

if (!function_exists('receipt_logo_path')) {
    function receipt_logo_path($tenantId, $filename) {
        $filename = basename((string)$filename);
        if ($filename === '') return '';
        return receipt_upload_dir($tenantId) . '/' . $filename;
    }
}

if (!function_exists('receipt_logo_url')) {
    function receipt_logo_url($tenantId, $filename) {
        $filename = basename((string)$filename);
        if ($filename === '') return '';
        return app_url('public/uploads/tenant_' . (int)$tenantId . '/struk/' . rawurlencode($filename));
    }
}
