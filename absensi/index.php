<?php
session_start();
require_once '../config/database.php';
require_once '../includes/ui.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id_login = (int)$_SESSION['user_id'];
$role_login = $_SESSION['role'];
$hr = ui_is_role(['Owner','Admin','HRD']);

function safe_count_abs($pdo, $sql, $params) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

$menunggu_izin = safe_count_abs($pdo, "SELECT COUNT(*) FROM pengajuan_absensi WHERE tenant_id = ? AND status = 'Menunggu'", [$tenant_id]);
$menunggu_tukar = safe_count_abs($pdo, "SELECT COUNT(*) FROM shift_tukar WHERE tenant_id = ? AND status = 'Menunggu'", [$tenant_id]);
$absen_hari_ini = safe_count_abs($pdo, "SELECT COUNT(*) FROM absensi WHERE tenant_id = ? AND user_id = ? AND tanggal = CURDATE()", [$tenant_id, $user_id_login]);

ui_head('Absensi');
ui_nav($pdo, 'Absensi');
?>

<div class="mb-6">
    <h2 class="text-2xl font-bold text-emerald-400">Absensi Karyawan</h2>
    <p class="text-slate-400">Absen wajah, izin/cuti/sakit, manual/koreksi, tukar shift, dan approval.</p>
</div>

<?php if (!empty($_SESSION['flash']['success'])): ?>
    <div class="mb-4 p-3 bg-emerald-900/50 border border-emerald-500 rounded-xl"><?= h($_SESSION['flash']['success']); unset($_SESSION['flash']['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash']['error'])): ?>
    <div class="mb-4 p-3 bg-red-900/50 border border-red-500 rounded-xl"><?= h($_SESSION['flash']['error']); unset($_SESSION['flash']['error']); ?></div>
<?php endif; ?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <div class="stat-card">
        <div class="label">Absensi Saya Hari Ini</div>
        <div class="text-2xl font-extrabold"><?= $absen_hari_ini ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Izin Menunggu</div>
        <div class="text-2xl font-extrabold"><?= $menunggu_izin ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Tukar Shift Menunggu</div>
        <div class="text-2xl font-extrabold"><?= $menunggu_tukar ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Total Approval</div>
        <div class="text-2xl font-extrabold"><?= $menunggu_izin + $menunggu_tukar ?></div>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
    <a href="absen_wajah.php" class="mobile-card hover:border-emerald-500">
        <div class="text-2xl mb-2">📷</div>
        <div class="text-xl font-bold">Absen Wajah</div>
        <p class="text-slate-400 text-sm">Masuk/pulang otomatis dengan deteksi wajah.</p>
    </a>
    <a href="../absensi/daftar_wajah.php" class="mobile-card hover:border-orange-500 transition">
        <div class="text-2xl mb-2">🪪</div>
        <div class="text-xl font-bold text-white">Rekam Wajah</div>
        <p class="text-slate-400 text-sm mt-1">
            Ambil atau perbarui referensi wajah karyawan untuk absensi.
        </p>
    </a>
    <a href="izin.php" class="mobile-card hover:border-emerald-500">
        <div class="text-2xl mb-2">📝</div>
        <div class="text-xl font-bold">Ajukan Izin / Manual</div>
        <p class="text-slate-400 text-sm">Izin, cuti, sakit, lupa absen, dan koreksi jam.</p>
    </a>

    <a href="tukar_shift.php" class="mobile-card hover:border-emerald-500">
        <div class="text-2xl mb-2">🔁</div>
        <div class="text-xl font-bold">Tukar Shift</div>
        <p class="text-slate-400 text-sm">Ajukan pengganti shift sementara.</p>
    </a>

    <a href="pengajuan_saya.php" class="mobile-card hover:border-emerald-500">
        <div class="text-2xl mb-2">📌</div>
        <div class="text-xl font-bold">Pengajuan Saya</div>
        <p class="text-slate-400 text-sm">Lihat status pengajuan absensi dan tukar shift.</p>
    </a>

    <a href="rekap.php" class="mobile-card hover:border-emerald-500">
        <div class="text-2xl mb-2">📊</div>
        <div class="text-xl font-bold">Rekap Absensi</div>
        <p class="text-slate-400 text-sm">Riwayat hadir, telat, pulang cepat, dan foto bukti.</p>
    </a>

    <?php if ($hr): ?>
    <a href="approval.php" class="mobile-card border-amber-700 bg-amber-950/30 hover:border-amber-400">
        <div class="text-2xl mb-2">✅</div>
        <div class="text-xl font-bold">Approval</div>
        <p class="text-slate-400 text-sm">Setujui/tolak izin, manual absensi, dan tukar shift.</p>
    </a>

    <a href="shift.php" class="mobile-card hover:border-emerald-500">
        <div class="text-2xl mb-2">⏱️</div>
        <div class="text-xl font-bold">Master Shift</div>
        <p class="text-slate-400 text-sm">Atur shift pagi, sore, malam, dan toleransi.</p>
    </a>

    <a href="jadwal.php" class="mobile-card hover:border-emerald-500">
        <div class="text-2xl mb-2">🗓️</div>
        <div class="text-xl font-bold">Jadwal Shift</div>
        <p class="text-slate-400 text-sm">Atur jadwal kerja karyawan.</p>
    </a>

    <a href="manual.php" class="mobile-card hover:border-emerald-500">
        <div class="text-2xl mb-2">✍️</div>
        <div class="text-xl font-bold">Input Manual Admin</div>
        <p class="text-slate-400 text-sm">Fallback langsung oleh Owner/Admin/HRD.</p>
    </a>
    <?php endif; ?>
</div>

<?php ui_footer(); ?>
