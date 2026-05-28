<?php
require_once '_auth.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajukan Izin / Manual Absensi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-200 min-h-screen">
<div class="max-w-4xl mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-emerald-400">Ajukan Izin / Manual Absensi</h1>
            <p class="text-slate-400">Semua pengajuan menunggu approval Owner/Admin/HRD.</p>
        </div>
        <a href="index.php" class="px-4 py-2 bg-slate-800 rounded-lg">← Absensi</a>
    </div>

    <?php if ($msg = flash('error')): ?>
        <div class="mb-4 p-3 bg-red-900/50 border border-red-500 rounded"><?= h($msg) ?></div>
    <?php endif; ?>

    <form method="POST" action="proses_izin.php" enctype="multipart/form-data" class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
        <div>
            <label class="block text-sm text-slate-400 mb-1">Jenis Pengajuan</label>
            <select name="jenis" id="jenis" required class="w-full p-3 bg-slate-950 border border-slate-700 rounded">
                <option value="Izin">Izin</option>
                <option value="Cuti">Cuti</option>
                <option value="Sakit">Sakit</option>
                <option value="Manual Masuk">Manual Masuk / Lupa Absen Masuk</option>
                <option value="Manual Pulang">Manual Pulang / Lupa Absen Pulang</option>
                <option value="Koreksi Masuk">Koreksi Jam Masuk</option>
                <option value="Koreksi Pulang">Koreksi Jam Pulang</option>
            </select>
        </div>

        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm text-slate-400 mb-1">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" value="<?= date('Y-m-d') ?>" required class="w-full p-3 bg-slate-950 border border-slate-700 rounded">
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1">Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai" value="<?= date('Y-m-d') ?>" required class="w-full p-3 bg-slate-950 border border-slate-700 rounded">
            </div>
        </div>

        <div id="jamBox">
            <label class="block text-sm text-slate-400 mb-1">Jam Koreksi / Manual</label>
            <input type="time" name="jam_koreksi" class="w-full p-3 bg-slate-950 border border-slate-700 rounded">
            <p class="text-xs text-slate-500 mt-1">Wajib untuk Manual/Koreksi Masuk atau Pulang.</p>
        </div>

        <div>
            <label class="block text-sm text-slate-400 mb-1">Alasan</label>
            <textarea name="alasan" required rows="5" class="w-full p-3 bg-slate-950 border border-slate-700 rounded" placeholder="Contoh: sakit, urusan keluarga, lupa absen karena..."></textarea>
        </div>

        <div>
            <label class="block text-sm text-slate-400 mb-1">Lampiran opsional</label>
            <input type="file" name="lampiran" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf" class="w-full p-3 bg-slate-950 border border-slate-700 rounded">
            <p class="text-xs text-slate-500 mt-1">Bisa upload surat dokter/foto bukti. Maksimal 3 MB.</p>
        </div>

        <button class="w-full py-3 bg-emerald-600 rounded-xl font-bold">Kirim Pengajuan</button>
    </form>
</div>

<script>
const jenis = document.getElementById('jenis');
const jamBox = document.getElementById('jamBox');

function toggleJam() {
    const need = ['Manual Masuk','Manual Pulang','Koreksi Masuk','Koreksi Pulang'].includes(jenis.value);
    jamBox.style.display = need ? 'block' : 'none';
}
jenis.addEventListener('change', toggleJam);
toggleJam();
</script>
</body>
</html>
