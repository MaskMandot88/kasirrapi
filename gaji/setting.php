<?php
session_start();
require_once '../config/database.php';
require_once '../includes/ui.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}
if (!ui_is_role(['Owner','Admin','HRD'])) {
    die('Akses ditolak.');
}

$tenant_id = (int)$_SESSION['tenant_id'];
$subscription = kasirrapi_tenant_subscription($pdo, $tenant_id);

if (!kasirrapi_feature_allowed($subscription, 'gaji')) {
    die('Fitur Gaji tersedia melalui add-on Absensi & Gaji. Silakan upgrade paket toko.');
}

$stmt = $pdo->prepare("SELECT u.id, u.nama, u.email, u.role,
                              kg.gaji_pokok, kg.uang_makan_per_hari, kg.uang_transport_per_hari,
                              kg.tarif_lembur_per_jam, kg.potongan_terlambat_per_menit,
                              kg.potongan_pulang_cepat_per_menit, kg.potongan_alpha_per_hari,
                              kg.jam_kerja_normal_menit, kg.aktif
                       FROM users u
                       LEFT JOIN karyawan_gaji kg ON kg.user_id = u.id AND kg.tenant_id = u.tenant_id
                       WHERE u.tenant_id = ?
                       ORDER BY FIELD(u.role,'Owner','Admin','HRD','Gudang','Kasir'), u.nama ASC");
$stmt->execute([$tenant_id]);
$users = $stmt->fetchAll();

ui_head('Setting Gaji');
ui_nav($pdo, 'Setting Gaji');
?>

<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-emerald-400">Setting Gaji Karyawan</h2>
        <p class="text-slate-400">Atur komponen gaji per karyawan.</p>
    </div>
    <a href="index.php" class="btn btn-secondary">← Gaji</a>
</div>

<?php if (!empty($_SESSION['flash']['success'])): ?>
    <div class="mb-4 p-3 bg-emerald-900/50 border border-emerald-500 rounded-xl"><?= h($_SESSION['flash']['success']); unset($_SESSION['flash']['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash']['error'])): ?>
    <div class="mb-4 p-3 bg-red-900/50 border border-red-500 rounded-xl"><?= h($_SESSION['flash']['error']); unset($_SESSION['flash']['error']); ?></div>
<?php endif; ?>

<div class="mobile-card-list">
    <?php foreach ($users as $u): ?>
    <form method="POST" action="proses_setting.php" class="mobile-card">
        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">

        <div class="mb-4">
            <h3 class="text-xl font-bold"><?= h($u['nama']) ?></h3>
            <p class="text-slate-400 text-sm"><?= h($u['role']) ?> · <?= h($u['email']) ?></p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <label>
                <span class="label">Gaji Pokok</span>
                <input type="number" step="0.01" min="0" name="gaji_pokok" value="<?= h($u['gaji_pokok'] ?? 0) ?>" class="app-input mt-1">
            </label>
            <label>
                <span class="label">Uang Makan / Hari</span>
                <input type="number" step="0.01" min="0" name="uang_makan_per_hari" value="<?= h($u['uang_makan_per_hari'] ?? 0) ?>" class="app-input mt-1">
            </label>
            <label>
                <span class="label">Transport / Hari</span>
                <input type="number" step="0.01" min="0" name="uang_transport_per_hari" value="<?= h($u['uang_transport_per_hari'] ?? 0) ?>" class="app-input mt-1">
            </label>
            <label>
                <span class="label">Lembur / Jam</span>
                <input type="number" step="0.01" min="0" name="tarif_lembur_per_jam" value="<?= h($u['tarif_lembur_per_jam'] ?? 0) ?>" class="app-input mt-1">
            </label>
            <label>
                <span class="label">Potongan Telat / Menit</span>
                <input type="number" step="0.01" min="0" name="potongan_terlambat_per_menit" value="<?= h($u['potongan_terlambat_per_menit'] ?? 0) ?>" class="app-input mt-1">
            </label>
            <label>
                <span class="label">Potongan Pulang Cepat / Menit</span>
                <input type="number" step="0.01" min="0" name="potongan_pulang_cepat_per_menit" value="<?= h($u['potongan_pulang_cepat_per_menit'] ?? 0) ?>" class="app-input mt-1">
            </label>
            <label>
                <span class="label">Potongan Alpha / Hari</span>
                <input type="number" step="0.01" min="0" name="potongan_alpha_per_hari" value="<?= h($u['potongan_alpha_per_hari'] ?? 0) ?>" class="app-input mt-1">
            </label>
            <label>
                <span class="label">Jam Kerja Normal / Hari</span>
                <input type="number" min="1" name="jam_kerja_normal_menit" value="<?= h($u['jam_kerja_normal_menit'] ?? 480) ?>" class="app-input mt-1">
                <span class="text-xs text-slate-500">480 = 8 jam.</span>
            </label>
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
            <label class="flex items-center gap-3 bg-slate-950 rounded-xl p-3">
                <input type="checkbox" name="aktif" value="1" <?= ((int)($u['aktif'] ?? 1) === 1) ? 'checked' : '' ?>>
                <span>Aktif dihitung payroll</span>
            </label>
            <button class="btn btn-primary">Simpan Setting</button>
        </div>
    </form>
    <?php endforeach; ?>
</div>

<div class="mt-5 p-4 app-card text-sm text-slate-400">
    <b>Catatan:</b> uang makan dan transport dihitung dari jumlah hari hadir. Potongan telat/pulang cepat dihitung dari menit absensi. Alpha dihitung dari jadwal shift yang tidak punya absensi/izin/cuti/sakit.
</div>

<?php ui_footer(); ?>
