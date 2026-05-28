<?php
require_once '_auth.php';

$start = date('Y-m-01');
$end = date('Y-m-t');
$nama = 'Gaji ' . date('F Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Periode Gaji</title>
    <script src="https://cdn.tailwindcss.com"></script>

<style>
    .field {
        width: 100%;
        padding: 0.75rem;
        border-radius: 0.75rem;
        border: 1px solid rgb(51 65 85);
        background: rgb(2 6 23);
        color: rgb(226 232 240);
    }
    .card {
        background: rgb(15 23 42);
        border: 1px solid rgb(30 41 59);
        border-radius: 1rem;
    }
    .label {
        font-size: .75rem;
        color: rgb(148 163 184);
    }
    .value {
        font-weight: 700;
        color: rgb(241 245 249);
        overflow-wrap: anywhere;
    }
    @media print {
        .no-print { display: none !important; }
        body { background: white !important; color: black !important; }
        .card { background: white !important; color: black !important; border: 1px solid #ddd !important; }
        .label { color: #555 !important; }
        .value { color: #000 !important; }
    }
</style>

</head>
<body class="bg-slate-950 text-slate-200 min-h-screen">
<div class="max-w-3xl mx-auto p-4 md:p-6">
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-emerald-400">Buat Periode Gaji</h1>
            <p class="text-slate-400 text-sm md:text-base">Generate otomatis dari absensi dan jadwal shift.</p>
        </div>
        <a href="index.php" class="text-center px-4 py-3 bg-slate-800 rounded-xl">← Gaji</a>
    </div>

    <?php if ($msg = flash('error')): ?>
        <div class="mb-4 p-3 bg-red-900/50 border border-red-500 rounded"><?= h($msg) ?></div>
    <?php endif; ?>

    <form method="POST" action="proses_generate.php" class="card p-5 md:p-6 space-y-4">
        <label class="block">
            <span class="label">Nama Periode</span>
            <input name="nama_periode" value="<?= h($nama) ?>" required class="field mt-1">
        </label>
        <div class="grid md:grid-cols-2 gap-4">
            <label class="block">
                <span class="label">Periode Mulai</span>
                <input type="date" name="periode_mulai" value="<?= h($start) ?>" required class="field mt-1">
            </label>
            <label class="block">
                <span class="label">Periode Selesai</span>
                <input type="date" name="periode_selesai" value="<?= h($end) ?>" required class="field mt-1">
            </label>
        </div>

        <label class="block">
            <span class="label">Catatan</span>
            <textarea name="catatan" rows="3" class="field mt-1"></textarea>
        </label>

        <label class="flex items-start gap-3 p-4 rounded-xl bg-amber-950/40 border border-amber-700">
            <input type="checkbox" name="replace_existing" value="1" class="mt-1">
            <span>
                <b>Generate ulang jika periode sama sudah ada</b><br>
                <span class="text-sm text-slate-400">Hanya bisa mengganti periode yang masih Draft.</span>
            </span>
        </label>

        <button class="w-full py-3 bg-emerald-600 rounded-xl font-bold" onclick="return confirm('Generate gaji periode ini?')">Generate Gaji</button>
    </form>
</div>
</body>
</html>
