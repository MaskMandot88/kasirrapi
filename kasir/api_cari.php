<?php
// kasir/api_cari.php
session_start();

require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (
    !isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role']) ||
    !in_array($_SESSION['role'], ['Owner', 'Admin', 'Kasir'], true)
) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'pesan' => 'Sesi tidak valid'
    ]);
    exit;
}

$tenant_id = (int) $_SESSION['tenant_id'];
$keyword = trim($_GET['keyword'] ?? '');

if ($tenant_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'pesan' => 'Tenant tidak valid'
    ]);
    exit;
}

if ($keyword === '') {
    echo json_encode([
        'status' => 'error',
        'pesan' => 'Keyword kosong'
    ]);
    exit;
}

function column_exists_kasir_api($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

$exact = $keyword;
$like = '%' . $keyword . '%';

try {
    $has_is_aktif = column_exists_kasir_api($pdo, 'barang', 'is_aktif');
    $has_status_barang = column_exists_kasir_api($pdo, 'barang', 'status_barang');

    $filterAktif = '';

    if ($has_is_aktif) {
        $filterAktif .= " AND COALESCE(is_aktif, 1) = 1 ";
    }

    if ($has_status_barang) {
        $filterAktif .= " AND (status_barang IS NULL OR status_barang = 'Aktif') ";
    }

    $sql = "
        SELECT
            id,
            kode_barang,
            barcode,
            barcode_eceran,
            nama_barang,
            kategori,
            harga_beli,
            harga_jual,
            harga_ecer,
            stok_gudang,
            satuan,
            isi_per_kemasan,
            satuan_ecer
        FROM barang
        WHERE tenant_id = ?
          $filterAktif
          AND (
                kode_barang = ?
                OR barcode = ?
                OR barcode_eceran = ?
                OR nama_barang LIKE ?
                OR kode_barang LIKE ?
                OR barcode LIKE ?
                OR barcode_eceran LIKE ?
          )
        ORDER BY
            CASE
                WHEN barcode = ? THEN 1
                WHEN barcode_eceran = ? THEN 2
                WHEN kode_barang = ? THEN 3
                WHEN nama_barang LIKE ? THEN 4
                ELSE 5
            END,
            nama_barang ASC
        LIMIT 20
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        $tenant_id,

        $exact,
        $exact,
        $exact,
        $like,
        $like,
        $like,
        $like,

        $exact,
        $exact,
        $exact,
        $like
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo json_encode([
            'status' => 'error',
            'pesan' => 'Barang tidak ditemukan'
        ]);
        exit;
    }

    $normalized = [];

    foreach ($rows as $row) {
        $barcode = trim((string)($row['barcode'] ?? ''));
        $barcodeEcer = trim((string)($row['barcode_eceran'] ?? ''));

        $isi = max(1, (int)($row['isi_per_kemasan'] ?? 1));
        $stokGudang = (int)($row['stok_gudang'] ?? 0);

        $hargaJual = (float)($row['harga_jual'] ?? 0);
        $hargaEcer = null;

        if ($row['harga_ecer'] !== null && $row['harga_ecer'] !== '') {
            $hargaEcer = (float)$row['harga_ecer'];
        }

        $satuanUtama = trim((string)($row['satuan'] ?? ''));
        if ($satuanUtama === '') {
            $satuanUtama = 'Pcs';
        }

        $satuanEcer = trim((string)($row['satuan_ecer'] ?? ''));
        if ($satuanEcer === '') {
            $satuanEcer = 'Pcs';
        }

        $stokMaksimalUtama = $isi > 1 ? (int)floor($stokGudang / $isi) : $stokGudang;
        $stokMaksimalEcer = $stokGudang;

        $row['id'] = (int)$row['id'];
        $row['harga_beli'] = (float)($row['harga_beli'] ?? 0);
        $row['harga_jual'] = $hargaJual;
        $row['harga_ecer'] = $hargaEcer;
        $row['stok_gudang'] = $stokGudang;
        $row['isi_per_kemasan'] = $isi;
        $row['satuan'] = $satuanUtama;
        $row['satuan_ecer'] = $satuanEcer;

        $row['stok_maksimal_utama'] = $stokMaksimalUtama;
        $row['stok_maksimal_ecer'] = $stokMaksimalEcer;

        /*
         * Aturan mode jual:
         *
         * 1. Jika scan tepat barcode_eceran dan barcode ecer berbeda dari barcode utama:
         *    langsung mode eceran.
         *
         * 2. Jika barcode utama dan barcode ecer sama:
         *    mode pilihan, nanti kasir memilih jual utama atau ecer.
         *
         * 3. Selain itu:
         *    mode utama.
         */

        if (
            $barcodeEcer !== '' &&
            $exact === $barcodeEcer &&
            $barcodeEcer !== $barcode &&
            $hargaEcer !== null
        ) {
            $row['harga'] = $hargaEcer;
            $row['satuan'] = $satuanEcer;
            $row['isi_per_kemasan'] = 1;
            $row['stok_maksimal'] = $stokMaksimalEcer;
            $row['mode_jual'] = 'eceran';

        } elseif (
            $barcodeEcer !== '' &&
            $barcodeEcer === $barcode &&
            $isi > 1 &&
            $hargaEcer !== null
        ) {
            $row['harga'] = $hargaJual;
            $row['satuan'] = $satuanUtama;
            $row['stok_maksimal'] = $stokMaksimalUtama;
            $row['mode_jual'] = 'pilihan';

        } else {
            $row['harga'] = $hargaJual;
            $row['satuan'] = $satuanUtama;
            $row['stok_maksimal'] = $stokMaksimalUtama;
            $row['mode_jual'] = 'utama';
        }

        $normalized[] = $row;
    }

    if (count($normalized) === 1) {
        echo json_encode([
            'status' => 'sukses',
            'data' => $normalized[0]
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'pilihan',
        'data' => $normalized
    ]);
    exit;

} catch (Throwable $e) {
    error_log('Gagal kasir api_cari: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'pesan' => 'Gagal mencari barang'
    ]);
    exit;
}
