<?php
session_start();
require_once '../config/database.php';
require_once '../includes/ui.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}
if (!ui_is_role(['Owner','Admin','Kasir'])) {
    die('Akses ditolak.');
}

$tenant_id = (int)$_SESSION['tenant_id'];
$status = $_GET['status'] ?? 'Belum Lunas';
$q = trim((string)($_GET['q'] ?? ''));

$status_valid = ['Semua', 'Belum Lunas', 'Lunas', 'Batal'];
if (!in_array($status, $status_valid, true)) $status = 'Belum Lunas';

$where = ['p.tenant_id = ?'];
$params = [$tenant_id];

if ($status !== 'Semua') {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(pl.nama_pelanggan LIKE ? OR pl.no_wa LIKE ? OR p.nomor_invoice LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}

$where_sql = implode(' AND ', $where);

$stmt_summary = $pdo->prepare("SELECT
        COUNT(*) AS jumlah_data,
        COALESCE(SUM(jumlah_piutang), 0) AS total_nilai,
        COALESCE(SUM(total_dibayar), 0) AS total_dibayar,
        COALESCE(SUM(sisa_piutang), 0) AS total_sisa
    FROM piutang p
    INNER JOIN pelanggan pl ON p.pelanggan_id = pl.id
    WHERE $where_sql");
$stmt_summary->execute($params);
$summary = $stmt_summary->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT p.*, pl.nama_pelanggan, pl.no_wa, u.nama AS nama_kasir
    FROM piutang p
    INNER JOIN pelanggan pl ON p.pelanggan_id = pl.id
    INNER JOIN transaksi t ON p.transaksi_id = t.id
    LEFT JOIN users u ON t.kasir_id = u.id
    WHERE $where_sql
    ORDER BY p.status = 'Belum Lunas' DESC, p.jatuh_tempo IS NULL ASC, p.jatuh_tempo ASC, p.id DESC
    LIMIT 300");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function tanggal_piutang($v) { return $v ? date('d/m/Y', strtotime($v)) : '-'; }

ui_head('Data Piutang');
ui_nav($pdo, 'Data Piutang');
?>

<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-emerald-400">Data Piutang</h2>
        <p class="text-slate-400">Daftar transaksi hutang, sisa tagihan, dan pembayaran pelanggan.</p>
    </div>
    <a href="../kasir/index.php" class="btn btn-primary">Kasir</a>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
    <div class="stat-card"><div class="label">Jumlah Data</div><div class="text-2xl font-bold"><?= (int)$summary['jumlah_data'] ?></div></div>
    <div class="stat-card"><div class="label">Total Piutang</div><div class="text-xl font-bold text-amber-300"><?= rupiah($summary['total_nilai']) ?></div></div>
    <div class="stat-card"><div class="label">Sudah Dibayar</div><div class="text-xl font-bold text-emerald-300"><?= rupiah($summary['total_dibayar']) ?></div></div>
    <div class="stat-card"><div class="label">Sisa Piutang</div><div class="text-xl font-bold text-red-300"><?= rupiah($summary['total_sisa']) ?></div></div>
</div>

<form method="GET" class="app-card p-3 mb-5 grid grid-cols-1 md:grid-cols-[1fr_180px_auto] gap-2">
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Cari pelanggan, WA, atau invoice..." class="app-input">
    <select name="status" class="app-select">
        <?php foreach ($status_valid as $s): ?>
            <option value="<?= h($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= h($s) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-secondary">Filter</button>
</form>

<div class="mobile-card-list">
<?php foreach ($rows as $r): ?>
    <div class="mobile-card">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
            <div>
                <div class="flex flex-wrap gap-2 mb-2">
                    <span class="px-2 py-1 rounded-lg text-xs <?= $r['status'] === 'Lunas' ? 'bg-emerald-700' : ($r['status'] === 'Batal' ? 'bg-slate-700' : 'bg-amber-700') ?>"><?= h($r['status']) ?></span>
                    <span class="px-2 py-1 rounded-lg text-xs bg-slate-800"><?= h($r['nomor_invoice']) ?></span>
                </div>
                <h3 class="text-xl font-bold"><?= h($r['nama_pelanggan']) ?></h3>
                <p class="text-slate-400 text-sm"><?= h($r['no_wa'] ?: '-') ?> · Kasir: <?= h($r['nama_kasir'] ?: '-') ?></p>
                <p class="text-slate-500 text-sm mt-1">Tanggal: <?= tanggal_piutang($r['tanggal']) ?> · Jatuh tempo: <?= tanggal_piutang($r['jatuh_tempo']) ?></p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-2 md:min-w-[460px]">
                <div class="bg-slate-950 rounded-xl p-3"><div class="label">Piutang</div><div class="value"><?= rupiah($r['jumlah_piutang']) ?></div></div>
                <div class="bg-slate-950 rounded-xl p-3"><div class="label">Dibayar</div><div class="value text-emerald-300"><?= rupiah($r['total_dibayar']) ?></div></div>
                <div class="bg-slate-950 rounded-xl p-3 col-span-2 md:col-span-1"><div class="label">Sisa</div><div class="value text-red-300"><?= rupiah($r['sisa_piutang']) ?></div></div>
                <?php if ((float)$r['sisa_piutang'] > 0 && $r['status'] !== 'Batal'): ?>
                    <a href="bayar.php?id=<?= (int)$r['id'] ?>" class="col-span-2 md:col-span-3 btn btn-primary">Bayar Piutang</a>
                <?php else: ?>
                    <span class="col-span-2 md:col-span-3 btn btn-secondary">Tidak ada tagihan aktif</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if (!$rows): ?>
    <div class="mobile-card text-center text-slate-500">Data piutang tidak ditemukan.</div>
<?php endif; ?>
</div>

<?php ui_footer(); ?>
