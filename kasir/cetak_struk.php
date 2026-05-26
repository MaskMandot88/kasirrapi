<?php
// kasir/cetak_struk.php
// Versi perbaikan: menampilkan satuan jual grosir/eceran dari transaksi_detail.

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['tenant_id'])) {
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

$stmt_toko = $pdo->prepare('SELECT nama_toko FROM tenants WHERE id = ?');
$stmt_toko->execute([$tenant_id]);
$toko = $stmt_toko->fetch(PDO::FETCH_ASSOC);
$nama_toko = $toko ? $toko['nama_toko'] : 'Toko Retail';

$stmt_trx = $pdo->prepare(" 
    SELECT
        t.*,
        u.nama AS nama_kasir,
        pl.nama_pelanggan,
        p.sisa_piutang
    FROM transaksi t
    INNER JOIN users u ON t.kasir_id = u.id
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
    INNER JOIN barang b ON d.barang_id = b.id
    WHERE d.transaksi_id = ?
    ORDER BY d.id ASC
");
$stmt_detail->execute([$transaksi_id]);
$details = $stmt_detail->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Struk - <?= e($trx['nomor_invoice']) ?></title>
    <style>
        @page { margin: 0; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 10px;
            width: 58mm;
        }
        .header { text-align: center; margin-bottom: 10px; }
        .header h2 { margin: 0; font-size: 16px; font-weight: bold; text-transform: uppercase; }
        .header p { margin: 2px 0; font-size: 11px; }
        .garis { border-top: 1px dashed #000; margin: 8px 0; }
        .info-trx { font-size: 11px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        td { vertical-align: top; padding: 2px 0; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-area { margin-top: 5px; font-weight: bold; }
        .footer { text-align: center; margin-top: 15px; font-size: 10px; }
        .muted { font-size: 10px; color: #333; }
        @media print {
            .no-print { display: none; }
            body { width: 100%; padding: 0; }
        }
        .btn-kembali {
            display: block; width: 100%; padding: 10px; background: #10b981; color: white;
            text-align: center; text-decoration: none; font-family: Arial, sans-serif;
            font-weight: bold; border-radius: 5px; margin-top: 20px; font-size: 14px;
        }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <h2><?= e($nama_toko) ?></h2>
        <p>Struk Pembelian Asli</p>
    </div>

    <div class="garis"></div>

    <div class="info-trx">
        <table>
            <tr><td>No</td><td>: <?= e($trx['nomor_invoice']) ?></td></tr>
            <tr><td>Tgl</td><td>: <?= e(date('d M Y H:i', strtotime($trx['tanggal']))) ?></td></tr>
            <tr><td>Kasir</td><td>: <?= e($trx['nama_kasir']) ?></td></tr>
            <?php if (!empty($trx['nama_pelanggan'])): ?>
            <tr><td>Pelanggan</td><td>: <?= e($trx['nama_pelanggan']) ?></td></tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="garis"></div>

    <table>
        <?php foreach ($details as $d): ?>
            <?php
                $satuan_jual = $d['satuan_jual'] ?: ($d['tipe_satuan'] === 'kemasan' ? ($d['satuan'] ?: 'Kemasan') : ($d['satuan_ecer'] ?: ($d['satuan'] ?: 'Pcs')));
            ?>
            <tr>
                <td colspan="3"><?= e($d['nama_barang']) ?></td>
            </tr>
            <tr>
                <td style="padding-bottom: 0;">
                    <?= rupiah_struk($d['qty']) ?> <?= e($satuan_jual) ?> x <?= rupiah_struk($d['harga_satuan']) ?>
                </td>
                <td style="padding-bottom: 0;"></td>
                <td class="text-right" style="padding-bottom: 0;"><?= rupiah_struk($d['subtotal']) ?></td>
            </tr>

        <?php endforeach; ?>
    </table>

    <div class="garis"></div>

    <table class="total-area">
        <tr>
            <td>SUBTOTAL</td>
            <td class="text-right">Rp <?= rupiah_struk($trx['subtotal']) ?></td>
        </tr>
        <?php if ((float)$trx['diskon'] > 0): ?>
        <tr>
            <td>DISKON</td>
            <td class="text-right">Rp <?= rupiah_struk($trx['diskon']) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td>TOTAL</td>
            <td class="text-right">Rp <?= rupiah_struk($trx['total']) ?></td>
        </tr>
        <tr>
            <td style="font-weight: normal;">Bayar (<?= e($trx['metode_bayar']) ?>)</td>
            <td class="text-right" style="font-weight: normal;">Rp <?= rupiah_struk($trx['nominal_bayar']) ?></td>
        </tr>
        <?php if ($trx['metode_bayar'] === 'Hutang'): ?>
        <tr>
            <td>SISA HUTANG</td>
            <td class="text-right">Rp <?= rupiah_struk($trx['sisa_piutang'] ?? max(((float)$trx['total'] - (float)$trx['nominal_bayar']), 0)) ?></td>
        </tr>
        <?php else: ?>
        <tr>
            <td>KEMBALI</td>
            <td class="text-right">Rp <?= rupiah_struk($trx['kembalian']) ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <div class="garis"></div>

    <div class="footer">
        <p>*** TERIMA KASIH ***</p>
        <p>Barang yang sudah dibeli<br>tidak dapat ditukar/dikembalikan.</p>
        <p>Powered by POS SaaS</p>
    </div>

    <a href="index.php" class="btn-kembali no-print">Kembali ke Kasir</a>

</body>
</html>
