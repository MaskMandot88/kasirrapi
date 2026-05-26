<?php
session_start();
require_once '../config/database.php';
require_once '../includes/ui.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}
if (!ui_is_role(['Owner','Admin'])) {
    die('Akses ditolak.');
}

$tenant_id = (int)$_SESSION['tenant_id'];
$mulai = $_GET['mulai'] ?? date('Y-m-01');
$selesai = $_GET['selesai'] ?? date('Y-m-d');
$mode = $_GET['mode'] ?? 'ringkas';
$export = $_GET['export'] ?? '';

if (strtotime($mulai) === false) $mulai = date('Y-m-01');
if (strtotime($selesai) === false) $selesai = date('Y-m-d');
if ($selesai < $mulai) $selesai = $mulai;
if (!in_array($mode, ['ringkas','detail'], true)) $mode = 'ringkas';

function qval($pdo, $sql, $params, $default = 0) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();
        return $v === false || $v === null ? $default : $v;
    } catch (Throwable $e) {
        return $default;
    }
}
function qall($pdo, $sql, $params) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}
function tglid_lap($v) { return $v ? date('d/m/Y', strtotime($v)) : '-'; }

$dtMulai = $mulai . ' 00:00:00';
$dtSelesai = $selesai . ' 23:59:59';

$omzet = (float)qval($pdo, "SELECT COALESCE(SUM(total),0) FROM transaksi WHERE tenant_id=? AND tanggal BETWEEN ? AND ?", [$tenant_id,$dtMulai,$dtSelesai]);
$trx_count = (int)qval($pdo, "SELECT COUNT(*) FROM transaksi WHERE tenant_id=? AND tanggal BETWEEN ? AND ?", [$tenant_id,$dtMulai,$dtSelesai]);
$hutang_baru = (float)qval($pdo, "SELECT COALESCE(SUM(total),0) FROM transaksi WHERE tenant_id=? AND metode_bayar='Hutang' AND tanggal BETWEEN ? AND ?", [$tenant_id,$dtMulai,$dtSelesai]);
$tunai = (float)qval($pdo, "SELECT COALESCE(SUM(total),0) FROM transaksi WHERE tenant_id=? AND metode_bayar='Tunai' AND tanggal BETWEEN ? AND ?", [$tenant_id,$dtMulai,$dtSelesai]);
$qris = (float)qval($pdo, "SELECT COALESCE(SUM(total),0) FROM transaksi WHERE tenant_id=? AND metode_bayar='QRIS' AND tanggal BETWEEN ? AND ?", [$tenant_id,$dtMulai,$dtSelesai]);
$transfer = (float)qval($pdo, "SELECT COALESCE(SUM(total),0) FROM transaksi WHERE tenant_id=? AND metode_bayar='Transfer' AND tanggal BETWEEN ? AND ?", [$tenant_id,$dtMulai,$dtSelesai]);
$pembayaran_piutang = (float)qval($pdo, "SELECT COALESCE(SUM(nominal_bayar),0) FROM piutang_pembayaran WHERE tenant_id=? AND tanggal_bayar BETWEEN ? AND ?", [$tenant_id,$dtMulai,$dtSelesai]);
$pembelian = (float)qval($pdo, "SELECT COALESCE(SUM(total_pembelian),0) FROM pembelian WHERE tenant_id=? AND tanggal BETWEEN ? AND ?", [$tenant_id,$mulai,$selesai]);
$sisa_piutang = (float)qval($pdo, "SELECT COALESCE(SUM(sisa_piutang),0) FROM piutang WHERE tenant_id=? AND status='Belum Lunas'", [$tenant_id]);
$stok_menipis = (int)qval($pdo, "SELECT COUNT(*) FROM barang WHERE tenant_id=? AND stok_gudang <= 5", [$tenant_id]);

$kas_masuk = $tunai + $qris + $transfer + $pembayaran_piutang;
$aruskas = $kas_masuk - $pembelian;

