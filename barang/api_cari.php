<?php
// barang/api_cari.php
session_start();

require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Session tidak valid. Silakan login ulang.',
        'data' => []
    ]);
    exit;
}

$tenant_id = (int) $_SESSION['tenant_id'];
$keyword = trim($_GET['keyword'] ?? '');

if ($tenant_id <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Tenant tidak valid.',
        'data' => []
    ]);
    exit;
}

if ($keyword === '' || mb_strlen($keyword) < 2) {
    echo json_encode([
        'status' => 'kosong',
        'message' => 'Keyword terlalu pendek.',
        'data' => []
    ]);
    exit;
}

function column_exists_api($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

try {
    $has_is_aktif = column_exists_api($pdo, 'barang', 'is_aktif');

    $whereAktif = '';
    if ($has_is_aktif) {
        $whereAktif = " AND COALESCE(is_aktif, 1) = 1 ";
    }

    $like = '%' . $keyword . '%';

    $sql = "
        SELECT
            id,
            tenant_id,
            supplier_id,
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
            satuan_ecer,
            foto_barang
        FROM barang
        WHERE tenant_id = ?
          $whereAktif
          AND (
                nama_barang LIKE ?
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
        $like,
        $like,
        $like,
        $like,
        $keyword,
        $keyword,
        $keyword,
        $like
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];

    foreach ($rows as $row) {
        $data[] = [
            'id' => (int)($row['id'] ?? 0),
            'tenant_id' => (int)($row['tenant_id'] ?? 0),
            'supplier_id' => $row['supplier_id'] !== null ? (int)$row['supplier_id'] : null,

            'kode_barang' => $row['kode_barang'] ?? '',
            'barcode' => $row['barcode'] ?? '',
            'barcode_eceran' => $row['barcode_eceran'] ?? '',

            'nama_barang' => $row['nama_barang'] ?? '',
            'kategori' => $row['kategori'] ?? '',

            'harga_beli' => (float)($row['harga_beli'] ?? 0),
            'harga_jual' => (float)($row['harga_jual'] ?? 0),
            'harga_ecer' => $row['harga_ecer'] !== null ? (float)$row['harga_ecer'] : null,

            'stok_gudang' => (int)($row['stok_gudang'] ?? 0),

            'satuan' => $row['satuan'] ?? '',
            'isi_per_kemasan' => max(1, (int)($row['isi_per_kemasan'] ?? 1)),
            'satuan_ecer' => $row['satuan_ecer'] ?? '',

            'foto_barang' => $row['foto_barang'] ?? ''
        ];
    }

    if (count($data) === 1) {
        echo json_encode([
            'status' => 'sukses',
            'message' => 'Barang ditemukan.',
            'data' => $data[0]
        ]);
        exit;
    }

    if (count($data) > 1) {
        echo json_encode([
            'status' => 'pilihan',
            'message' => 'Beberapa barang ditemukan.',
            'data' => $data
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'kosong',
        'message' => 'Barang tidak ditemukan.',
        'data' => []
    ]);
    exit;

} catch (Throwable $e) {
    error_log('Gagal api_cari barang: ' . $e->getMessage());

    echo json_encode([
        'status' => 'error',
        'message' => 'Gagal mencari barang.',
        'data' => []
    ]);
    exit;
}
