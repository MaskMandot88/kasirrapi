<?php
require_once '_auth.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT pd.*, pp.nama_periode, pp.periode_mulai, pp.periode_selesai,
                              u.nama, u.role, u.email, t.nama_toko
                       FROM payroll_detail pd
                       JOIN payroll_periode pp ON pp.id = pd.payroll_id AND pp.tenant_id = pd.tenant_id
                       JOIN users u ON u.id = pd.user_id
                       JOIN tenants t ON t.id = pd.tenant_id
                       WHERE pd.tenant_id = ? AND pd.id = ? LIMIT 1");
$stmt->execute([$tenant_id, $id]);
$d = $stmt->fetch();
if (!$d) die('Slip tidak ditemukan.');

$pendapatan = $d['gaji_pokok'] + $d['uang_makan'] + $d['uang_transport'] + $d['uang_lembur'] + $d['bonus'];
$potongan = $d['potongan_terlambat'] + $d['potongan_pulang_cepat'] + $d['potongan_alpha'] + $d['potongan_lain'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print { .no-print { display:none !important; } body { background:white !important; } }
    </style>
</head>
<body class="bg-slate-100 text-slate-900">
<div class="max-w-3xl mx-auto p-6">
    <div class="no-print mb-4 flex justify-between">
        <button onclick="window.print()" class="px-4 py-2 bg-slate-800 text-white rounded">Print / PDF</button>
        <a href="detail.php?id=<?= (int)$d['payroll_id'] ?>" class="px-4 py-2 bg-slate-300 rounded">Kembali</a>
    </div>

    <div class="bg-white border border-slate-300 rounded-2xl p-8">
        <div class="text-center border-b pb-5 mb-5">
            <h1 class="text-2xl font-bold"><?= h($d['nama_toko']) ?></h1>
            <div class="text-slate-500">Slip Gaji Karyawan</div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
            <div>
                <div class="text-slate-500">Nama</div>
                <div class="font-bold"><?= h($d['nama']) ?></div>
            </div>
            <div>
                <div class="text-slate-500">Role</div>
                <div class="font-bold"><?= h($d['role']) ?></div>
            </div>
            <div>
                <div class="text-slate-500">Periode</div>
                <div class="font-bold"><?= h($d['nama_periode']) ?></div>
            </div>
            <div>
                <div class="text-slate-500">Tanggal</div>
                <div class="font-bold"><?= tanggal_id($d['periode_mulai']) ?> - <?= tanggal_id($d['periode_selesai']) ?></div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-6">
            <div>
                <h2 class="font-bold mb-2">Pendapatan</h2>
                <table class="w-full text-sm">
                    <tr><td>Gaji Pokok</td><td class="text-right"><?= rupiah($d['gaji_pokok']) ?></td></tr>
                    <tr><td>Uang Makan</td><td class="text-right"><?= rupiah($d['uang_makan']) ?></td></tr>
                    <tr><td>Uang Transport</td><td class="text-right"><?= rupiah($d['uang_transport']) ?></td></tr>
                    <tr><td>Uang Lembur</td><td class="text-right"><?= rupiah($d['uang_lembur']) ?></td></tr>
                    <tr><td>Bonus</td><td class="text-right"><?= rupiah($d['bonus']) ?></td></tr>
                    <tr class="border-t font-bold"><td>Total Pendapatan</td><td class="text-right"><?= rupiah($pendapatan) ?></td></tr>
                </table>
            </div>
            <div>
                <h2 class="font-bold mb-2">Potongan</h2>
                <table class="w-full text-sm">
                    <tr><td>Terlambat</td><td class="text-right"><?= rupiah($d['potongan_terlambat']) ?></td></tr>
                    <tr><td>Pulang Cepat</td><td class="text-right"><?= rupiah($d['potongan_pulang_cepat']) ?></td></tr>
                    <tr><td>Alpha</td><td class="text-right"><?= rupiah($d['potongan_alpha']) ?></td></tr>
                    <tr><td>Potongan Lain</td><td class="text-right"><?= rupiah($d['potongan_lain']) ?></td></tr>
                    <tr class="border-t font-bold"><td>Total Potongan</td><td class="text-right"><?= rupiah($potongan) ?></td></tr>
                </table>
            </div>
        </div>

        <div class="mb-6 p-4 bg-slate-100 rounded-xl">
            <div class="grid grid-cols-3 gap-4 text-sm">
                <div>Hadir: <b><?= (int)$d['hari_hadir'] ?></b></div>
                <div>Izin/Cuti/Sakit: <b><?= (int)$d['hari_izin'] ?>/<?= (int)$d['hari_cuti'] ?>/<?= (int)$d['hari_sakit'] ?></b></div>
                <div>Alpha: <b><?= (int)$d['hari_alpha'] ?></b></div>
                <div>Telat: <b><?= (int)$d['total_menit_terlambat'] ?> menit</b></div>
                <div>Pulang Cepat: <b><?= (int)$d['total_menit_pulang_cepat'] ?> menit</b></div>
                <div>Lembur: <b><?= h($d['total_jam_lembur']) ?> jam</b></div>
            </div>
        </div>

        <div class="flex justify-between items-center border-t pt-5">
            <div>
                <div class="text-slate-500 text-sm">Status Bayar</div>
                <div class="font-bold"><?= h($d['status_bayar']) ?></div>
            </div>
            <div class="text-right">
                <div class="text-slate-500 text-sm">Gaji Bersih</div>
                <div class="text-3xl font-extrabold"><?= rupiah($d['total_gaji']) ?></div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-12 mt-12 text-center text-sm">
            <div>
                <div>HRD/Owner</div>
                <div class="mt-16 border-t pt-2">Tanda Tangan</div>
            </div>
            <div>
                <div>Karyawan</div>
                <div class="mt-16 border-t pt-2"><?= h($d['nama']) ?></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