$rekap_metode = qall($pdo, "SELECT metode_bayar, COUNT(*) jumlah, COALESCE(SUM(total),0) total
    FROM transaksi WHERE tenant_id=? AND tanggal BETWEEN ? AND ?
    GROUP BY metode_bayar ORDER BY total DESC", [$tenant_id,$dtMulai,$dtSelesai]);

$rekap_harian = qall($pdo, "SELECT DATE(tanggal) tanggal, COUNT(*) jumlah, COALESCE(SUM(total),0) total
    FROM transaksi WHERE tenant_id=? AND tanggal BETWEEN ? AND ?
    GROUP BY DATE(tanggal) ORDER BY tanggal ASC", [$tenant_id,$dtMulai,$dtSelesai]);

$produk_terlaris = qall($pdo, "SELECT b.nama_barang, COALESCE(SUM(td.qty),0) qty, COALESCE(SUM(td.subtotal),0) total
    FROM transaksi_detail td
    JOIN transaksi t ON t.id = td.transaksi_id
    JOIN barang b ON b.id = td.barang_id
    WHERE t.tenant_id=? AND t.tanggal BETWEEN ? AND ?
    GROUP BY b.id, b.nama_barang ORDER BY qty DESC LIMIT 20", [$tenant_id,$dtMulai,$dtSelesai]);

$detail_transaksi = qall($pdo, "SELECT t.nomor_invoice, t.tanggal, t.metode_bayar, t.total, u.nama AS kasir
    FROM transaksi t LEFT JOIN users u ON u.id = t.kasir_id
    WHERE t.tenant_id=? AND t.tanggal BETWEEN ? AND ?
    ORDER BY t.tanggal DESC LIMIT 200", [$tenant_id,$dtMulai,$dtSelesai]);

if ($export === 'xls') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan_' . $mulai . '_' . $selesai . '.xls"');
    echo "<h3>Laporan $mulai s/d $selesai</h3>";
    echo "<table border='1'>";
    echo "<tr><th>Omzet</th><td>".h($omzet)."</td></tr>";
    echo "<tr><th>Transaksi</th><td>".h($trx_count)."</td></tr>";
    echo "<tr><th>Kas Masuk</th><td>".h($kas_masuk)."</td></tr>";
    echo "<tr><th>Pembelian</th><td>".h($pembelian)."</td></tr>";
    echo "<tr><th>Arus Kas</th><td>".h($aruskas)."</td></tr>";
    echo "<tr><th>Sisa Piutang Aktif</th><td>".h($sisa_piutang)."</td></tr>";
    echo "</table><br>";
    echo "<h4>Detail Transaksi</h4><table border='1'><tr><th>Invoice</th><th>Tanggal</th><th>Kasir</th><th>Metode</th><th>Total</th></tr>";
    foreach ($detail_transaksi as $d) {
        echo "<tr><td>".h($d['nomor_invoice'])."</td><td>".h($d['tanggal'])."</td><td>".h($d['kasir'])."</td><td>".h($d['metode_bayar'])."</td><td>".h($d['total'])."</td></tr>";
    }
    echo "</table>";
    exit;
}

ui_head('Laporan');
ui_nav($pdo, 'Laporan');
?>

<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-emerald-400">Laporan Toko</h2>
        <p class="text-slate-400">Mode ringkas untuk cepat membaca kondisi toko, mode detail untuk audit.</p>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
        <a href="?mulai=<?= h($mulai) ?>&selesai=<?= h($selesai) ?>&mode=<?= $mode === 'ringkas' ? 'detail' : 'ringkas' ?>" class="btn btn-secondary"><?= $mode === 'ringkas' ? 'Mode Detail' : 'Mode Ringkas' ?></a>
        <a href="?mulai=<?= h($mulai) ?>&selesai=<?= h($selesai) ?>&mode=detail&export=xls" class="btn btn-primary">Excel</a>
        <button onclick="window.print()" class="btn btn-secondary col-span-2 md:col-span-1">PDF / Print</button>
    </div>
</div>

<form method="GET" class="app-card p-3 mb-5 grid grid-cols-1 md:grid-cols-[1fr_1fr_auto] gap-2 no-print">
    <input type="hidden" name="mode" value="<?= h($mode) ?>">
    <label><span class="label">Mulai</span><input type="date" name="mulai" value="<?= h($mulai) ?>" class="app-input mt-1"></label>
    <label><span class="label">Selesai</span><input type="date" name="selesai" value="<?= h($selesai) ?>" class="app-input mt-1"></label>
    <button class="btn btn-secondary self-end">Terapkan</button>
</form>

<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
    <div class="stat-card"><div class="label">Omzet</div><div class="text-xl md:text-2xl font-bold text-emerald-300"><?= rupiah($omzet) ?></div></div>
    <div class="stat-card"><div class="label">Transaksi</div><div class="text-xl md:text-2xl font-bold"><?= $trx_count ?></div></div>
    <div class="stat-card"><div class="label">Kas Masuk</div><div class="text-xl md:text-2xl font-bold text-cyan-300"><?= rupiah($kas_masuk) ?></div></div>
    <div class="stat-card"><div class="label">Arus Kas</div><div class="text-xl md:text-2xl font-bold <?= $aruskas < 0 ? 'text-red-300' : 'text-emerald-300' ?>"><?= rupiah($aruskas) ?></div></div>
    <div class="stat-card"><div class="label">Tunai</div><div class="text-lg font-bold"><?= rupiah($tunai) ?></div></div>
    <div class="stat-card"><div class="label">QRIS</div><div class="text-lg font-bold"><?= rupiah($qris) ?></div></div>
    <div class="stat-card"><div class="label">Transfer</div><div class="text-lg font-bold"><?= rupiah($transfer) ?></div></div>
    <div class="stat-card"><div class="label">Hutang Baru</div><div class="text-lg font-bold text-amber-300"><?= rupiah($hutang_baru) ?></div></div>
    <div class="stat-card"><div class="label">Pembayaran Piutang</div><div class="text-lg font-bold text-emerald-300"><?= rupiah($pembayaran_piutang) ?></div></div>
    <div class="stat-card"><div class="label">Pembelian</div><div class="text-lg font-bold text-red-300"><?= rupiah($pembelian) ?></div></div>
    <div class="stat-card"><div class="label">Sisa Piutang Aktif</div><div class="text-lg font-bold text-amber-300"><?= rupiah($sisa_piutang) ?></div></div>
    <div class="stat-card"><div class="label">Stok Menipis</div><div class="text-lg font-bold <?= $stok_menipis > 0 ? 'text-red-300' : '' ?>"><?= $stok_menipis ?></div></div>
</div>

<?php if ($mode === 'detail'): ?>
<div class="grid lg:grid-cols-2 gap-4 mb-6">
    <div class="app-card p-4">
        <h3 class="text-lg font-bold mb-3">Rekap Metode Pembayaran</h3>
        <div class="mobile-card-list">
            <?php foreach ($rekap_metode as $r): ?>
            <div class="bg-slate-950 rounded-xl p-3 flex justify-between gap-3">
                <div><b><?= h($r['metode_bayar']) ?></b><div class="text-sm text-slate-500"><?= (int)$r['jumlah'] ?> transaksi</div></div>
                <div class="font-bold"><?= rupiah($r['total']) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (!$rekap_metode): ?><div class="text-slate-500">Tidak ada data.</div><?php endif; ?>
        </div>
    </div>

    <div class="app-card p-4">
        <h3 class="text-lg font-bold mb-3">Rekap Harian</h3>
        <div class="mobile-card-list">
            <?php foreach ($rekap_harian as $r): ?>
            <div class="bg-slate-950 rounded-xl p-3 flex justify-between gap-3">
                <div><b><?= tglid_lap($r['tanggal']) ?></b><div class="text-sm text-slate-500"><?= (int)$r['jumlah'] ?> transaksi</div></div>
                <div class="font-bold"><?= rupiah($r['total']) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (!$rekap_harian): ?><div class="text-slate-500">Tidak ada data.</div><?php endif; ?>
        </div>
    </div>
</div>

<div class="app-card p-4 mb-6">
    <h3 class="text-lg font-bold mb-3">Produk Terlaris</h3>
    <div class="mobile-card-list">
        <?php foreach ($produk_terlaris as $r): ?>
        <div class="bg-slate-950 rounded-xl p-3 flex justify-between gap-3">
            <div><b><?= h($r['nama_barang']) ?></b><div class="text-sm text-slate-500">Qty: <?= (int)$r['qty'] ?></div></div>
            <div class="font-bold"><?= rupiah($r['total']) ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (!$produk_terlaris): ?><div class="text-slate-500">Tidak ada data.</div><?php endif; ?>
    </div>
</div>

<div class="app-card p-4">
    <h3 class="text-lg font-bold mb-3">Detail Transaksi</h3>
    <div class="mobile-card-list">
        <?php foreach ($detail_transaksi as $d): ?>
        <div class="bg-slate-950 rounded-xl p-3">
            <div class="flex justify-between gap-3">
                <div>
                    <b><?= h($d['nomor_invoice']) ?></b>
                    <div class="text-sm text-slate-500"><?= h(date('d/m/Y H:i', strtotime($d['tanggal']))) ?> · <?= h($d['kasir'] ?: '-') ?></div>
                </div>
                <div class="text-right">
                    <div class="font-bold"><?= rupiah($d['total']) ?></div>
                    <div class="text-sm text-slate-500"><?= h($d['metode_bayar']) ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$detail_transaksi): ?><div class="text-slate-500">Tidak ada data.</div><?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php ui_footer(); ?>
