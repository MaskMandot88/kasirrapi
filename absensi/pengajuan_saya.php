<?php
require_once '_auth.php';

$stmt = $pdo->prepare("SELECT * FROM pengajuan_absensi WHERE tenant_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 100");
$stmt->execute([$tenant_id, $user_id_login]);
$pengajuan = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT st.*, ua.nama AS nama_asal, up.nama AS nama_pengganti, s.nama_shift
                       FROM shift_tukar st
                       JOIN users ua ON ua.id = st.user_asal_id
                       JOIN users up ON up.id = st.user_pengganti_id
                       JOIN shifts s ON s.id = st.shift_id
                       WHERE st.tenant_id = ? AND st.requested_by = ?
                       ORDER BY st.created_at DESC LIMIT 100");
$stmt->execute([$tenant_id, $user_id_login]);
$tukar = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Saya</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-200 min-h-screen">
<div class="max-w-6xl mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-emerald-400">Pengajuan Saya</h1>
            <p class="text-slate-400">Status izin, manual absensi, dan tukar shift.</p>
        </div>
        <a href="index.php" class="px-4 py-2 bg-slate-800 rounded-lg">← Absensi</a>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="mb-4 p-3 bg-emerald-900/50 border border-emerald-500 rounded"><?= h($msg) ?></div>
    <?php endif; ?>

    <h2 class="text-xl font-bold mb-3">Izin / Manual Absensi</h2>
    <div class="overflow-x-auto bg-slate-900 border border-slate-800 rounded-2xl mb-8">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-800 text-slate-300">
                <tr>
                    <th class="p-3 text-left">Tanggal</th>
                    <th class="p-3 text-left">Jenis</th>
                    <th class="p-3 text-left">Jam</th>
                    <th class="p-3 text-left">Alasan</th>
                    <th class="p-3 text-left">Status</th>
                    <th class="p-3 text-left">Catatan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pengajuan as $p): ?>
                <tr class="border-t border-slate-800">
                    <td class="p-3"><?= h($p['tanggal_mulai']) ?><?= $p['tanggal_mulai'] !== $p['tanggal_selesai'] ? ' s/d ' . h($p['tanggal_selesai']) : '' ?></td>
                    <td class="p-3"><?= h($p['jenis']) ?></td>
                    <td class="p-3"><?= h($p['jam_koreksi'] ?: '-') ?></td>
                    <td class="p-3 max-w-md"><?= h($p['alasan']) ?></td>
                    <td class="p-3 font-bold"><?= h($p['status']) ?></td>
                    <td class="p-3"><?= h($p['catatan_approval'] ?: '-') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$pengajuan): ?>
                <tr><td colspan="6" class="p-4 text-center text-slate-500">Belum ada pengajuan.</td></tr>
                <?php endif; ?>
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
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tukar as $t): ?>
                <tr class="border-t border-slate-800">
                    <td class="p-3"><?= h($t['tanggal']) ?></td>
                    <td class="p-3"><?= h($t['nama_shift']) ?></td>
                    <td class="p-3"><?= h($t['nama_asal']) ?></td>
                    <td class="p-3"><?= h($t['nama_pengganti']) ?></td>
                    <td class="p-3 max-w-md"><?= h($t['alasan']) ?></td>
                    <td class="p-3 font-bold"><?= h($t['status']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$tukar): ?>
                <tr><td colspan="6" class="p-4 text-center text-slate-500">Belum ada pengajuan tukar shift.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
