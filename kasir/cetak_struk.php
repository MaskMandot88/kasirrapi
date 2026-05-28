<?php
// kasir/cetak_struk.php
// Struk thermal dengan branding sesuai paket langganan.

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once '../config/app.php';
require_once '../config/database.php';
require_once '../includes/plans.php';
require_once '../includes/receipt.php';

if (
    !isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role']) ||
    !in_array($_SESSION['role'], ['Owner', 'Admin', 'Kasir'], true)
) {
    die('Akses ilegal.');
}

$tenant_id = (int) $_SESSION['tenant_id'];
$transaksi_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($transaksi_id <= 0) {
    die('ID transaksi tidak valid.');
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function rupiah_struk($angka) {
    return number_format((float) $angka, 0, ',', '.');
}

function struk_tanggal($value) {
    return $value ? date('d/m/Y H:i', strtotime($value)) : '-';
}

receipt_ensure_settings_schema($pdo);

$subscription = kasirrapi_tenant_subscription($pdo, $tenant_id);
$planName = $subscription['plan'] ?? 'Gratis';
$customStrukAllowed = kasirrapi_feature_allowed($subscription, 'custom_struk');

$stmt_toko = $pdo->prepare("
    SELECT nama_toko, nama_pemilik, email, no_wa, alamat_toko, logo_struk, catatan_struk
    FROM tenants
    WHERE id = ?
    LIMIT 1
");
$stmt_toko->execute([$tenant_id]);
$toko = $stmt_toko->fetch(PDO::FETCH_ASSOC) ?: [];

$nama_toko = $toko['nama_toko'] ?? 'Toko Retail';
$alamat_toko = trim((string)($toko['alamat_toko'] ?? ''));
$no_wa = trim((string)($toko['no_wa'] ?? ''));
$catatan_struk = trim((string)($toko['catatan_struk'] ?? ''));
$logo_struk = trim((string)($toko['logo_struk'] ?? ''));
$logo_url = '';

if ($customStrukAllowed && $logo_struk !== '') {
    $logo_path = receipt_logo_path($tenant_id, $logo_struk);
    if ($logo_path !== '' && is_file($logo_path)) {
        $logo_url = receipt_logo_url($tenant_id, $logo_struk);
    }
}

$stmt_trx = $pdo->prepare("
    SELECT
        t.*,
        u.nama AS nama_kasir,
        pl.nama_pelanggan,
        pl.no_wa AS no_wa_pelanggan,
        p.sisa_piutang
    FROM transaksi t
    INNER JOIN users u ON t.kasir_id = u.id AND u.tenant_id = t.tenant_id
    LEFT JOIN pelanggan pl ON t.pelanggan_id = pl.id
    LEFT JOIN piutang p ON p.transaksi_id = t.id
    WHERE t.id = ? AND t.tenant_id = ?
");
$stmt_trx->execute([$transaksi_id, $tenant_id]);
$trx = $stmt_trx->fetch(PDO::FETCH_ASSOC);

if (!$trx) {
    die('Struk tidak ditemukan atau bukan milik toko ini.');
}

$stmt_detail = $pdo->prepare("
    SELECT
        d.qty,
        d.harga_satuan,
        d.subtotal,
        d.tipe_satuan,
        d.satuan_jual,
        d.isi_per_kemasan,
        d.qty_konversi_stok,
        b.nama_barang,
        b.satuan,
        b.satuan_ecer
    FROM transaksi_detail d
    INNER JOIN barang b ON d.barang_id = b.id AND b.tenant_id = d.tenant_id
    WHERE d.transaksi_id = ? AND d.tenant_id = ?
    ORDER BY d.id ASC
");
$stmt_detail->execute([$transaksi_id, $tenant_id]);
$details = $stmt_detail->fetchAll(PDO::FETCH_ASSOC);

$isHutang = $trx['metode_bayar'] === 'Hutang';
$sisaHutang = max((float)($trx['sisa_piutang'] ?? ((float)$trx['total'] - (float)$trx['nominal_bayar'])), 0);
$statusBayar = $isHutang ? ($sisaHutang > 0 ? 'HUTANG' : 'LUNAS') : 'LUNAS';
$footerNote = $customStrukAllowed && $catatan_struk !== ''
    ? $catatan_struk
    : 'Barang yang sudah dibeli tidak dapat ditukar/dikembalikan.';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Struk - <?= e($trx['nomor_invoice']) ?></title>
    <style>
        @page{size:58mm auto;margin:0}
        *{box-sizing:border-box}
        body{margin:0;background:#f1f5f9;color:#000;font-family:Arial,sans-serif}
        .receipt{width:58mm;min-height:100vh;margin:0 auto;background:#fff;padding:9px 8px;font-family:"Courier New",Courier,monospace;font-size:11px;line-height:1.25}
        .screen-actions{position:fixed;right:16px;top:16px;display:flex;gap:8px;font-family:Arial,sans-serif}
        .screen-actions a,.screen-actions button{border:0;border-radius:10px;padding:10px 13px;background:#0f172a;color:#fff;text-decoration:none;font-weight:800;cursor:pointer}
        .screen-actions button{background:#ff6a00}
        .brand{text-align:center;margin-bottom:7px}
        .logo{max-width:34mm;max-height:18mm;object-fit:contain;margin:0 auto 4px;display:block}
        .store-name{font-size:15px;font-weight:900;text-transform:uppercase;line-height:1.12;word-break:break-word}
        .store-meta{font-size:10px;margin-top:2px;word-break:break-word}
        .line{border-top:1px dashed #000;margin:7px 0}
        table{width:100%;border-collapse:collapse}
        td{vertical-align:top;padding:1px 0}
        .label{width:17mm}
        .right{text-align:right}
        .center{text-align:center}
        .bold{font-weight:900}
        .muted{font-size:10px}
        .item-name{font-weight:900;word-break:break-word;padding-top:2px}
        .item-meta{font-size:10px;color:#111}
        .total td{font-weight:900}
        .grand td{font-size:13px;font-weight:900;padding-top:3px}
        .status{display:inline-block;border:1px solid #000;padding:2px 6px;margin-top:4px;font-weight:900}
        .footer{text-align:center;font-size:10px;margin-top:8px;word-break:break-word}
        .powered{font-size:9px;margin-top:5px}
        @media print{
            body{background:#fff}
            .screen-actions{display:none}
            .receipt{width:58mm;margin:0;padding:7px 6px;min-height:0}
        }
    </style>
</head>
<body onload="window.print()">
    <div class="screen-actions">
        <a href="<?= e(app_url('kasir/index.php')) ?>">Kembali</a>
        <button type="button" onclick="window.print()">Cetak Lagi</button>
    </div>

    <main class="receipt">
        <header class="brand">
            <?php if ($logo_url !== ''): ?>
                <img src="<?= e($logo_url) ?>" alt="<?= e($nama_toko) ?>" class="logo">
            <?php endif; ?>
            <div class="store-name"><?= e($nama_toko) ?></div>
            <?php if ($alamat_toko !== ''): ?><div class="store-meta"><?= e($alamat_toko) ?></div><?php endif; ?>
            <?php if ($no_wa !== ''): ?><div class="store-meta">WA: <?= e($no_wa) ?></div><?php endif; ?>
        </header>

        <div class="line"></div>

        <table>
            <tr><td class="label">No</td><td>: <?= e($trx['nomor_invoice']) ?></td></tr>
            <tr><td class="label">Tgl</td><td>: <?= e(struk_tanggal($trx['tanggal'])) ?></td></tr>
            <tr><td class="label">Kasir</td><td>: <?= e($trx['nama_kasir']) ?></td></tr>
            <?php if (!empty($trx['nama_pelanggan'])): ?>
                <tr><td class="label">Pelanggan</td><td>: <?= e($trx['nama_pelanggan']) ?></td></tr>
            <?php endif; ?>
            <tr><td class="label">Status</td><td>: <?= e($statusBayar) ?></td></tr>
        </table>

        <div class="line"></div>

        <table>
            <?php foreach ($details as $d): ?>
                <?php
                    $satuan_jual = $d['satuan_jual'] ?: ($d['tipe_satuan'] === 'kemasan' ? ($d['satuan'] ?: 'Kemasan') : ($d['satuan_ecer'] ?: ($d['satuan'] ?: 'Pcs')));
                ?>
                <tr>
                    <td colspan="2" class="item-name"><?= e($d['nama_barang']) ?></td>
                </tr>
                <tr>
                    <td class="item-meta">
                        <?= rupiah_struk($d['qty']) ?> <?= e($satuan_jual) ?> x <?= rupiah_struk($d['harga_satuan']) ?>
                    </td>
                    <td class="right"><?= rupiah_struk($d['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$details): ?>
                <tr><td colspan="2" class="center muted">Tidak ada item.</td></tr>
            <?php endif; ?>
        </table>

        <div class="line"></div>

        <table>
            <tr>
                <td>Subtotal</td>
                <td class="right">Rp <?= rupiah_struk($trx['subtotal']) ?></td>
            </tr>
            <?php if ((float)$trx['diskon'] > 0): ?>
                <tr>
                    <td>Diskon</td>
                    <td class="right">- Rp <?= rupiah_struk($trx['diskon']) ?></td>
                </tr>
            <?php endif; ?>
            <tr class="grand">
                <td>Total</td>
                <td class="right">Rp <?= rupiah_struk($trx['total']) ?></td>
            </tr>
            <tr>
                <td>Bayar <?= e($trx['metode_bayar']) ?></td>
                <td class="right">Rp <?= rupiah_struk($trx['nominal_bayar']) ?></td>
            </tr>
            <?php if ($isHutang): ?>
                <tr class="total">
                    <td>Sisa Hutang</td>
                    <td class="right">Rp <?= rupiah_struk($sisaHutang) ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td>Kembali</td>
                    <td class="right">Rp <?= rupiah_struk($trx['kembalian']) ?></td>
                </tr>
            <?php endif; ?>
        </table>

        <div class="center"><span class="status"><?= e($statusBayar) ?></span></div>
        <div class="line"></div>

        <footer class="footer">
            <div>*** TERIMA KASIH ***</div>
            <div><?= e($footerNote) ?></div>
            <?php if (!$customStrukAllowed): ?>
                <div class="powered">Powered by <?= e(APP_NAME) ?> - Paket <?= e($planName) ?></div>
                <div class="powered">Upgrade Plus/Pro untuk logo dan catatan struk khusus.</div>
            <?php else: ?>
                <div class="powered"><?= e(APP_NAME) ?></div>
            <?php endif; ?>
        </footer>
    </main>
</body>
</html>
