<?php
require_once '_auth.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT pd.*, pp.nama_periode, pp.periode_mulai, pp.periode_selesai, pp.status AS status_periode,
                              u.nama, u.role
                       FROM payroll_detail pd
                       JOIN payroll_periode pp ON pp.id = pd.payroll_id AND pp.tenant_id = pd.tenant_id
                       JOIN users u ON u.id = pd.user_id
                       WHERE pd.tenant_id = ? AND pd.id = ? LIMIT 1");
$stmt->execute([$tenant_id, $id]);
$d = $stmt->fetch();
if (!$d) die('Detail gaji tidak ditemukan.');
if ($d['status_bayar'] === 'Sudah Dibayar') redirect_with('detail.php?id=' . (int)$d['payroll_id'], 'error', 'Gaji ini sudah dibayar.');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bayar Gaji</title>
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
            <h1 class="text-2xl md:text-3xl font-bold text-emerald-400">Bayar Gaji</h1>
            <p class="text-slate-400"><?= h($d['nama_periode']) ?></p>
        </div>
        <a href="detail.php?id=<?= (int)$d['payroll_id'] ?>" class="text-center px-4 py-3 bg-slate-800 rounded-xl">← Detail</a>
    </div>

    <div class="card p-5 md:p-6 mb-4">
        <div class="label">Karyawan</div>
        <div class="text-2xl font-bold"><?= h($d['nama']) ?></div>
        <div class="text-slate-500"><?= h($d['role']) ?></div>
        <div class="mt-4 label">Total Gaji</div>
        <div class="text-3xl font-extrabold text-emerald-300"><?= rupiah($d['total_gaji']) ?></div>
    </div>

    <form method="POST" action="proses_bayar.php" class="card p-5 md:p-6 space-y-4">
        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
        <label class="block">
            <span class="label">Metode Bayar</span>
            <select name="metode_bayar" required class="field mt-1">
                <option value="Tunai">Tunai</option>
                <option value="Transfer">Transfer</option>
                <option value="QRIS">QRIS</option>
            </select>
        </label>
        <label class="block">
            <span class="label">Tanggal Bayar</span>
            <input type="datetime-local" name="tanggal_bayar" value="<?= date('Y-m-d\TH:i') ?>" required class="field mt-1">
        </label>
        <label class="block">
            <span class="label">Catatan</span>
            <textarea name="catatan" rows="3" class="field mt-1"></textarea>
        </label>
        <button class="w-full py-3 bg-emerald-600 rounded-xl font-bold" onclick="return confirm('Tandai gaji ini sudah dibayar?')">Simpan Pembayaran</button>
    </form>
</div>
</body>
</html>
