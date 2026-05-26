<?php
require_once '_auth.php';
require_hr_role();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_user_id = (int)($_POST['user_id'] ?? 0);
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
    $jam_masuk = trim($_POST['jam_masuk'] ?? '');
    $jam_pulang = trim($_POST['jam_pulang'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');
    if (!$target_user_id || !$tanggal || $catatan === '') redirect_with('manual.php', 'error', 'Karyawan, tanggal, dan catatan manual wajib diisi.');
    $cek = $pdo->prepare("SELECT id FROM users WHERE id=? AND tenant_id=?"); $cek->execute([$target_user_id,$tenant_id]); if(!$cek->fetch()) redirect_with('manual.php','error','Karyawan tidak valid.');
    $jadwal = get_today_jadwal($pdo, $tenant_id, $target_user_id, $tanggal);
    $jadwal_id = $jadwal ? (int)$jadwal['id'] : null;
    $jm = $jam_masuk ? $tanggal.' '.$jam_masuk.':00' : null;
    $jp = $jam_pulang ? $tanggal.' '.$jam_pulang.':00' : null;
    if ($jm && $jp && strtotime($jp) < strtotime($jm)) $jp = date('Y-m-d H:i:s', strtotime($jp.' +1 day'));
    $durasi = ($jm && $jp) ? max(0, (int)floor((strtotime($jp)-strtotime($jm))/60)) : 0;
    $status = $jp ? 'Hadir' : 'Belum Pulang';
    $stmt = $pdo->prepare("INSERT INTO absensi (tenant_id,user_id,jadwal_shift_id,tanggal,jam_masuk,jam_pulang,metode_masuk,metode_pulang,status_kehadiran,durasi_kerja_menit,catatan,created_by,updated_by)
                           VALUES (?,?,?,?,?,?, 'Manual', IF(? IS NULL, NULL, 'Manual'), ?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE jam_masuk=VALUES(jam_masuk), jam_pulang=VALUES(jam_pulang), metode_masuk='Manual', metode_pulang=IF(VALUES(jam_pulang) IS NULL, metode_pulang, 'Manual'), status_kehadiran=VALUES(status_kehadiran), durasi_kerja_menit=VALUES(durasi_kerja_menit), catatan=VALUES(catatan), updated_by=VALUES(updated_by)");
    $stmt->execute([$tenant_id,$target_user_id,$jadwal_id,$tanggal,$jm,$jp,$jp,$status,$durasi,$catatan,$user_id_login,$user_id_login]);
    redirect_with('manual.php','success','Absensi manual berhasil disimpan.');
}
$stmt = $pdo->prepare("SELECT id,nama,role FROM users WHERE tenant_id=? ORDER BY nama ASC"); $stmt->execute([$tenant_id]); $users=$stmt->fetchAll();
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Absensi Manual</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-slate-900 text-slate-200 min-h-screen"><div class="max-w-3xl mx-auto p-6"><div class="flex justify-between items-center mb-6"><div><h1 class="text-3xl font-bold text-emerald-400">Absensi Manual</h1><p class="text-slate-400">Fallback saat kamera bermasalah. Wajib ada catatan audit.</p></div><a href="index.php" class="bg-slate-800 px-4 py-2 rounded-lg">← Absensi</a></div><?php if($m=flash('success')):?><div class="mb-4 p-3 bg-emerald-900/50 border border-emerald-500 rounded"><?=h($m)?></div><?php endif;?><?php if($m=flash('error')):?><div class="mb-4 p-3 bg-red-900/50 border border-red-500 rounded"><?=h($m)?></div><?php endif;?><form method="POST" class="bg-slate-800 border border-slate-700 rounded-2xl p-5 space-y-4"><div><label class="text-sm text-slate-400">Karyawan</label><select name="user_id" required class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded"><option value="">Pilih</option><?php foreach($users as $u):?><option value="<?=(int)$u['id']?>"><?=h($u['nama'])?> - <?=h($u['role'])?></option><?php endforeach;?></select></div><div><label class="text-sm text-slate-400">Tanggal</label><input type="date" name="tanggal" value="<?=date('Y-m-d')?>" required class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded"></div><div class="grid grid-cols-2 gap-3"><div><label class="text-sm text-slate-400">Jam Masuk</label><input type="time" name="jam_masuk" class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded"></div><div><label class="text-sm text-slate-400">Jam Pulang</label><input type="time" name="jam_pulang" class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded"></div></div><div><label class="text-sm text-slate-400">Catatan/alasan manual</label><textarea name="catatan" required class="w-full mt-1 p-3 bg-slate-900 border border-slate-700 rounded" placeholder="Contoh: kamera toko bermasalah, diinput oleh HRD berdasarkan konfirmasi Owner."></textarea></div><button class="w-full py-3 bg-emerald-600 rounded font-bold">Simpan Absensi Manual</button></form></div></body></html>
