<?php
// includes/plans.php
// Konfigurasi paket harga dan limit fitur KasirRapi.

if (!function_exists('kasirrapi_plans')) {
    function kasirrapi_plans() {
        return [
            'Gratis' => [
                'name' => 'Gratis',
                'monthly_price' => 0,
                'yearly_price' => 0,
                'target' => 'Warung kecil, coba aplikasi',
                'badge' => '',
                'max_users' => 1,
                'max_products' => 100,
                'max_transactions_per_month' => 100,
                'addon_hrd_available' => false,
                'features' => [
                    '1 user Owner',
                    '100 barang aktif',
                    '100 transaksi/bulan',
                    'Kasir, stok dasar, scan barcode',
                    'Laporan harian sederhana',
                ],
            ],
            'Basic' => [
                'name' => 'Basic',
                'monthly_price' => 49000,
                'yearly_price' => 490000,
                'target' => 'Kasir + stok ringan',
                'badge' => '',
                'max_users' => 2,
                'max_products' => 500,
                'max_transactions_per_month' => 0,
                'addon_hrd_available' => false,
                'features' => [
                    '2 user: Owner + Kasir',
                    '500 barang aktif',
                    'Transaksi unlimited',
                    'Barcode dan satuan utama/ecer',
                    'Laporan harian & bulanan',
                ],
            ],
            'Plus' => [
                'name' => 'Plus',
                'monthly_price' => 79000,
                'yearly_price' => 790000,
                'target' => 'Paket utama untuk toko aktif',
                'badge' => 'Paling Populer',
                'max_users' => 5,
                'max_products' => 5000,
                'max_transactions_per_month' => 0,
                'addon_hrd_available' => true,
                'features' => [
                    '5 user: Owner, Admin, Kasir, Gudang',
                    '5.000 barang aktif',
                    'Supplier, pembelian, foto nota',
                    'Piutang, pelanggan, laporan lengkap',
                    'Export dan custom logo struk',
                ],
            ],
            'Pro' => [
                'name' => 'Pro',
                'monthly_price' => 129000,
                'yearly_price' => 1290000,
                'target' => 'Kontrol lengkap untuk toko/grosir',
                'badge' => '',
                'max_users' => 10,
                'max_products' => 20000,
                'max_transactions_per_month' => 0,
                'addon_hrd_available' => true,
                'features' => [
                    '10 user dan role lengkap',
                    '20.000 barang aktif',
                    'Audit harga dan stok',
                    'Laporan detail dan export lengkap',
                    'Support prioritas',
                ],
            ],
        ];
    }
}

if (!function_exists('kasirrapi_addons')) {
    function kasirrapi_addons() {
        return [
            'hrd' => [
                'name' => 'Absensi & Gaji',
                'monthly_price' => 39000,
                'yearly_price' => 390000,
                'max_employees' => 20,
                'features' => [
                    'Absensi wajah',
                    'Validasi lokasi toko',
                    'Rekap izin/sakit/terlambat',
                    'Gaji pokok dan potongan',
                    'Export rekap',
                ],
            ],
            'outlet' => [
                'name' => 'Extra Outlet',
                'monthly_price' => 49000,
                'yearly_price' => 490000,
                'max_outlets_added' => 1,
                'features' => [
                    'Tambahan 1 outlet',
                    'User mengikuti paket utama',
                    'Laporan cabang untuk Pro/add-on',
                ],
            ],
        ];
    }
}

if (!function_exists('kasirrapi_plan')) {
    function kasirrapi_plan($code) {
        $plans = kasirrapi_plans();
        return $plans[$code] ?? $plans['Gratis'];
    }
}

if (!function_exists('kasirrapi_format_price')) {
    function kasirrapi_format_price($amount) {
        $amount = (int)$amount;
        if ($amount <= 0) return 'Rp0';
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

if (!function_exists('kasirrapi_unlimited_label')) {
    function kasirrapi_unlimited_label($value, $suffix = '') {
        $value = (int)$value;
        return $value === 0 ? 'unlimited' : number_format($value, 0, ',', '.') . $suffix;
    }
}

if (!function_exists('kasirrapi_tenant_subscription')) {
    function kasirrapi_tenant_subscription(PDO $pdo, $tenantId) {
        $fallback = kasirrapi_plan('Gratis');
        $subscription = [
            'plan' => 'Gratis',
            'max_users' => (int)$fallback['max_users'],
            'max_products' => (int)$fallback['max_products'],
            'max_transactions_per_month' => (int)$fallback['max_transactions_per_month'],
            'addon_hrd_enabled' => 0,
            'max_employees' => 0,
            'max_outlets' => 1,
        ];

        try {
            $stmt = $pdo->prepare("
                SELECT paket_langganan, plan, max_users, max_products, max_transactions_per_month,
                       addon_hrd_enabled, max_employees, max_outlets
                FROM tenants
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([(int)$tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return $subscription;

            $planCode = $row['plan'] ?: ($row['paket_langganan'] ?: 'Gratis');
            if ($planCode === 'VIP') $planCode = 'Pro';
            $plan = kasirrapi_plan($planCode);

            return [
                'plan' => $plan['name'],
                'max_users' => isset($row['max_users']) ? (int)$row['max_users'] : (int)$plan['max_users'],
                'max_products' => isset($row['max_products']) ? (int)$row['max_products'] : (int)$plan['max_products'],
                'max_transactions_per_month' => isset($row['max_transactions_per_month']) ? (int)$row['max_transactions_per_month'] : (int)$plan['max_transactions_per_month'],
                'addon_hrd_enabled' => (int)($row['addon_hrd_enabled'] ?? 0),
                'max_employees' => (int)($row['max_employees'] ?? 0),
                'max_outlets' => (int)($row['max_outlets'] ?? 1),
            ];
        } catch (Throwable $e) {
            return $subscription;
        }
    }
}

if (!function_exists('kasirrapi_feature_allowed')) {
    function kasirrapi_feature_allowed(array $subscription, $feature) {
        $plan = $subscription['plan'] ?? 'Gratis';

        if (in_array($feature, ['kasir', 'barang', 'stok', 'barcode'], true)) {
            return true;
        }

        if (in_array($feature, ['supplier', 'pembelian', 'piutang', 'pelanggan', 'laporan_lengkap', 'export', 'custom_struk'], true)) {
            return in_array($plan, ['Plus', 'Pro'], true);
        }

        if (in_array($feature, ['audit_harga', 'audit_stok', 'laporan_detail'], true)) {
            return $plan === 'Pro';
        }

        if (in_array($feature, ['absensi', 'gaji', 'hrd'], true)) {
            return !empty($subscription['addon_hrd_enabled']);
        }

        return false;
    }
}

if (!function_exists('kasirrapi_limit_reached')) {
    function kasirrapi_limit_reached($limit, $current) {
        $limit = (int)$limit;
        if ($limit === 0) return false;
        return (int)$current >= $limit;
    }
}
