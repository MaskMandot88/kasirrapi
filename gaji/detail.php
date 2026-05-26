<?php
require_once '_auth.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM payroll_periode WHERE tenant_id = ? AND id = ? LIMIT 1");
$stmt->execute([$tenant_id, $id]);
$periode = $stmt->fetch();
if (!$periode) die('Periode tidak ditemukan.');

$stmt = $pdo->prepare("SELECT pd.*, u.nama, u.role, u.email
                       FROM payroll_detail pd
                       JOIN users u ON u.id = pd.user_id
                       WHERE pd.tenant_id = ? AND pd.payroll_id = ?
                       ORDER BY u.nama ASC");
$stmt->execute([$tenant_id, $id]);
$details = $stmt->fetchAll();

$export = $_GET['export'] ?? '';
if ($export === 'xls') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="gaji_' . preg_replace('/[^a-zA-Z0-9_-]/','_', $periode['nama_periode']) . '.xls"');
    echo "<table border='1'>";
    echo "<tr><th>Nama</th><th>Role</th><th>Hari Jadwal</th><th>Hadir</th><th>Izin</th><th>Cuti</th><th>Sakit</th><th>Alpha</th><th>Telat Menit</th><th>Pulang Cepat Menit</th><th>Jam Lembur</th><th>Gaji Pokok</th><th>Makan</th><th>Transport</th><th>Lembur</th><th>Pot Telat</th><th>Pot Pulang Cepat</th><th>Pot Alpha</th><th>Total</th><th>Status Bayar</th></tr>";
    foreach ($details as $d) {
        echo "<tr>";
        foreach (['nama','role','hari_jadwal','hari_hadir','hari_izin','hari_cuti','hari_sakit','hari_alpha','total_menit_terlambat','total_menit_pulang_cepat','total_jam_lembur','gaji_pokok','uang_makan','uang_transport','uang_lembur','potongan_terlambat','potongan_pulang_cepat','potongan_alpha','total_gaji','status_bayar'] as $f) {
            echo "<td>" . h($d[$f]) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Gaji</title>
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
<div class="max-w-7xl mx-auto p-4 md:p-6">
    <div class="flex flex-col gap-4 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-emerald-400"><?= h($periode['nama_periode']) ?></h1>
            <p class="text-slate-400 text-sm md:text-base"><?= tanggal_id($periode['periode_mulai']) ?> - <?= tanggal_id($periode['periode_selesai']) ?> · Status: <?= h($periode['status']) ?></p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-2 no-print">
            <a href="index.php" class="text-center px-4 py-3 bg-slate-800 rounded-xl">← Gaji</a>
            <a href="detail.php?id=<?= $id ?>&export=xls" class="text-center px-4 py-3 bg-emerald-700 rounded-xl">Excel</a>
            <button onclick="window.print()" class="px-4 py-3 bg-cyan-700 rounded-xl">PDF / Print</button>
            <?php if ($periode['status'] === 'Draft'): ?>
            <form method="POST" action="kunci.php" onsubmit="return confirm('Kunci periode ini? Setelah dikunci, detail tidak bisa digenerate ulang.')" class="col-span-2 md:col-span-2">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button class="w-full px-4 py-3 bg-amber-600 rounded-xl">Kunci Periode</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($msg = flash('success')): ?>
        <div class="mb-4 p-3 bg-emerald-900/50 border border-emerald-500 rounded no-print"><?= h($msg) ?></div>
    <?php endif; ?>
    <?php if ($msg = flash('error')): ?>
        <div class="mb-4 p-3 bg-red-900/50 border border-red-500 rounded no-print"><?= h($msg) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="card p-4">
            <div class="label">Total Gaji</div>
            <div class="text-xl md:text-2xl font-bold"><?= rupiah($periode['total_gaji']) ?></div>
        </div>
        <div class="card p-4">
            <div class="label">Karyawan</div>
            <div class="text-xl md:text-2xl font-bold"><?= count($details) ?></div>
        </div>
        <div class="card p-4">
            <div class="label">Sudah Dibayar</div>
            <div class="text-xl md:text-2xl font-bold"><?= count(array_filter($details, fn($d)=>$d['status_bayar']==='Sudah Dibayar')) ?></div>
        </div>
        <div class="card p-4">
            <div class="label">Belum Dibayar</div>
            <div class="text-xl md:text-2xl font-bold"><?= count(array_filter($details, fn($d)=>$d['status_bayar']!=='Sudah Dibayar')) ?></div>
        </div>
    </div>

    <div class="space-y-4">
        <?php foreach ($details as $d):
            $pendapatan = $d['gaji_pokok'] + $d['uang_makan'] + $d['uang_transport'] + $d['uang_lembur'] + $d['bonus'];
            $potongan = $d['potongan_terlambat'] + $d['potongan_pulang_cepat'] + $d['potongan_alpha'] + $d['potongan_lain'];
        ?>
        <div class="card p-4 md:p-5">
            <div class="flex flex-col md:flex-row md:justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-xl font-bold"><?= h($d['nama']) ?></h2>
                    <p class="text-slate-400 text-sm"><?= h($d['role']) ?> · <?= h($d['status_bayar']) ?></p>
                </div>
                <div class="text-left md:text-right">
                    <div class="label">Total Gaji</div>
                    <div class="text-2xl font-extrabold text-emerald-300"><?= rupiah($d['total_gaji']) ?></div>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                <div class="bg-slate-950 rounded-xl p-3"><div class="label">Jadwal</div><div class="value"><?= (int)$d['hari_jadwal'] ?></div></div>
                <div class="bg-slate-950 rounded-xl p-3"><div class="label">Hadir</div><div class="value"><?= (int)$d['hari_hadir'] ?></div></div>
                <div class="bg-slate-950 rounded-xl p-3"><div class="label">Izin/Cuti/Sakit</div><div class="value"><?= (int)$d['hari_izin'] ?>/<?= (int)$d['hari_cuti'] ?>/<?= (int)$d['hari_sakit'] ?></div></div>
                <div class="bg-slate-950 rounded-xl p-3"><div class="label">Alpha</div><div class="value"><?= (int)$d['hari_alpha'] ?></div></div>
                <div class="bg-slate-950 rounded-xl p-3"><div class="label">Telat</div><div class="value"><?= (int)$d['total_menit_terlambat'] ?> m</div></div>
                <div class="bg-slate-950 rounded-xl p-3"><div class="label">Pulang Cepat</div><div class="value"><?= (int)$d['total_menit_pulang_cepat'] ?> m</div></div>
                <div class="bg-slate-950 rounded-xl p-3"><div class="label">Lembur</div><div class="value"><?= h($d['total_jam_lembur']) ?> j</div></div>
                <div class="bg-slate-950 rounded-xl p-3"><div class="label">Kerja</div><div class="value"><?= floor((int)$d['total_menit_kerja']/60) ?> j <?= ((int)$d['total_menit_kerja']%60) ?> m</div></div>
            </div>

            <div class="grid md:grid-cols-2 gap-3 mb-4">
                <div class="bg-slate-950 rounded-xl p-3">
                    <div class="label mb-2">Pendapatan</div>
                    <div class="flex justify-between"><span>Gaji Pokok</span><b><?= rupiah($d['gaji_pokok']) ?></b></div>
                    <div class="flex justify-between"><span>Makan</span><b><?= rupiah($d['uang_makan']) ?></b></div>
                    <div class="flex justify-between"><span>Transport</span><b><?= rupiah($d['uang_transport']) ?></b></div>
                    <div class="flex justify-between"><span>Lembur</span><b><?= rupiah($d['uang_lembur']) ?></b></div>
                    <div class="flex justify-between border-t border-slate-800 mt-2 pt-2"><span>Total</span><b><?= rupiah($pendapatan) ?></b></div>
                </div>
                <div class="bg-slate-950 rounded-xl p-3">
                    <div class="label mb-2">Potongan</div>
                    <div class="flex justify-between"><span>Telat</span><b><?= rupiah($d['potongan_terlambat']) ?></b></div>
                    <div class="flex justify-between"><span>Pulang Cepat</span><b><?= rupiah($d['potongan_pulang_cepat']) ?></b></div>
                    <div class="flex justify-between"><span>Alpha</span><b><?= rupiah($d['potongan_alpha']) ?></b></div>
                    <div class="flex justify-between"><span>Lain</span><b><?= rupiah($d['potongan_lain']) ?></b></div>
                    <div class="flex justify-between border-t border-slate-800 mt-2 pt-2"><span>Total</span><b><?= rupiah($potongan) ?></b></div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 no-print">
                <a href="slip.php?id=<?= (int)$d['id'] ?>" target="_blank" class="text-center px-4 py-3 bg-slate-700 rounded-xl">Slip</a>
                <?php if ($d['status_bayar'] !== 'Sudah Dibayar'): ?>
                <a href="bayar.php?id=<?= (int)$d['id'] ?>" class="text-center px-4 py-3 bg-emerald-600 rounded-xl font-bold">Bayar</a>
                <?php else: ?>
                <span class="text-center px-4 py-3 bg-emerald-950 border border-emerald-700 rounded-xl">Sudah Dibayar</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (!$details): ?>
        <div class="card p-6 text-center text-slate-500">Tidak ada detail gaji.</div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
