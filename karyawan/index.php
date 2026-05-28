<?php
// karyawan/index.php
session_start();

require_once '../config/database.php';
require_once '../includes/ui.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'Owner') {
    die("Akses Ditolak: Hanya Pemilik Toko (Owner) yang dapat mengelola karyawan.");
}

$tenant_id = (int) $_SESSION['tenant_id'];
$error = '';
$subscription = kasirrapi_tenant_subscription($pdo, $tenant_id);
$allowed_roles_for_plan = ['Kasir'];
if (in_array($subscription['plan'], ['Plus', 'Pro'], true)) {
    $allowed_roles_for_plan = ['Admin', 'Gudang', 'Kasir'];
}
if ($subscription['plan'] === 'Pro' || !empty($subscription['addon_hrd_enabled'])) {
    $allowed_roles_for_plan[] = 'HRD';
}

function role_badge_class($role) {
    switch ($role) {
        case 'Owner':
            return 'bg-amber-900/70 text-amber-200 border border-amber-700';
        case 'Admin':
            return 'bg-blue-900/70 text-blue-200 border border-blue-700';
        case 'Kasir':
            return 'bg-emerald-900/70 text-emerald-200 border border-emerald-700';
        case 'Gudang':
            return 'bg-purple-900/70 text-purple-200 border border-purple-700';
        case 'HRD':
            return 'bg-pink-900/70 text-pink-200 border border-pink-700';
        default:
            return 'bg-slate-800 text-slate-200 border border-slate-700';
    }
}

// PROSES TAMBAH KARYAWAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_karyawan'])) {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $password_raw = $_POST['password'] ?? '';

    $allowed_roles = $allowed_roles_for_plan;

    if ($nama === '' || $email === '' || $role === '' || $password_raw === '') {
        $_SESSION['flash']['error'] = 'Semua field wajib diisi.';
        header('Location: index.php');
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash']['error'] = 'Format email tidak valid.';
        header('Location: index.php');
        exit;
    }

    if (!in_array($role, $allowed_roles, true)) {
        $_SESSION['flash']['error'] = 'Role karyawan tidak valid.';
        header('Location: index.php');
        exit;
    }

    if (strlen($password_raw) < 6) {
        $_SESSION['flash']['error'] = 'Password minimal 6 karakter.';
        header('Location: index.php');
        exit;
    }

    $stmtLimit = $pdo->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ?");
    $stmtLimit->execute([$tenant_id]);
    $totalUsers = (int)$stmtLimit->fetchColumn();
    if (kasirrapi_limit_reached($subscription['max_users'] ?? 1, $totalUsers)) {
        $_SESSION['flash']['error'] = 'Batas user paket ' . ($subscription['plan'] ?? 'Gratis') . ' sudah tercapai. Silakan upgrade paket.';
        header('Location: index.php');
        exit;
    }

    $password_hashed = password_hash($password_raw, PASSWORD_BCRYPT);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (tenant_id, nama, email, password, role)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tenant_id, $nama, $email, $password_hashed, $role]);

        $_SESSION['flash']['success'] = 'Akun staf berhasil didaftarkan.';
        header('Location: index.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash']['error'] = 'Gagal menambah karyawan. Email mungkin sudah terdaftar.';
        header('Location: index.php');
        exit;
    }
}

