<?php
require_once '_auth.php';
if (!is_hr_role()) die('Akses ditolak.');

$status = $_GET['status'] ?? 'Menunggu';
$allowedStatus = ['Menunggu','Disetujui','Ditolak','Dibatalkan','Semua'];
if (!in_array($status, $allowedStatus, true)) $status = 'Menunggu';

$params = [$tenant_id];
$where = "pa.tenant_id = ?";
if ($status !== 'Semua') {
    $where .= " AND pa.status = ?";
    $params[] = $status;
}

$stmt = $pdo->prepare("SELECT pa.*, u.nama, u.role
                       FROM pengajuan_absensi pa
                       JOIN users u ON u.id = pa.user_id
                       WHERE $where
                       ORDER BY pa.created_at DESC
                       LIMIT 200");
$stmt->execute($params);
$pengajuan = $stmt->fetchAll();

$params = [$tenant_id];
$where = "st.tenant_id = ?";
if ($status !== 'Semua') {
    $where .= " AND st.status = ?";
    $params[] = $status;
}
$stmt = $pdo->prepare("SELECT st.*, ua.nama AS nama_asal, up.nama AS nama_pengganti, s.nama_shift, s.jam_mulai, s.jam_selesai
                       FROM shift_tukar st
                       JOIN users ua ON ua.id = st.user_asal_id
                       JOIN users up ON up.id = st.user_pengganti_id
                       JOIN shifts s ON s.id = st.shift_id
                       WHERE $where
                       ORDER BY st.created_at DESC
                       LIMIT 200");
$stmt->execute($params);
$tukar = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Absensi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-200 min-h-screen">
<div class="max-w-7xl mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-emerald-400">Approval Absensi</h1>
            <p class="text-slate-400">Setujui atau tolak pengajuan izin, manual absensi, dan tukar shift.</p>
        </div>
        <a href="index.php" class="px-4 py-2 bg-slate-800 rounded-lg">← Absensi</a>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="mb-4 p-3 bg-emerald-900/50 border border-emerald-500 rounded"><?= h($msg) ?></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="mb-4 p-3 bg-red-900/50 border border-red-500 rounded"><?= h($msg) ?></div>
    <?php endif; ?>

    <form method="GET" class="mb-6 bg-slate-900 border border-slate-800 rounded-2xl p-4 flex gap-3 items-end">
        <div>
            <label class="block text-sm text-slate-400 mb-1">Status</label>
            <select name="status" class="p-3 bg-slate-950 border border-slate-700 rounded">
                <?php foreach ($allowedStatus as $s): ?>
                    <option value="<?= h($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= h($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="px-5 py-3 bg-slate-700 rounded">Filter</button>
    </form>

    <h2 class="text-xl font-bold mb-3">Izin / Manual Absensi</h2>
    <div class="overflow-x-auto bg-slate-900 border border-slate-800 rounded-2xl mb-8">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-800 text-slate-300">
                <tr>
                    <th class="p-3 text-left">Karyawan</th>
                    <th class="p-3 text-left">Tanggal</th>
                    <th class="p-3 text-left">Jenis</th>
                    <th class="p-3 text-left">Jam</th>
                    <th class="p-3 text-left">Alasan</th>
                    <th class="p-3 text-left">Lampiran</th>
                    <th class="p-3 text-left">Status</th>
                    <th class="p-3 text-left">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pengajuan as $p): ?>
                <tr class="border-t border-slate-800 align-top">
                    <td class="p-3"><b><?= h($p['nama']) ?></b><br><span class="text-slate-500"><?= h($p['role']) ?></span></td>
                    <td class="p-3"><?= h($p['tanggal_mulai']) ?><?= $p['tanggal_mulai'] !== $p['tanggal_selesai'] ? ' s/d ' . h($p['tanggal_selesai']) : '' ?></td>
                    <td class="p-3"><?= h($p['jenis']) ?></td>
                    <td class="p-3"><?= h($p['jam_koreksi'] ?: '-') ?></td>
                    <td class="p-3 max-w-md"><?= h($p['alasan']) ?></td>
                    <td class="p-3"><?= $p['lampiran'] ? '<a class="underline" target="_blank" href="'.h($p['lampiran']).'">Lihat</a>' : '-' ?></td>
                    <td class="p-3 font-bold"><?= h($p['status']) ?></td>
                    <td class="p-3 min-w-[260px]">
                        <?php if ($p['status'] === 'Menunggu'): ?>
                        <form method="POST" action="proses_approval.php" class="space-y-2">
                            <input type="hidden" name="tipe" value="pengajuan_absensi">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <textarea name="catatan" rows="2" class="w-full p-2 bg-slate-950 border border-slate-700 rounded" placeholder="Catatan approval opsional"></textarea>
                            <div class="flex gap-2">
                                <button name="aksi" value="setujui" class="flex-1 py-2 bg-emerald-600 rounded">Setujui</button>
                                <button name="aksi" value="tolak" class="flex-1 py-2 bg-red-600 rounded" onclick="return confirm('Tolak pengajuan ini?')">Tolak</button>
                            </div>
                        </form>
                        <?php else: ?>
                            <span class="text-slate-500"><?= h($p['catatan_approval'] ?: '-') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$pengajuan): ?><tr><td colspan="8" class="p-4 text-center text-slate-500">Tidak ada data.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <h2 class="text-xl font-bold mb-3">Tukar Shift</h2>
    <div class="overflow-x-auto bg-slate-900 border border-slate-800 rounded-2xl">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-800 text-slate-300">
                <tr>
                    <th class="p-3 text-left">Tanggal</th>
                    <th class="p-3 text-left">Shift</th>
                    <th class="p-3 text-left">Asal</th>
                    <th class="p-3 text-left">Pengganti</th>
                    <th class="p-3 text-left">Alasan</th>
                    <th class="p-3 text-left">Status</th>
                    <th class="p-3 text-left">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tukar as $t): ?>
                <tr class="border-t border-slate-800 align-top">
                    <td class="p-3"><?= h($t['tanggal']) ?></td>
                    <td class="p-3"><?= h($t['nama_shift']) ?><br><span class="text-slate-500"><?= h(substr($t['jam_mulai'],0,5)) ?> - <?= h(substr($t['jam_selesai'],0,5)) ?></span></td>
                    <td class="p-3"><?= h($t['nama_asal']) ?></td>
                    <td class="p-3"><?= h($t['nama_pengganti']) ?></td>
                    <td class="p-3 max-w-md"><?= h($t['alasan']) ?></td>
                    <td class="p-3 font-bold"><?= h($t['status']) ?></td>
                    <td class="p-3 min-w-[260px]">
                        <?php if ($t['status'] === 'Menunggu'): ?>
                        <form method="POST" action="proses_approval.php" class="space-y-2">
                            <input type="hidden" name="tipe" value="shift_tukar">
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <textarea name="catatan" rows="2" class="w-full p-2 bg-slate-950 border border-slate-700 rounded" placeholder="Catatan approval opsional"></textarea>
                            <div class="flex gap-2">
                                <button name="aksi" value="setujui" class="flex-1 py-2 bg-emerald-600 rounded">Setujui</button>
                                <button name="aksi" value="tolak" class="flex-1 py-2 bg-red-600 rounded" onclick="return confirm('Tolak tukar shift ini?')">Tolak</button>
                            </div>
                        </form>
                        <?php else: ?>
                            <span class="text-slate-500"><?= h($t['catatan_approval'] ?: '-') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$tukar): ?><tr><td colspan="7" class="p-4 text-center text-slate-500">Tidak ada data.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
