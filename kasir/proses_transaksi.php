<?php
// kasir/proses_transaksi.php
// Versi perbaikan + modul piutang: harga dihitung server, stok aman, transaksi hutang masuk tabel piutang.

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Owner', 'Admin', 'Kasir'], true)) {
    http_response_code(403);
    die('Akses ilegal.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$tenant_id = (int) $_SESSION['tenant_id'];
$kasir_id  = (int) $_SESSION['user_id'];

$metode_bayar_valid = ['Tunai', 'QRIS', 'Transfer', 'Hutang'];
$metode_bayar = $_POST['metode_bayar'] ?? 'Tunai';
if (!in_array($metode_bayar, $metode_bayar_valid, true)) {
    die('Metode bayar tidak valid.');
}

$nominal_bayar = isset($_POST['nominal_bayar']) ? (float) $_POST['nominal_bayar'] : 0;
if ($nominal_bayar < 0) {
    die('Nominal bayar tidak valid.');
}

$pelanggan_id_post = isset($_POST['pelanggan_id']) && $_POST['pelanggan_id'] !== '' ? (int) $_POST['pelanggan_id'] : 0;
$pelanggan_nama = trim((string)($_POST['pelanggan_nama'] ?? ''));
$pelanggan_wa = trim((string)($_POST['pelanggan_wa'] ?? ''));
$jatuh_tempo = trim((string)($_POST['jatuh_tempo'] ?? ''));
$catatan_piutang = trim((string)($_POST['catatan_piutang'] ?? ''));

if ($jatuh_tempo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $jatuh_tempo)) {
    die('Format jatuh tempo tidak valid.');
}

$data_keranjang_raw = $_POST['data_keranjang'] ?? '';
$data_keranjang = json_decode($data_keranjang_raw, true);

if (!is_array($data_keranjang) || count($data_keranjang) === 0) {
    die('Error: Keranjang kosong atau format keranjang tidak valid.');
}

if (count($data_keranjang) > 200) {
    die('Jumlah item transaksi terlalu banyak.');
}

$nomor_invoice = 'INV-' . date('Ymd-His') . '-' . random_int(10, 99);