// PROSES HAPUS KARYAWAN
if (isset($_GET['hapus'])) {
    $user_id_hapus = (int) $_GET['hapus'];

    if ($user_id_hapus > 0) {
        $stmt = $pdo->prepare("
            DELETE FROM users
            WHERE id = ?
              AND tenant_id = ?
              AND role != 'Owner'
        ");
        $stmt->execute([$user_id_hapus, $tenant_id]);

        $_SESSION['flash']['success'] = 'Akses karyawan berhasil dicabut.';
    }

    header('Location: index.php');
    exit;
}

// AMBIL DAFTAR KARYAWAN
$stmt = $pdo->prepare("
    SELECT id, nama, email, role, created_at
    FROM users
    WHERE tenant_id = ?
    ORDER BY FIELD(role, 'Owner', 'Admin', 'HRD', 'Gudang', 'Kasir'), nama ASC
");
$stmt->execute([$tenant_id]);
$daftar_karyawan = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_karyawan = count($daftar_karyawan);
$total_owner = 0;
$total_admin = 0;
$total_hrd = 0;
$total_gudang = 0;
$total_kasir = 0;

foreach ($daftar_karyawan as $k) {
    if ($k['role'] === 'Owner') $total_owner++;
    if ($k['role'] === 'Admin') $total_admin++;
    if ($k['role'] === 'HRD') $total_hrd++;
    if ($k['role'] === 'Gudang') $total_gudang++;
    if ($k['role'] === 'Kasir') $total_kasir++;
}

ui_head('Kelola Karyawan');
ui_nav($pdo, 'Kelola Karyawan');
?>

<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-orange-400">Manajemen Karyawan</h2>
        <p class="text-slate-400">
            Tambah, kelola, dan cabut akses staf toko Anda.
        </p>
    </div>

    <a href="../dashboard/index.php" class="btn btn-secondary">
        ← Dashboard
    </a>
</div>

<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
    <div class="stat-card">
        <div class="label">Total User</div>
        <div class="text-2xl font-extrabold"><?= (int) $total_karyawan ?></div>
    </div>

    <div class="stat-card">
        <div class="label">Owner</div>
        <div class="text-2xl font-extrabold text-amber-300"><?= (int) $total_owner ?></div>
    </div>

    <div class="stat-card">
        <div class="label">Admin</div>
        <div class="text-2xl font-extrabold text-blue-300"><?= (int) $total_admin ?></div>
    </div>

    <div class="stat-card">
        <div class="label">HRD / Gudang</div>
        <div class="text-2xl font-extrabold text-purple-300"><?= (int) ($total_hrd + $total_gudang) ?></div>
    </div>

    <div class="stat-card">
        <div class="label">Kasir</div>
        <div class="text-2xl font-extrabold text-emerald-300"><?= (int) $total_kasir ?></div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-[420px_1fr] gap-5">

    <div class="app-card p-4 md:p-5 h-fit">
        <div class="mb-5">
            <h3 class="text-xl font-bold text-orange-400">Tambah Staf Baru</h3>
            <p class="text-sm text-slate-400 mt-1">
                Akun ini akan otomatis masuk ke tenant toko Anda.
            </p>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="tambah_karyawan" value="1">

            <label class="block">
                <span class="label">Nama Lengkap</span>
                <input type="text"
                       name="nama"
                       class="app-input mt-1"
                       required
                       placeholder="Nama karyawan...">
            </label>

            <label class="block">
                <span class="label">Email Login</span>
                <input type="email"
                       name="email"
                       class="app-input mt-1"
                       required
                       placeholder="staf@tokosaya.com">
            </label>

            <label class="block">
                <span class="label">Password Awal</span>
                <input type="password"
                       name="password"
                       class="app-input mt-1"
                       required
                       minlength="6"
                       placeholder="Minimal 6 karakter">
            </label>

            <label class="block">
                <span class="label">Role / Hak Akses</span>
                <select name="role" class="app-select mt-1" required>
                    <?php if (in_array('Kasir', $allowed_roles_for_plan, true)): ?><option value="Kasir">Kasir - Penjualan</option><?php endif; ?>
                    <?php if (in_array('Admin', $allowed_roles_for_plan, true)): ?><option value="Admin">Admin - Operasional</option><?php endif; ?>
                    <?php if (in_array('Gudang', $allowed_roles_for_plan, true)): ?><option value="Gudang">Gudang - Stok Barang</option><?php endif; ?>
                    <?php if (in_array('HRD', $allowed_roles_for_plan, true)): ?><option value="HRD">HRD - Absensi & Gaji</option><?php endif; ?>
                </select>
            </label>

            <button type="submit" class="btn btn-primary w-full">
                Daftarkan Staf
            </button>
        </form>
    </div>

    <div class="app-card p-4 md:p-5">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5">
            <div>
                <h3 class="text-xl font-bold">Struktur Organisasi Toko</h3>
                <p class="text-sm text-slate-400">
                    Daftar user yang memiliki akses ke toko ini.
                </p>
            </div>
        </div>

        <div class="mobile-card-list">
            <?php foreach ($daftar_karyawan as $k): ?>
                <div class="mobile-card">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="px-3 py-1 rounded-full text-xs font-bold <?= role_badge_class($k['role']) ?>">
                                    <?= h($k['role']) ?>
                                </span>

                                <?php if ($k['role'] === 'Owner'): ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-800 text-slate-300 border border-slate-700">
                                        Akun Utama
                                    </span>
                                <?php endif; ?>
                            </div>

                            <h4 class="text-lg font-extrabold text-white">
                                <?= h($k['nama']) ?>
                            </h4>

                            <p class="text-sm text-slate-400 break-all mt-1">
                                <?= h($k['email']) ?>
                            </p>

                            <?php if (!empty($k['created_at'])): ?>
                                <p class="text-xs text-slate-500 mt-2">
                                    Dibuat: <?= h(date('d/m/Y H:i', strtotime($k['created_at']))) ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="md:min-w-[180px]">
                            <?php if ($k['role'] !== 'Owner'): ?>
                                <a href="index.php?hapus=<?= (int) $k['id'] ?>"
                                   class="btn btn-danger w-full"
                                   onclick="return confirm('Cabut akses karyawan ini?')">
                                    Cabut Akses
                                </a>
                            <?php else: ?>
                                <div class="btn btn-secondary w-full opacity-70 cursor-not-allowed">
                                    Tidak Bisa Dihapus
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (!$daftar_karyawan): ?>
                <div class="mobile-card text-center text-slate-500">
                    Belum ada data karyawan.
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.AppUI) {
        document.querySelectorAll('a.btn-danger').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();

                AppUI.confirm('Cabut akses karyawan ini?', function () {
                    window.location.href = link.href;
                }, 'Konfirmasi Hapus');
            });

            link.removeAttribute('onclick');
        });
    }
});
</script>

<?php ui_footer(); ?>
