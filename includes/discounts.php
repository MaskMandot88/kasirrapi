<?php
// includes/discounts.php
// Aturan diskon otomatis untuk transaksi kasir.

if (!function_exists('discounts_ensure_schema')) {
    function discounts_ensure_schema(PDO $pdo) {
        static $done = false;
        if ($done) return;

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS diskon_rules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT NOT NULL,
                nama_diskon VARCHAR(120) NOT NULL,
                kondisi ENUM('minimal_belanja','produk_tertentu','qty_produk','metode_bayar') NOT NULL DEFAULT 'minimal_belanja',
                barang_id INT NULL,
                metode_bayar ENUM('Tunai','QRIS','Transfer','Hutang') NULL,
                min_subtotal DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                min_qty INT NOT NULL DEFAULT 0,
                tipe_diskon ENUM('persen','nominal') NOT NULL DEFAULT 'persen',
                nilai_diskon DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                max_diskon DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                mulai DATE NULL,
                selesai DATE NULL,
                aktif TINYINT(1) NOT NULL DEFAULT 1,
                prioritas INT NOT NULL DEFAULT 100,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_diskon_tenant_aktif (tenant_id, aktif),
                KEY idx_diskon_barang (barang_id),
                KEY idx_diskon_periode (mulai, selesai)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $done = true;
    }
}

if (!function_exists('discounts_active_rules')) {
    function discounts_active_rules(PDO $pdo, int $tenantId, ?string $date = null) {
        discounts_ensure_schema($pdo);
        $date = $date ?: date('Y-m-d');

        $stmt = $pdo->prepare("
            SELECT dr.*, b.nama_barang
            FROM diskon_rules dr
            LEFT JOIN barang b ON b.id = dr.barang_id AND b.tenant_id = dr.tenant_id
            WHERE dr.tenant_id = ?
              AND dr.aktif = 1
              AND (dr.mulai IS NULL OR dr.mulai <= ?)
              AND (dr.selesai IS NULL OR dr.selesai >= ?)
            ORDER BY dr.prioritas ASC, dr.id ASC
        ");
        $stmt->execute([$tenantId, $date, $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('discounts_money')) {
    function discounts_money($base, $type, $value, $max = 0) {
        $base = max(0, (float)$base);
        $value = max(0, (float)$value);
        $max = max(0, (float)$max);

        if ($base <= 0 || $value <= 0) return 0.0;

        $discount = $type === 'persen' ? ($base * min($value, 100) / 100) : $value;
        if ($max > 0) {
            $discount = min($discount, $max);
        }

        return min($base, round($discount, 2));
    }
}

if (!function_exists('discounts_calculate')) {
    function discounts_calculate(PDO $pdo, int $tenantId, array $items, string $paymentMethod = 'Tunai', ?string $date = null) {
        $subtotal = 0.0;
        $itemTotals = [];
        $itemQty = [];

        foreach ($items as $item) {
            $barangId = (int)($item['barang_id'] ?? $item['id'] ?? 0);
            $qty = max(0, (int)($item['qty'] ?? 0));
            $lineSubtotal = max(0, (float)($item['subtotal'] ?? 0));
            if ($barangId <= 0 || $qty <= 0 || $lineSubtotal <= 0) continue;

            $subtotal += $lineSubtotal;
            $itemTotals[$barangId] = ($itemTotals[$barangId] ?? 0) + $lineSubtotal;
            $itemQty[$barangId] = ($itemQty[$barangId] ?? 0) + $qty;
        }

        $discounts = [];
        $totalDiscount = 0.0;

        foreach (discounts_active_rules($pdo, $tenantId, $date) as $rule) {
            $condition = $rule['kondisi'];
            $base = 0.0;
            $eligible = false;

            if ($condition === 'minimal_belanja') {
                $eligible = $subtotal >= (float)$rule['min_subtotal'];
                $base = $subtotal;
            } elseif ($condition === 'metode_bayar') {
                $eligible = $paymentMethod === (string)$rule['metode_bayar']
                    && $subtotal >= (float)$rule['min_subtotal'];
                $base = $subtotal;
            } elseif ($condition === 'produk_tertentu') {
                $barangId = (int)$rule['barang_id'];
                $eligible = $barangId > 0 && isset($itemTotals[$barangId]);
                $base = $itemTotals[$barangId] ?? 0;
            } elseif ($condition === 'qty_produk') {
                $barangId = (int)$rule['barang_id'];
                $eligible = $barangId > 0
                    && isset($itemQty[$barangId])
                    && $itemQty[$barangId] >= max(1, (int)$rule['min_qty']);
                $base = $itemTotals[$barangId] ?? 0;
            }

            if (!$eligible || $base <= 0) continue;

            $amount = discounts_money($base, $rule['tipe_diskon'], $rule['nilai_diskon'], $rule['max_diskon']);
            $remaining = max(0, $subtotal - $totalDiscount);
            $amount = min($amount, $remaining);

            if ($amount <= 0) continue;

            $totalDiscount += $amount;
            $discounts[] = [
                'id' => (int)$rule['id'],
                'nama' => $rule['nama_diskon'],
                'kondisi' => $condition,
                'barang' => $rule['nama_barang'] ?? '',
                'nilai' => $amount,
            ];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'diskon' => round($totalDiscount, 2),
            'total' => round(max(0, $subtotal - $totalDiscount), 2),
            'items' => $discounts,
        ];
    }
}
