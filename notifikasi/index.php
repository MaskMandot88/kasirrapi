<?php
session_start();
require_once '../config/database.php';
require_once '../includes/ui.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    $stmt = $pdo->prepare("SELECT n.id
        FROM notifikasi n
        LEFT JOIN notifikasi_read nr ON nr.notifikasi_id = n.id AND nr.user_id = ? AND nr.tenant_id = n.tenant_id
        WHERE n.tenant_id = ?
          AND nr.id IS NULL
          AND (n.target_user_id = ? OR n.target_user_id IS NULL)
          AND (n.target_role = 'Semua' OR n.target_role = ? OR n.target_role IS NULL)");
    $stmt->execute([$user_id, $tenant_id, $user_id, $role]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $ins = $pdo->prepare("INSERT IGNORE INTO notifikasi_read (tenant_id, notifikasi_id, user_id, read_at) VALUES (?, ?, ?, NOW())");
    foreach ($ids as $id) {
        $ins->execute([$tenant_id, $id, $user_id]);
    }

    $_SESSION['flash']['success'] = 'Semua notifikasi ditandai sudah dibaca.';
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT n.*, u.nama AS pengirim,
                              CASE WHEN nr.id IS NULL THEN 0 ELSE 1 END AS sudah_dibaca
    FROM notifikasi n
    LEFT JOIN users u ON u.id = n.pengirim_id
    LEFT JOIN notifikasi_read nr ON nr.notifikasi_id = n.id AND nr.user_id = ? AND nr.tenant_id = n.tenant_id
    WHERE n.tenant_id = ?
      AND (n.target_user_id = ? OR n.target_user_id IS NULL)
      AND (n.target_role = 'Semua' OR n.target_role = ? OR n.target_role IS NULL)
    ORDER BY n.created_at DESC
    LIMIT 100");
$stmt->execute([$user_id, $tenant_id, $user_id, $role]);
$items = $stmt->fetchAll();

ui_head('Notifikasi');
ui_nav($pdo, 'Notifikasi');
?>

<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5">
    <div>
        <h2 class="text-2xl font-bold text-emerald-400">Lonceng Pemberitahuan</h2>
        <p class="text-slate-400">Pengumuman, approval, absensi, gaji, piutang, stok, dan info sistem.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        <?php if (ui_is_role(['Owner'])): ?>
            <a href="buat.php" class="btn btn-primary">Buat Pengumuman</a>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="action" value="mark_all_read">
            <button class="btn btn-secondary">Tandai Semua Dibaca</button>
        </form>
    </div>
</div>

<?php if (!empty($_SESSION['flash']['success'])): ?>
    <div class="mb-4 p-3 bg-emerald-900/50 border border-emerald-500 rounded-xl"><?= h($_SESSION['flash']['success']); unset($_SESSION['flash']['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash']['error'])): ?>
    <div class="mb-4 p-3 bg-red-900/50 border border-red-500 rounded-xl"><?= h($_SESSION['flash']['error']); unset($_SESSION['flash']['error']); ?></div>
<?php endif; ?>

<div class="mobile-card-list">
<?php foreach ($items as $n): ?>
    <?php
        $border = $n['sudah_dibaca'] ? 'border-slate-800' : 'border-emerald-500';
        $badge = $n['prioritas'] === 'Darurat' ? 'bg-red-600' : ($n['prioritas'] === 'Penting' ? 'bg-amber-600' : 'bg-slate-700');
    ?>
    <div class="mobile-card <?= $border ?>">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-2">
                    <span class="px-2 py-1 rounded-lg text-xs <?= $badge ?> text-white"><?= h($n['prioritas']) ?></span>
                    <span class="px-2 py-1 rounded-lg text-xs bg-slate-800 text-slate-300"><?= h($n['tipe']) ?></span>
                    <?php if (!$n['sudah_dibaca']): ?>
                        <span class="px-2 py-1 rounded-lg text-xs bg-emerald-700 text-white">Baru</span>
                    <?php endif; ?>
                </div>
                <h3 class="text-lg font-bold text-white"><?= h($n['judul']) ?></h3>
                <p class="text-slate-300 mt-2 whitespace-pre-wrap"><?= h($n['pesan']) ?></p>
                <p class="text-xs text-slate-500 mt-3">
                    <?= h(date('d/m/Y H:i', strtotime($n['created_at']))) ?>
                    · dari <?= h($n['pengirim'] ?: 'Sistem') ?>
                </p>
            </div>
            <div class="flex flex-col gap-2 md:min-w-[160px]">
                <?php if ($n['link']): ?>
                    <a href="<?= h($n['link']) ?>" class="btn btn-primary">Buka</a>
                <?php endif; ?>
                <?php if (!$n['sudah_dibaca']): ?>
                    <a href="baca.php?id=<?= (int)$n['id'] ?>" class="btn btn-secondary">Tandai Dibaca</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if (!$items): ?>
    <div class="mobile-card text-center text-slate-500">Belum ada notifikasi.</div>
<?php endif; ?>
</div>

<?php ui_footer(); ?>
