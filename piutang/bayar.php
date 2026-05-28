<?php
// piutang/bayar.php
session_start();
require_once '../config/database.php';
require_once '../includes/plans.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Owner', 'Admin', 'Kasir'], true)) {
    die('Akses ditolak.');
}

$tenant_id = (int) $_SESSION['tenant_id'];
$subscription = kasirrapi_tenant_subscription($pdo, $tenant_id);
if (!kasirrapi_feature_allowed($subscription, 'piutang')) {
    die('Fitur Piutang tersedia mulai paket Plus.');
}
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) die('ID piutang tidak valid.');

$stmt = $pdo->prepare("
    SELECT p.*, pl.nama_pelanggan, pl.no_wa, pl.alamat
    FROM piutang p
    INNER JOIN pelanggan pl ON p.pelanggan_id = pl.id
    WHERE p.id = ? AND p.tenant_id = ?
");
$stmt->execute([$id, $tenant_id]);
$piutang = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$piutang) die('Data piutang tidak ditemukan.');

$stmt_bayar = $pdo->prepare("
    SELECT pb.*, u.nama AS nama_kasir
    FROM piutang_pembayaran pb
    INNER JOIN users u ON pb.kasir_id = u.id
    WHERE pb.piutang_id = ? AND pb.tenant_id = ?
    ORDER BY pb.id DESC
");
$stmt_bayar->execute([$id, $tenant_id]);
$riwayat = $stmt_bayar->fetchAll(PDO::FETCH_ASSOC);

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function rp($v) { return 'Rp ' . number_format((float)$v, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bayar Piutang</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">
    <div class="max-w-5xl mx-auto p-4 md:p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-emerald-300"><i class="fa fa-money-bill-wave mr-2"></i>Bayar Piutang</h1>
                <p class="text-slate-400 text-sm mt-1">Invoice <?= e($piutang['nomor_invoice']) ?></p>
            </div>
            <a href="index.php" class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm font-bold"><i class="fa fa-arrow-left mr-1"></i> Kembali</a>
        </div>

        <?php if (isset($_GET['sukses'])): ?>
            <div class="mb-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 rounded-lg p-3 font-bold">Pembayaran berhasil disimpan.</div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="mb-4 bg-red-500/10 border border-red-500/30 text-red-300 rounded-lg p-3 font-bold"><?= e($_GET['error']) ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-1 bg-slate-800 border border-slate-700 rounded-xl p-4">
                <h2 class="font-bold text-lg mb-3">Ringkasan</h2>
                <div class="space-y-3 text-sm">
                    <div><div class="text-slate-400 text-xs">Pelanggan</div><div class="font-bold text-white"><?= e($piutang['nama_pelanggan']) ?></div><div class="text-slate-400 text-xs"><?= e($piutang['no_wa'] ?: '-') ?></div></div>
                    <div><div class="text-slate-400 text-xs">Total Piutang</div><div class="font-bold text-amber-300 text-xl"><?= rp($piutang['jumlah_piutang']) ?></div></div>
                    <div><div class="text-slate-400 text-xs">Sudah Dibayar</div><div class="font-bold text-emerald-300 text-xl"><?= rp($piutang['total_dibayar']) ?></div></div>
                    <div><div class="text-slate-400 text-xs">Sisa Piutang</div><div class="font-bold text-red-300 text-2xl"><?= rp($piutang['sisa_piutang']) ?></div></div>
                    <div><div class="text-slate-400 text-xs">Status</div><div class="font-bold"><?= e($piutang['status']) ?></div></div>
                </div>
            </div>

            <div class="lg:col-span-2 bg-slate-800 border border-slate-700 rounded-xl p-4">
                <h2 class="font-bold text-lg mb-3">Input Pembayaran</h2>
                <?php if ($piutang['status'] === 'Lunas' || (float)$piutang['sisa_piutang'] <= 0): ?>
                    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 rounded-lg p-4 font-bold">Piutang ini sudah lunas.</div>
                <?php else: ?>
                    <form method="POST" action="proses_bayar.php" onsubmit="return validasiBayar()" class="space-y-3">
                        <input type="hidden" name="piutang_id" value="<?= (int)$piutang['id'] ?>">
                        <div>
                            <label class="block text-sm text-slate-400 mb-1">Nominal Bayar</label>
                            <input type="number" name="nominal_bayar" id="nominal_bayar" min="1" max="<?= (float)$piutang['sisa_piutang'] ?>" value="<?= (float)$piutang['sisa_piutang'] ?>" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-3 text-xl font-bold text-right outline-none focus:border-emerald-400" required>
                            <p class="text-xs text-slate-500 mt-1">Maksimal: <?= rp($piutang['sisa_piutang']) ?></p>
                        </div>
                        <div>
                            <label class="block text-sm text-slate-400 mb-1">Metode Bayar</label>
                            <select name="metode_bayar" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-3 outline-none focus:border-emerald-400">
                                <option value="Tunai">Tunai</option>
                                <option value="QRIS">QRIS</option>
                                <option value="Transfer">Transfer</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-slate-400 mb-1">Catatan</label>
                            <textarea name="catatan" rows="3" class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-3 outline-none focus:border-emerald-400" placeholder="Opsional"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg py-3 font-bold cursor-pointer"><i class="fa fa-check-circle mr-1"></i> Simpan Pembayaran</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden mt-4">
            <div class="p-4 border-b border-slate-700 font-bold">Riwayat Pembayaran</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-700/60 text-slate-300"><tr><th class="p-3 text-left">Tanggal</th><th class="p-3 text-left">Kasir</th><th class="p-3 text-left">Metode</th><th class="p-3 text-right">Nominal</th><th class="p-3 text-left">Catatan</th></tr></thead>
                    <tbody class="divide-y divide-slate-700">
                        <?php if (!$riwayat): ?><tr><td colspan="5" class="p-5 text-center text-slate-400 italic">Belum ada pembayaran.</td></tr><?php endif; ?>
                        <?php foreach ($riwayat as $r): ?>
                            <tr>
                                <td class="p-3"><?= e(date('d/m/Y H:i', strtotime($r['tanggal_bayar']))) ?></td>
                                <td class="p-3"><?= e($r['nama_kasir']) ?></td>
                                <td class="p-3"><?= e($r['metode_bayar']) ?></td>
                                <td class="p-3 text-right font-bold text-emerald-300"><?= rp($r['nominal_bayar']) ?></td>
                                <td class="p-3 text-slate-300"><?= e($r['catatan'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function validasiBayar() {
            const sisa = <?= json_encode((float)$piutang['sisa_piutang']) ?>;
            const nominal = parseFloat(document.getElementById('nominal_bayar').value) || 0;
            if (nominal <= 0) { alert('Nominal bayar harus lebih dari 0.'); return false; }
            if (nominal > sisa) { alert('Nominal bayar tidak boleh lebih dari sisa piutang.'); return false; }
            return confirm('Simpan pembayaran piutang ini?');
        }
    </script>
</body>
</html>
