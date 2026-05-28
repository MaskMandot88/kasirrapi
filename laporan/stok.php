<?php
// laporan/stok.php
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

// Query ambil data stok
$stmt = $pdo->prepare("SELECT *, (stok_gudang * harga_beli) as nilai_aset FROM barang WHERE tenant_id = ? ORDER BY stok_gudang ASC");
$stmt->execute([$tenant_id]);
$stok_list = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Stok Gudang</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-200 p-8">
    <div class="max-w-5xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-amber-400">Laporan Inventaris Stok</h2>
            <a href="index.php" class="bg-slate-700 px-4 py-2 rounded-lg">Kembali ke Keuangan</a>
        </div>

        <div class="bg-slate-800 rounded-xl shadow-xl overflow-hidden border border-slate-700">
            <table class="w-full text-sm">
                <thead class="bg-slate-900 text-slate-400">
                    <tr>
                        <th class="p-4 text-left">Nama Barang</th>
                        <th class="p-4 text-center">Stok</th>
                        <th class="p-4 text-right">Harga Beli</th>
                        <th class="p-4 text-right">Nilai Aset</th>
                        <th class="p-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_aset = 0;
                    foreach($stok_list as $s):
                        $total_aset += $s['nilai_aset'];
                    ?>
                    <tr class="border-b border-slate-700/50">
                        <td class="p-4 font-bold"><?= h($s['nama_barang']) ?></td>
                        <td class="p-4 text-center">
                            <div class="font-bold">
                                <?= h(ui_format_stok_bertingkat($s['stok_gudang'] ?? 0, $s['satuan'] ?? '', $s['isi_per_kemasan'] ?? 1, $s['satuan_ecer'] ?? '')) ?>
                            </div>
                            <?php if ((int)($s['isi_per_kemasan'] ?? 1) > 1): ?>
                                <div class="text-xs text-slate-500 mt-1">
                                    Total kecil: <?= h(ui_format_stok_terkecil($s['stok_gudang'] ?? 0, $s['satuan'] ?? '', $s['isi_per_kemasan'] ?? 1, $s['satuan_ecer'] ?? '')) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 text-right">Rp <?= number_format($s['harga_beli'], 0, ',', '.') ?></td>
                        <td class="p-4 text-right">Rp <?= number_format($s['nilai_aset'], 0, ',', '.') ?></td>
                        <td class="p-4 text-center">
                            <?php if($s['stok_gudang'] <= 5): ?>
                                <span class="bg-red-900 text-red-200 px-2 py-1 rounded text-xs font-bold">KRITIS</span>
                            <?php else: ?>
                                <span class="bg-emerald-900 text-emerald-200 px-2 py-1 rounded text-xs">AMAN</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="bg-slate-900 font-bold">
                    <tr>
                        <td colspan="3" class="p-4 text-right">Total Nilai Aset Gudang:</td>
                        <td class="p-4 text-right text-emerald-400">Rp <?= number_format($total_aset, 0, ',', '.') ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</body>
</html>
