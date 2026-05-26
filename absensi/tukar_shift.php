<?php
require_once '_auth.php';

$stmt = $pdo->prepare("SELECT id, nama, role FROM users WHERE tenant_id = ? AND id <> ? ORDER BY nama ASC");
$stmt->execute([$tenant_id, $user_id_login]);
$users = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, nama_shift, jam_mulai, jam_selesai FROM shifts WHERE tenant_id = ? AND aktif = 1 ORDER BY jam_mulai ASC");
$stmt->execute([$tenant_id]);
$shifts = $stmt->fetchAll();

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$stmt = $pdo->prepare("SELECT js.*, s.nama_shift, s.jam_mulai, s.jam_selesai
                       FROM jadwal_shift js
                       JOIN shifts s ON s.id = js.shift_id
                       WHERE js.tenant_id = ? AND js.user_id = ? AND js.tanggal = ?
                       LIMIT 1");
$stmt->execute([$tenant_id, $user_id_login, $tanggal]);
$jadwalSaya = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajukan Tukar Shift</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-200 min-h-screen">
<div class="max-w-4xl mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-emerald-400">Ajukan Tukar Shift</h1>
            <p class="text-slate-400">Tukar shift berlaku setelah disetujui Owner/Admin/HRD.</p>
        </div>
        <a href="index.php" class="px-4 py-2 bg-slate-800 rounded-lg">← Absensi</a>
    </div>

    <?php if ($msg = flash('error')): ?>
        <div class="mb-4 p-3 bg-red-900/50 border border-red-500 rounded"><?= h($msg) ?></div>
    <?php endif; ?>

    <form method="GET" class="mb-4 bg-slate-900 border border-slate-800 rounded-2xl p-4">
        <label class="block text-sm text-slate-400 mb-1">Tanggal shift yang ingin ditukar</label>
        <div class="flex gap-2">
            <input type="date" name="tanggal" value="<?= h($tanggal) ?>" class="flex-1 p-3 bg-slate-950 border border-slate-700 rounded">
            <button class="px-5 py-3 bg-slate-700 rounded">Cek</button>
        </div>
    </form>

    <div class="mb-4 p-4 rounded-2xl border <?= $jadwalSaya ? 'border-emerald-600 bg-emerald-950/30' : 'border-amber-600 bg-amber-950/30' ?>">
        <?php if ($jadwalSaya): ?>
            Jadwal Anda pada <?= h($tanggal) ?>:
            <b><?= h($jadwalSaya['nama_shift']) ?></b>
            <?= h(substr($jadwalSaya['jam_mulai'],0,5)) ?> - <?= h(substr($jadwalSaya['jam_selesai'],0,5)) ?>
        <?php else: ?>
            Tidak ditemukan jadwal Anda pada tanggal ini. Anda tetap bisa mengajukan, tapi approval akan memvalidasi ulang.
        <?php endif; ?>
    </div>

    <form method="POST" action="proses_tukar_shift.php" class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
        <input type="hidden" name="tanggal" value="<?= h($tanggal) ?>">
        <input type="hidden" name="jadwal_asal_id" value="<?= $jadwalSaya ? (int)$jadwalSaya['id'] : 0 ?>">

        <div>
            <label class="block text-sm text-slate-400 mb-1">Shift yang ditukar</label>
            <select name="shift_id" required class="w-full p-3 bg-slate-950 border border-slate-700 rounded">
                <?php foreach ($shifts as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= $jadwalSaya && (int)$jadwalSaya['shift_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                        <?= h($s['nama_shift']) ?> (<?= h(substr($s['jam_mulai'],0,5)) ?> - <?= h(substr($s['jam_selesai'],0,5)) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm text-slate-400 mb-1">Karyawan Pengganti</label>
            <select name="user_pengganti_id" required class="w-full p-3 bg-slate-950 border border-slate-700 rounded">
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int)$u['id'] ?>"><?= h($u['nama']) ?> - <?= h($u['role']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm text-slate-400 mb-1">Alasan</label>
            <textarea name="alasan" required rows="5" class="w-full p-3 bg-slate-950 border border-slate-700 rounded"></textarea>
        </div>

        <button class="w-full py-3 bg-emerald-600 rounded-xl font-bold">Kirim Pengajuan Tukar Shift</button>
    </form>
</div>
</body>
</html>
