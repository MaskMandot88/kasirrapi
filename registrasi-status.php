<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/pwa.php';
require_once __DIR__ . '/includes/tripay.php';

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

tripay_registration_table($pdo);

if (empty($_SESSION['csrf_tripay_sync'])) {
    $_SESSION['csrf_tripay_sync'] = bin2hex(random_bytes(32));
}

$ref = tripay_clean($_GET['ref'] ?? '', 60);
$registration = null;

if ($ref !== '') {
    $stmt = $pdo->prepare("
        SELECT *
        FROM registrasi_toko
        WHERE merchant_ref = ? OR tripay_reference = ?
        LIMIT 1
    ");
    $stmt->execute([$ref, $ref]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
}

$status = $registration['status'] ?? 'TIDAK_DITEMUKAN';
$isPaid = $status === 'PAID' && !empty($registration['tenant_id']);
$statusSuccess = $_SESSION['flash']['tripay_status_success'] ?? '';
$statusError = $_SESSION['flash']['tripay_status_error'] ?? '';
unset($_SESSION['flash']['tripay_status_success'], $_SESSION['flash']['tripay_status_error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Registrasi - <?= h(APP_NAME) ?></title>
    <meta name="theme-color" content="#FF6A00">
    <link rel="icon" type="image/png" href="<?= h(asset_url('app/favicon.png')) ?>?v=<?= h(APP_VERSION) ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <?php pwa_head_tags(); ?>
    <style>
        body{min-height:100vh;background:radial-gradient(circle at top left,rgba(255,106,0,.18),transparent 32rem),#020617;color:#e2e8f0}
        .card{background:rgba(15,23,42,.94);border:1px solid rgba(148,163,184,.16);box-shadow:0 28px 80px rgba(0,0,0,.32)}
        .btn{min-height:46px;border-radius:14px;padding:12px 18px;font-weight:950;display:inline-flex;align-items:center;justify-content:center}
        .btn-primary{background:#ff6a00;color:#fff}
        .btn-secondary{background:#1e293b;color:#e2e8f0;border:1px solid #334155}
    </style>
</head>
<body class="px-4 py-8">
<main class="max-w-2xl mx-auto">
    <div class="text-center mb-7">
        <img src="<?= h(asset_url('app/logo-full.png')) ?>?v=<?= h(APP_VERSION) ?>" alt="<?= h(APP_NAME) ?>" class="w-[190px] mx-auto">
    </div>

    <section class="card rounded-3xl p-6 md:p-8">
        <?php if ($statusSuccess): ?>
            <div class="rounded-2xl border border-emerald-600/40 bg-emerald-950/30 text-emerald-100 p-4 text-sm leading-6 mb-5">
                <?= h($statusSuccess) ?>
            </div>
        <?php endif; ?>
        <?php if ($statusError): ?>
            <div class="rounded-2xl border border-red-600/40 bg-red-950/30 text-red-100 p-4 text-sm leading-6 mb-5">
                <?= h($statusError) ?>
            </div>
        <?php endif; ?>

        <?php if (!$registration): ?>
            <h1 class="text-3xl font-black text-white">Registrasi tidak ditemukan</h1>
            <p class="text-slate-400 mt-3 leading-7">Nomor registrasi tidak valid atau data pembayaran belum tercatat.</p>
            <a href="<?= h(app_url('registrasi.php')) ?>" class="btn btn-primary mt-6 w-full">Registrasi Ulang</a>
        <?php else: ?>
            <div class="text-sm font-bold text-orange-300 mb-2"><?= h($registration['merchant_ref']) ?></div>
            <h1 class="text-3xl font-black text-white">
                <?= $isPaid ? 'Akun toko sudah aktif' : 'Status pembayaran: ' . h($status) ?>
            </h1>
            <p class="text-slate-400 mt-3 leading-7">
                Toko: <b class="text-slate-200"><?= h($registration['nama_toko']) ?></b><br>
                Paket: <b class="text-slate-200"><?= h($registration['paket_langganan']) ?></b><br>
                Siklus: <b class="text-slate-200"><?= h($registration['billing_cycle'] ?? 'monthly') ?></b><br>
                Add-on HRD: <b class="text-slate-200"><?= !empty($registration['addon_hrd_enabled']) ? 'Aktif' : 'Tidak' ?></b><br>
                Total: <b class="text-slate-200">Rp <?= number_format((int)$registration['amount'], 0, ',', '.') ?></b><br>
                <?php if (!empty($registration['tripay_reference'])): ?>
                    Referensi Tripay: <b class="text-slate-200"><?= h($registration['tripay_reference']) ?></b><br>
                <?php endif; ?>
                <?php if (!empty($registration['pay_code'])): ?>
                    Kode Bayar: <b class="text-slate-200"><?= h($registration['pay_code']) ?></b><br>
                <?php endif; ?>
                <?php if (!empty($registration['expired_at'])): ?>
                    Batas Bayar: <b class="text-slate-200"><?= h(date('d/m/Y H:i', strtotime($registration['expired_at']))) ?></b>
                <?php endif; ?>
            </p>

            <?php if ($isPaid): ?>
                <div class="rounded-2xl border border-emerald-600/40 bg-emerald-950/30 text-emerald-100 p-4 text-sm leading-6 mt-6">
                    Pembayaran sudah terkonfirmasi. Silakan login memakai email dan password yang dibuat saat registrasi.
                </div>
                <a href="<?= h(app_url('auth/login.php')) ?>" class="btn btn-primary mt-6 w-full">Login Sekarang</a>
            <?php else: ?>
                <div class="rounded-2xl border border-yellow-600/40 bg-yellow-950/30 text-yellow-100 p-4 text-sm leading-6 mt-6">
                    Jika Anda sudah membayar, tunggu beberapa saat sampai callback Tripay diterima. Halaman ini bisa dibuka ulang untuk cek status.
                </div>
                <?php if (!empty($registration['checkout_url'])): ?>
                    <a href="<?= h($registration['checkout_url']) ?>" class="btn btn-primary mt-6 w-full">Lanjutkan Pembayaran</a>
                <?php endif; ?>
                <?php if (!empty($registration['tripay_reference']) && tripay_is_configured()): ?>
                    <form method="POST" action="<?= h(app_url('tripay-sync.php')) ?>" class="mt-3">
                        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_tripay_sync']) ?>">
                        <input type="hidden" name="ref" value="<?= h($registration['merchant_ref']) ?>">
                        <button type="submit" class="btn btn-secondary w-full">Cek Status Tripay</button>
                    </form>
                <?php endif; ?>
                <a href="<?= h(app_url('registrasi-status.php?ref=' . rawurlencode($registration['merchant_ref']))) ?>" class="btn btn-secondary mt-3 w-full">Refresh Halaman</a>
                <a href="<?= h(app_url('index.php')) ?>" class="btn btn-secondary mt-3 w-full">Kembali ke Beranda</a>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
