<?php
require_once '_auth.php';
require_hr_role();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_shift = trim($_POST['nama_shift'] ?? '');
    $jam_mulai = $_POST['jam_mulai'] ?? '';
    $jam_selesai = $_POST['jam_selesai'] ?? '';
    $tol_telat = max(0, (int)($_POST['toleransi_terlambat_menit'] ?? 10));
    $tol_pulang = max(0, (int)($_POST['toleransi_pulang_cepat_menit'] ?? 10));
    $lintas_hari = isset($_POST['lintas_hari']) ? 1 : 0;

    if ($nama_shift === '' || $jam_mulai === '' || $jam_selesai === '') {
        redirect_with('shift.php', 'error', 'Nama shift, jam mulai, dan jam selesai wajib diisi.');
    }
    $stmt = $pdo->prepare("INSERT INTO shifts (tenant_id, nama_shift, jam_mulai, jam_selesai, toleransi_terlambat_menit, toleransi_pulang_cepat_menit, lintas_hari) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$tenant_id, $nama_shift, $jam_mulai, $jam_selesai, $tol_telat, $tol_pulang, $lintas_hari]);
    redirect_with('shift.php', 'success', 'Shift berhasil ditambahkan.');
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE shifts SET aktif = IF(aktif=1,0,1) WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant_id]);
    redirect_with('shift.php', 'success', 'Status shift berhasil diubah.');
}

$stmt = $pdo->prepare("SELECT * FROM shifts WHERE tenant_id = ? ORDER BY aktif DESC, jam_mulai ASC");
$stmt->execute([$tenant_id]);
$shifts = $stmt->fetchAll();
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Master Shift</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-900 text-slate-200 min-h-screen"><div class="max-w-6xl mx-auto p-6">
<div class="flex justify-between items-center mb-6"><div><h1 class="text-3xl font-bold text-emerald-400">Master Shift</h1><p class="text-slate-400">Atur jam kerja dan toleransi keterlambatan.</p></div><a href="index.php" class="bg-slate-800 px-4 py-2 rounded-lg">← Absensi</a></div>
<?php if ($msg = flash('success')): ?><div class="mb-4 p-3 bg-emerald-900/50 border border-emerald-500 rounded"><?= h($msg) ?></div><?php endif; ?>
<?php if ($msg = flash('error')): ?><div class="mb-4 p-3 bg-red-900/50 border border-red-500 rounded"><?= h($msg) ?></div><?php endif; ?>
<div class="grid md:grid-cols-3 gap-6">
<form method="POST" class="bg-slate-800 border border-slate-700 rounded-2xl p-5 space-y-4">
<h2 class="text-xl font-bold">Tambah Shift</h2>
<div><label class="text-sm text-slate-400">Nama Shift</label><input name="nama_shift" required class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded" placeholder="Pagi"></div>
<div class="grid grid-cols-2 gap-3"><div><label class="text-sm text-slate-400">Jam Mulai</label><input type="time" name="jam_mulai" required class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded"></div><div><label class="text-sm text-slate-400">Jam Selesai</label><input type="time" name="jam_selesai" required class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded"></div></div>
<div><label class="text-sm text-slate-400">Toleransi Terlambat (menit)</label><input type="number" name="toleransi_terlambat_menit" value="10" min="0" class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded"></div>
<div><label class="text-sm text-slate-400">Toleransi Pulang Cepat (menit)</label><input type="number" name="toleransi_pulang_cepat_menit" value="10" min="0" class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded"></div>
<label class="flex gap-2 items-center"><input type="checkbox" name="lintas_hari"> <span>Shift lintas hari/malam</span></label>
<button class="w-full bg-emerald-600 hover:bg-emerald-700 py-3 rounded font-bold">Simpan Shift</button>
</form>
<div class="md:col-span-2 bg-slate-800 border border-slate-700 rounded-2xl p-5"><h2 class="text-xl font-bold mb-4">Daftar Shift</h2><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="text-slate-400"><tr><th class="text-left p-2">Shift</th><th class="text-left p-2">Jam</th><th class="text-left p-2">Toleransi</th><th class="text-left p-2">Status</th><th class="text-left p-2">Aksi</th></tr></thead><tbody>
<?php foreach ($shifts as $s): ?><tr class="border-t border-slate-700"><td class="p-2 font-semibold"><?= h($s['nama_shift']) ?></td><td class="p-2"><?= h(substr($s['jam_mulai'],0,5)) ?> - <?= h(substr($s['jam_selesai'],0,5)) ?><?= $s['lintas_hari'] ? ' <span class="text-xs text-amber-300">lintas hari</span>' : '' ?></td><td class="p-2">Telat <?= (int)$s['toleransi_terlambat_menit'] ?>m · Pulang <?= (int)$s['toleransi_pulang_cepat_menit'] ?>m</td><td class="p-2"><?= $s['aktif'] ? '<span class="text-emerald-400">Aktif</span>' : '<span class="text-red-400">Nonaktif</span>' ?></td><td class="p-2"><a class="text-cyan-400" href="?toggle=<?= (int)$s['id'] ?>">Toggle</a></td></tr><?php endforeach; ?>
<?php if (!$shifts): ?><tr><td colspan="5" class="p-4 text-center text-slate-400">Belum ada shift.</td></tr><?php endif; ?>
</tbody></table></div></div></div></div></body></html>
