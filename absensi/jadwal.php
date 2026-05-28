<?php
require_once '_auth.php';
require_hr_role();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_user_id = (int)($_POST['user_id'] ?? 0);
    $tanggal = $_POST['tanggal'] ?? '';
    $shift_id = !empty($_POST['shift_id']) ? (int)$_POST['shift_id'] : null;
    $status = $_POST['status_jadwal'] ?? 'Dijadwalkan';
    $catatan = trim($_POST['catatan'] ?? '');
    $allowed = ['Dijadwalkan','Libur','Izin','Cuti','Sakit','Tukar Shift','Dibatalkan'];
    if (!$target_user_id || !$tanggal || !in_array($status, $allowed, true)) {
        redirect_with('jadwal.php', 'error', 'Data jadwal tidak lengkap.');
    }
    $cek = $pdo->prepare("SELECT id FROM users WHERE id = ? AND tenant_id = ?");
    $cek->execute([$target_user_id, $tenant_id]);
    if (!$cek->fetch()) redirect_with('jadwal.php', 'error', 'Karyawan tidak valid.');

    $stmt = $pdo->prepare("INSERT INTO jadwal_shift (tenant_id, user_id, tanggal, shift_id, status_jadwal, catatan, created_by)
                           VALUES (?, ?, ?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE shift_id = VALUES(shift_id), status_jadwal = VALUES(status_jadwal), catatan = VALUES(catatan), updated_at = CURRENT_TIMESTAMP");
    $stmt->execute([$tenant_id, $target_user_id, $tanggal, $shift_id, $status, $catatan, $user_id_login]);
    redirect_with('jadwal.php', 'success', 'Jadwal berhasil disimpan.');
}

$stmt = $pdo->prepare("SELECT id,nama,role FROM users WHERE tenant_id = ? ORDER BY nama ASC");
$stmt->execute([$tenant_id]);
$users = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT id,nama_shift,jam_mulai,jam_selesai FROM shifts WHERE tenant_id = ? AND aktif = 1 ORDER BY jam_mulai ASC");
$stmt->execute([$tenant_id]);
$shifts = $stmt->fetchAll();
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$stmt = $pdo->prepare("SELECT js.*, u.nama, u.role, s.nama_shift, s.jam_mulai, s.jam_selesai
                       FROM jadwal_shift js
                       JOIN users u ON u.id = js.user_id
                       LEFT JOIN shifts s ON s.id = js.shift_id
                       WHERE js.tenant_id = ? AND js.tanggal = ?
                       ORDER BY u.nama ASC");
$stmt->execute([$tenant_id, $tanggal]);
$jadwals = $stmt->fetchAll();
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Jadwal Shift</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-900 text-slate-200 min-h-screen"><div class="max-w-6xl mx-auto p-6">
<div class="flex justify-between items-center mb-6"><div><h1 class="text-3xl font-bold text-emerald-400">Jadwal Shift</h1><p class="text-slate-400">Atur jadwal karyawan per tanggal.</p></div><a href="index.php" class="bg-slate-800 px-4 py-2 rounded-lg">← Absensi</a></div>
<?php if ($msg = flash('success')): ?><div class="mb-4 p-3 bg-emerald-900/50 border border-emerald-500 rounded"><?= h($msg) ?></div><?php endif; ?>
<?php if ($msg = flash('error')): ?><div class="mb-4 p-3 bg-red-900/50 border border-red-500 rounded"><?= h($msg) ?></div><?php endif; ?>
<div class="grid md:grid-cols-3 gap-6"><form method="POST" class="bg-slate-800 border border-slate-700 rounded-2xl p-5 space-y-4"><h2 class="text-xl font-bold">Input Jadwal</h2><div><label class="text-sm text-slate-400">Karyawan</label><select name="user_id" required class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded"><option value="">Pilih</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= h($u['nama']) ?> - <?= h($u['role']) ?></option><?php endforeach; ?></select></div><div><label class="text-sm text-slate-400">Tanggal</label><input type="date" name="tanggal" value="<?= h($tanggal) ?>" required class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded"></div><div><label class="text-sm text-slate-400">Shift</label><select name="shift_id" class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded"><option value="">Tanpa shift</option><?php foreach($shifts as $s): ?><option value="<?= (int)$s['id'] ?>"><?= h($s['nama_shift']) ?> (<?= h(substr($s['jam_mulai'],0,5)) ?>-<?= h(substr($s['jam_selesai'],0,5)) ?>)</option><?php endforeach; ?></select></div><div><label class="text-sm text-slate-400">Status</label><select name="status_jadwal" class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded"><option>Dijadwalkan</option><option>Libur</option><option>Izin</option><option>Cuti</option><option>Sakit</option><option>Tukar Shift</option><option>Dibatalkan</option></select></div><div><label class="text-sm text-slate-400">Catatan</label><textarea name="catatan" class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded"></textarea></div><button class="w-full bg-emerald-600 hover:bg-emerald-700 py-3 rounded font-bold">Simpan Jadwal</button></form><div class="md:col-span-2 bg-slate-800 border border-slate-700 rounded-2xl p-5"><form class="mb-4"><label class="text-sm text-slate-400">Lihat tanggal</label><div class="flex gap-2 mt-1"><input type="date" name="tanggal" value="<?= h($tanggal) ?>" class="p-3 bg-slate-900 border border-slate-700 rounded"><button class="px-4 bg-slate-700 rounded">Lihat</button></div></form><div class="overflow-x-auto"><table class="w-full text-sm"><thead class="text-slate-400"><tr><th class="p-2 text-left">Karyawan</th><th class="p-2 text-left">Shift</th><th class="p-2 text-left">Status</th><th class="p-2 text-left">Catatan</th></tr></thead><tbody><?php foreach($jadwals as $j): ?><tr class="border-t border-slate-700"><td class="p-2 font-semibold"><?= h($j['nama']) ?> <span class="text-slate-500">(<?= h($j['role']) ?>)</span></td><td class="p-2"><?= $j['nama_shift'] ? h($j['nama_shift']).' '.h(substr($j['jam_mulai'],0,5)).'-'.h(substr($j['jam_selesai'],0,5)) : '-' ?></td><td class="p-2"><?= h($j['status_jadwal']) ?></td><td class="p-2 text-slate-400"><?= h($j['catatan']) ?></td></tr><?php endforeach; ?><?php if(!$jadwals): ?><tr><td colspan="4" class="p-4 text-center text-slate-400">Belum ada jadwal pada tanggal ini.</td></tr><?php endif; ?></tbody></table></div></div></div></div></body></html>
