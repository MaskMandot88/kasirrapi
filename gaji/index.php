<?php
session_start();
require_once '../config/database.php';
require_once '../includes/ui.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}
if (!ui_is_role(['Owner','Admin','HRD'])) {
    die('Akses ditolak. Modul gaji hanya untuk Owner/Admin/HRD.');
}

$tenant_id = (int)$_SESSION['tenant_id'];

$stmt = $pdo->prepare("SELECT pp.*, u.nama AS pembuat
                       FROM payroll_periode pp
                       LEFT JOIN users u ON u.id = pp.dibuat_by
                       WHERE pp.tenant_id = ?
                       ORDER BY pp.periode_mulai DESC, pp.id DESC
                       LIMIT 100");
$stmt->execute([$tenant_id]);
$periode = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM karyawan_gaji WHERE tenant_id = ? AND aktif = 1");
$stmt->execute([$tenant_id]);
$total_setting = (int)$stmt->fetchColumn();

$total_belum_bayar = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payroll_detail WHERE tenant_id = ? AND status_bayar = 'Belum Dibayar'");
    $stmt->execute([$tenant_id]);
    $total_belum_bayar = (int)$stmt->fetchColumn();
} catch (Throwable $e) {}

ui_head('Gaji Karyawan');
ui_nav($pdo, 'Gaji Karyawan');
?>

<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-emerald-400">Gaji Karyawan</h2>
        <p class="text-slate-400">Generate gaji dari absensi, jadwal, izin/cuti/sakit, dan keterlambatan.</p>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        <a href="setting.php" class="btn btn-secondary">Setting Gaji</a>
        <a href="generate.php" class="btn btn-primary">Buat Periode</a>
    </div>
</div>

<?php if (!empty($_SESSION['flash']['success'])): ?>
    <div class="mb-4 p-3 bg-emerald-900/50 border border-emerald-500 rounded-xl"><?= h($_SESSION['flash']['success']); unset($_SESSION['flash']['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash']['error'])): ?>
    <div class="mb-4 p-3 bg-red-900/50 border border-red-500 rounded-xl"><?= h($_SESSION['flash']['error']); unset($_SESSION['flash']['error']); ?></div>
<?php endif; ?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <div class="stat-card">
        <div class="label">Setting Aktif</div>
        <div class="text-2xl font-extrabold"><?= $total_setting ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Periode Payroll</div>
        <div class="text-2xl font-extrabold"><?= count($periode) ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Belum Dibayar</div>
        <div class="text-2xl font-extrabold"><?= $total_belum_bayar ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Aksi Cepat</div>
        <a href="generate.php" class="text-emerald-300 underline font-bold">Generate bulan ini</a>
    </div>
</div>

<div class="mobile-card-list">
    <?php foreach ($periode as $p): ?>
    <div class="mobile-card">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div class="min-w-0">
                <h3 class="text-xl font-bold text-white"><?= h($p['nama_periode']) ?></h3>
                <p class="text-slate-400 text-sm"><?= date('d/m/Y', strtotime($p['periode_mulai'])) ?> - <?= date('d/m/Y', strtotime($p['periode_selesai'])) ?></p>
                <p class="text-slate-500 text-sm mt-1">Dibuat oleh: <?= h($p['pembuat'] ?: '-') ?></p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-3 md:min-w-[420px]">
                <div class="bg-slate-950 rounded-xl p-3">
                    <div class="label">Total</div>
                    <div class="value"><?= rupiah($p['total_gaji']) ?></div>
                </div>
                <div class="bg-slate-950 rounded-xl p-3">
                    <div class="label">Status</div>
                    <div class="value"><?= h($p['status']) ?></div>
                </div>
                <a href="detail.php?id=<?= (int)$p['id'] ?>" class="col-span-2 md:col-span-1 btn btn-secondary">Detail</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (!$periode): ?>
    <div class="mobile-card text-center text-slate-500">Belum ada periode gaji.</div>
    <?php endif; ?>
</div>

<?php ui_footer(); ?>