try {
    $pdo->beginTransaction();

    $stmt_barang = $pdo->prepare(" 
        SELECT
            id,
            nama_barang,
            harga_beli,
            harga_jual,
            harga_ecer,
            stok_gudang,
            satuan,
            satuan_ecer,
            isi_per_kemasan
        FROM barang
        WHERE id = ? AND tenant_id = ?
        FOR UPDATE
    ");

    $detail_items = [];
    $subtotal_transaksi = 0;

    foreach ($data_keranjang as $index => $item) {
        $barang_id = isset($item['id']) ? (int) $item['id'] : 0;
        $qty = isset($item['qty']) ? (int) $item['qty'] : 0;
        $tipe_satuan = $item['tipe_satuan'] ?? 'eceran';

        if ($barang_id <= 0) {
            throw new Exception('Barang pada baris #' . ($index + 1) . ' tidak valid.');
        }
        if ($qty <= 0) {
            throw new Exception('Qty barang pada baris #' . ($index + 1) . ' tidak valid.');
        }
        if (!in_array($tipe_satuan, ['kemasan', 'eceran'], true)) {
            throw new Exception('Tipe satuan barang pada baris #' . ($index + 1) . ' tidak valid.');
        }

        $stmt_barang->execute([$barang_id, $tenant_id]);
        $barang = $stmt_barang->fetch(PDO::FETCH_ASSOC);

        if (!$barang) {
            throw new Exception('Barang pada baris #' . ($index + 1) . ' tidak ditemukan atau bukan milik toko ini.');
        }

        $isi_per_kemasan = max(1, (int) ($barang['isi_per_kemasan'] ?? 1));
        $harga_jual = (float) $barang['harga_jual'];
        $harga_ecer = $barang['harga_ecer'] !== null ? (float) $barang['harga_ecer'] : 0;
        $harga_beli = (float) $barang['harga_beli'];
        $stok_gudang = (int) $barang['stok_gudang'];

        if ($tipe_satuan === 'kemasan' && $isi_per_kemasan > 1) {
            $harga_satuan = $harga_jual;
            $satuan_jual = $barang['satuan'] ?: 'Kemasan';
            $qty_konversi_stok = $qty * $isi_per_kemasan;
            $harga_modal_satuan = $harga_beli;
        } else {
            $tipe_satuan = 'eceran';
            $harga_satuan = $harga_ecer > 0 ? $harga_ecer : $harga_jual;
            $satuan_jual = $barang['satuan_ecer'] ?: ($barang['satuan'] ?: 'Pcs');
            $qty_konversi_stok = $qty;
            $harga_modal_satuan = $isi_per_kemasan > 1 ? ($harga_beli / $isi_per_kemasan) : $harga_beli;
        }

        if ($harga_satuan < 0) {
            throw new Exception('Harga barang ' . $barang['nama_barang'] . ' tidak valid.');
        }
        if ($qty_konversi_stok <= 0) {
            throw new Exception('Konversi stok barang ' . $barang['nama_barang'] . ' tidak valid.');
        }
        if ($stok_gudang < $qty_konversi_stok) {
            throw new Exception(
                'Stok tidak cukup untuk ' . $barang['nama_barang'] . '. ' .
                'Stok tersedia: ' . $stok_gudang . ', dibutuhkan: ' . $qty_konversi_stok . '.'
            );
        }

        $subtotal_item = $qty * $harga_satuan;
        $subtotal_transaksi += $subtotal_item;

        $detail_items[] = [
            'barang_id' => $barang_id,
            'qty' => $qty,
            'harga_satuan' => $harga_satuan,
            'subtotal' => $subtotal_item,
            'tipe_satuan' => $tipe_satuan,
            'satuan_jual' => $satuan_jual,
            'isi_per_kemasan' => $isi_per_kemasan,
            'qty_konversi_stok' => $qty_konversi_stok,
            'harga_modal_satuan' => $harga_modal_satuan,
        ];
    }

    $diskon = 0;
    $total = $subtotal_transaksi - $diskon;

    if ($metode_bayar !== 'Hutang' && $nominal_bayar < $total) {
        throw new Exception('Nominal bayar kurang dari total tagihan.');
    }

    if ($metode_bayar === 'Hutang' && $nominal_bayar >= $total) {
        throw new Exception('Nominal bayar untuk metode hutang harus lebih kecil dari total tagihan. Gunakan Tunai/QRIS/Transfer jika sudah lunas.');
    }

    $kembalian = $metode_bayar === 'Hutang' ? 0 : ($nominal_bayar - $total);
    $pelanggan_id = null;

    if ($metode_bayar === 'Hutang') {
        if ($pelanggan_id_post > 0) {
            $stmt_cek_pelanggan = $pdo->prepare("SELECT id FROM pelanggan WHERE id = ? AND tenant_id = ? AND status = 'Aktif'");
            $stmt_cek_pelanggan->execute([$pelanggan_id_post, $tenant_id]);
            $pelanggan = $stmt_cek_pelanggan->fetch(PDO::FETCH_ASSOC);
            if (!$pelanggan) {
                throw new Exception('Pelanggan piutang tidak ditemukan atau bukan milik toko ini.');
            }
            $pelanggan_id = (int) $pelanggan['id'];
        } else {
            if (strlen($pelanggan_nama) < 2) {
                throw new Exception('Nama pelanggan wajib diisi untuk transaksi hutang.');
            }
            $stmt_insert_pelanggan = $pdo->prepare(" 
                INSERT INTO pelanggan (tenant_id, nama_pelanggan, no_wa, status)
                VALUES (?, ?, ?, 'Aktif')
            ");
            $stmt_insert_pelanggan->execute([
                $tenant_id,
                $pelanggan_nama,
                $pelanggan_wa !== '' ? $pelanggan_wa : null,
            ]);
            $pelanggan_id = (int) $pdo->lastInsertId();
        }
    }

    $stmt_trx = $pdo->prepare(" 
        INSERT INTO transaksi
            (tenant_id, kasir_id, pelanggan_id, nomor_invoice, subtotal, diskon, total, metode_bayar, nominal_bayar, kembalian)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt_trx->execute([
        $tenant_id,
        $kasir_id,
        $pelanggan_id,
        $nomor_invoice,
        $subtotal_transaksi,
        $diskon,
        $total,
        $metode_bayar,
        $nominal_bayar,
        $kembalian,
    ]);

    $transaksi_id = (int) $pdo->lastInsertId();

    $stmt_detail = $pdo->prepare(" 
        INSERT INTO transaksi_detail
            (transaksi_id, barang_id, qty, harga_satuan, subtotal, tipe_satuan, satuan_jual, isi_per_kemasan, qty_konversi_stok, harga_modal_satuan)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt_stok = $pdo->prepare(" 
        UPDATE barang
        SET stok_gudang = stok_gudang - ?
        WHERE id = ? AND tenant_id = ? AND stok_gudang >= ?
    ");

    foreach ($detail_items as $d) {
        $stmt_detail->execute([
            $transaksi_id,
            $d['barang_id'],
            $d['qty'],
            $d['harga_satuan'],
            $d['subtotal'],
            $d['tipe_satuan'],
            $d['satuan_jual'],
            $d['isi_per_kemasan'],
            $d['qty_konversi_stok'],
            $d['harga_modal_satuan'],
        ]);

        $stmt_stok->execute([
            $d['qty_konversi_stok'],
            $d['barang_id'],
            $tenant_id,
            $d['qty_konversi_stok'],
        ]);

        if ($stmt_stok->rowCount() !== 1) {
            throw new Exception('Gagal mengurangi stok barang ID ' . $d['barang_id'] . '.');
        }
    }

    if ($metode_bayar === 'Hutang') {
        $sisa_piutang = $total - $nominal_bayar;
        $stmt_piutang = $pdo->prepare(" 
            INSERT INTO piutang
                (tenant_id, pelanggan_id, transaksi_id, nomor_invoice, tanggal, jumlah_piutang, total_dibayar, sisa_piutang, status, jatuh_tempo, catatan)
            VALUES
                (?, ?, ?, ?, NOW(), ?, ?, ?, 'Belum Lunas', ?, ?)
        ");
        $stmt_piutang->execute([
            $tenant_id,
            $pelanggan_id,
            $transaksi_id,
            $nomor_invoice,
            $total,
            $nominal_bayar,
            $sisa_piutang,
            $jatuh_tempo !== '' ? $jatuh_tempo : null,
            $catatan_piutang !== '' ? $catatan_piutang : null,
        ]);
    }

    $pdo->commit();

    header('Location: cetak_struk.php?id=' . $transaksi_id);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    echo '<h3>Gagal memproses transaksi</h3>';
    echo '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="index.php">Kembali ke kasir</a></p>';
    exit;
}
