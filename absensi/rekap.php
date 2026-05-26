<?php
require_once '_auth.php';
$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');
$target_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$where = "a.tenant_id = ? AND a.tanggal BETWEEN ? AND ?";
$params = [$tenant_id, $dari, $sampai];
if (!is_hr_role()) {
    $where .= " AND a.user_id = ?";
    $params[] = $user_id_login;
} elseif ($target_user_id > 0) {
    $where .= " AND a.user_id = ?";
    $params[] = $target_user_id;
}

if (is_hr_role()) {
    $stmt = $pdo->prepare("SELECT id,nama,role FROM users WHERE tenant_id = ? ORDER BY nama ASC");
    $stmt->execute([$tenant_id]);
    $users = $stmt->fetchAll();
} else { $users = []; }

$stmt = $pdo->prepare("SELECT a.*, u.nama, u.role, s.nama_shift
                       FROM absensi a
                       JOIN users u ON u.id = a.user_id
                       LEFT JOIN jadwal_shift js ON js.id = a.jadwal_shift_id
                       LEFT JOIN shifts s ON s.id = js.shift_id
                       WHERE $where
                       ORDER BY a.tanggal DESC, u.nama ASC");
$stmt->execute($params);
$data = $stmt->fetchAll();
$total_hadir = 0; $total_telat = 0; $total_durasi = 0;
foreach($data as $r){ if($r['jam_masuk']) $total_hadir++; $total_telat += (int)$r['menit_terlambat']; $total_durasi += (int)$r['durasi_kerja_menit']; }
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Rekap Absensi</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-900 text-slate-200 min-h-screen"><div class="max-w-7xl mx-auto p-6">
<div class="flex justify-between items-center mb-6"><div><h1 class="text-3xl font-bold text-emerald-400">Rekap Absensi</h1><p class="text-slate-400">Masuk, pulang, keterlambatan, durasi kerja, dan foto audit.</p></div><a href="index.php" class="bg-slate-800 px-4 py-2 rounded-lg">← Absensi</a></div>
<form class="bg-slate-800 border border-slate-700 rounded-2xl p-4 mb-5 grid md:grid-cols-4 gap-3 items-end"><div><label class="text-sm text-slate-400">Dari</label><input type="date" name="dari" value="<?= h($dari) ?>" class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded"></div><div><label class="text-sm text-slate-400">Sampai</label><input type="date" name="sampai" value="<?= h($sampai) ?>" class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded"></div><?php if(is_hr_role()): ?><div><label class="text-sm text-slate-400">Karyawan</label><select name="user_id" class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded"><option value="0">Semua</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $target_user_id===(int)$u['id']?'selected':'' ?>><?= h($u['nama']) ?> - <?= h($u['role']) ?></option><?php endforeach; ?></select></div><?php endif; ?><button class="p-3 bg-emerald-600 rounded font-bold">Filter</button></form>
<div class="grid md:grid-cols-3 gap-4 mb-5"><div class="bg-slate-800 rounded-2xl p-4 border border-slate-700"><p class="text-slate-400 text-sm">Total hadir</p><h2 class="text-2xl font-bold"><?= $total_hadir ?></h2></div><div class="bg-slate-800 rounded-2xl p-4 border border-slate-700"><p class="text-slate-400 text-sm">Total menit terlambat</p><h2 class="text-2xl font-bold"><?= $total_telat ?></h2></div><div class="bg-slate-800 rounded-2xl p-4 border border-slate-700"><p class="text-slate-400 text-sm">Total jam kerja</p><h2 class="text-2xl font-bold"><?= number_format($total_durasi/60,1) ?> jam</h2></div></div>
<div class="bg-slate-800 border border-slate-700 rounded-2xl p-5 overflow-x-auto"><table class="w-full text-sm"><thead class="text-slate-400"><tr><th class="p-2 text-left">Tanggal</th><th class="p-2 text-left">Nama</th><th class="p-2 text-left">Shift</th><th class="p-2 text-left">Masuk</th><th class="p-2 text-left">Pulang</th><th class="p-2 text-left">Status</th><th class="p-2 text-right">Telat</th><th class="p-2 text-right">Pulang Cepat</th><th class="p-2 text-right">Durasi</th><th class="p-2 text-left">Foto</th></tr></thead><tbody><?php foreach($data as $r): ?><tr class="border-t border-slate-700"><td class="p-2"><?= h($r['tanggal']) ?></td><td class="p-2 font-semibold"><?= h($r['nama']) ?><br><span class="text-xs text-slate-500"><?= h($r['role']) ?></span></td><td class="p-2"><?= h($r['nama_shift'] ?: '-') ?></td><td class="p-2"><?= $r['jam_masuk'] ? date('H:i', strtotime($r['jam_masuk'])) : '-' ?><br><span class="text-xs text-slate-500"><?= h($r['metode_masuk']) ?></span></td><td class="p-2"><?= $r['jam_pulang'] ? date('H:i', strtotime($r['jam_pulang'])) : '-' ?><br><span class="text-xs text-slate-500"><?= h($r['metode_pulang']) ?></span></td><td class="p-2"><?= h($r['status_kehadiran']) ?></td><td class="p-2 text-right"><?= (int)$r['menit_terlambat'] ?>m</td><td class="p-2 text-right"><?= (int)$r['menit_pulang_cepat'] ?>m</td><td class="p-2 text-right"><?= (int)$r['durasi_kerja_menit'] ?>m</td><td class="p-2"><?php if($r['foto_masuk']): ?><a class="text-cyan-400" target="_blank" href="../<?= h($r['foto_masuk']) ?>">Masuk</a><?php endif; ?> <?php if($r['foto_pulang']): ?><a class="text-cyan-400" target="_blank" href="../<?= h($r['foto_pulang']) ?>">Pulang</a><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$data): ?><tr><td colspan="10" class="p-4 text-center text-slate-400">Tidak ada data.</td></tr><?php endif; ?></tbody></table></div>
</div></body></html>
