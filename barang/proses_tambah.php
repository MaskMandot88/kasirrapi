<?php
// barang/proses_tambah.php
session_start();

require_once '../config/database.php';
require_once '../includes/plans.php';

if (
    !isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role']) ||
    !in_array($_SESSION['role'], ['Owner', 'Admin', 'Gudang'], true)
) {
    die("Akses ditolak.");
}

$tenant_id = (int) $_SESSION['tenant_id'];
$user_id = (int) $_SESSION['user_id'];
$subscription = kasirrapi_tenant_subscription($pdo, $tenant_id);

function redirect_error($message) {
    $_SESSION['flash']['error'] = $message;
    header('Location: tambah.php');
    exit;
}

function clean_str($value, $max = 255) {
    $value = trim((string)$value);

    if (function_exists('mb_strlen') && mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    } elseif (strlen($value) > $max) {
        $value = substr($value, 0, $max);
    }

    return $value;
}

function upload_image($field, $tenant_id, $subdir) {
    if (empty($_FILES[$field]['name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload file gagal.');
    }

    if ($_FILES[$field]['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Ukuran file maksimal 2MB.');
    }

    $tmp = $_FILES[$field]['tmp_name'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $tmp) : '';

    if ($finfo) {
        finfo_close($finfo);
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Format gambar harus JPG, PNG, atau WEBP.');
    }

    $dir = "../public/uploads/tenant_{$tenant_id}/{$subdir}";

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = $field . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = $dir . '/' . $filename;

    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Gagal menyimpan gambar.');
    }

    return $filename;
}

function ensure_harga_riwayat_table($pdo) {
    /*
     * Penting:
     * Fungsi ini HARUS dipanggil SEBELUM beginTransaction().
     * CREATE TABLE di MySQL bisa menyebabkan implicit commit.
     */
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS barang_harga_riwayat (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            barang_id INT NOT NULL,
            user_id INT NULL,
            jenis_harga ENUM('harga_beli','harga_jual','harga_ecer') NOT NULL,
            harga_lama DECIMAL(15,2) NOT NULL DEFAULT 0,
            harga_baru DECIMAL(15,2) NOT NULL DEFAULT 0,
            mulai_berlaku DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            keterangan VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tenant_barang (tenant_id, barang_id),
            INDEX idx_barang_jenis (barang_id, jenis_harga)
        )
    ");
}

function harga_berubah($lama, $baru) {
    return abs((float)$lama - (float)$baru) > 0.0001;
}

function catat_riwayat_harga($pdo, $tenant_id, $barang_id, $user_id, $jenis, $harga_lama, $harga_baru, $keterangan = null) {
    if (!harga_berubah($harga_lama, $harga_baru)) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO barang_harga_riwayat
            (tenant_id, barang_id, user_id, jenis_harga, harga_lama, harga_baru, mulai_berlaku, keterangan)
        VALUES
            (?, ?, ?, ?, ?, ?, NOW(), ?)
    ");

    $stmt->execute([
        $tenant_id,
        $barang_id,
        $user_id,
        $jenis,
        (float)$harga_lama,
        (float)$harga_baru,
        $keterangan
    ]);
}

$barang_id = (int)($_POST['barang_id'] ?? 0);
$kode_barang = clean_str($_POST['kode_barang'] ?? '', 50);
$barcode = clean_str($_POST['barcode'] ?? '', 100);
$barcode_eceran = clean_str($_POST['barcode_eceran'] ?? '', 100);
$nama_barang = clean_str($_POST['nama_barang'] ?? '', 150);
$kategori = clean_str($_POST['kategori'] ?? '', 50);
$satuan = clean_str($_POST['satuan'] ?? '', 20);

$stok_masuk_utama = max(0, (int)($_POST['stok_gudang'] ?? 0));
$stok_masuk_ecer = max(0, (int)($_POST['stok_gudang_ecer'] ?? 0));
$harga_beli = (float)($_POST['harga_beli'] ?? 0);
$harga_jual = (float)($_POST['harga_jual'] ?? 0);

$bisa_diecer = isset($_POST['bisa_diecer']);
$isi_per_kemasan_post = $bisa_diecer ? max(1, (int)($_POST['isi_per_kemasan'] ?? 1)) : 1;
$satuan_ecer_post = $bisa_diecer ? clean_str($_POST['satuan_ecer'] ?? '', 50) : null;
$harga_ecer_post = $bisa_diecer ? (float)($_POST['harga_ecer'] ?? 0) : null;

if (!$bisa_diecer && $barang_id <= 0) {
    $stok_masuk_ecer = 0;
}

$aksi = $_POST['aksi'] ?? 'selesai';
$mode_input = $_POST['mode_input'] ?? ($_POST['opsi_nota'] ?? 'cepat');

if ($mode_input === 'nota' && !kasirrapi_feature_allowed($subscription, 'pembelian')) {
    redirect_error('Input nota supplier dan pembelian lengkap tersedia mulai paket Plus.');
}

if (!empty($_POST['supplier_id']) && !kasirrapi_feature_allowed($subscription, 'supplier')) {
    redirect_error('Fitur supplier tersedia mulai paket Plus.');
}

if (($_POST['opsi_supplier'] ?? 'lama') === 'baru' && !kasirrapi_feature_allowed($subscription, 'supplier')) {
    redirect_error('Fitur supplier tersedia mulai paket Plus.');
}

if ($stok_masuk_utama <= 0 && $stok_masuk_ecer <= 0) {
    redirect_error('Jumlah barang masuk harus lebih dari 0.');
}

if ($harga_beli < 0 || $harga_jual < 0) {
    redirect_error('Harga tidak boleh negatif.');
}

if ($barang_id <= 0) {
    $maxProducts = (int)($subscription['max_products'] ?? 0);
    if ($maxProducts > 0) {
        $stmtLimit = $pdo->prepare("SELECT COUNT(*) FROM barang WHERE tenant_id = ? AND COALESCE(is_aktif, 1) = 1");
        $stmtLimit->execute([$tenant_id]);
        if (kasirrapi_limit_reached($maxProducts, (int)$stmtLimit->fetchColumn())) {
            redirect_error('Batas barang aktif paket ' . ($subscription['plan'] ?? 'Gratis') . ' sudah tercapai. Silakan upgrade paket.');
        }
    }

    if ($nama_barang === '') {
        redirect_error('Nama barang wajib diisi.');
    }

    if ($satuan === '') {
        redirect_error('Satuan utama wajib diisi.');
    }

    if ($bisa_diecer && (!$satuan_ecer_post || $satuan_ecer_post === '')) {
        redirect_error('Satuan ecer wajib diisi jika barang bisa dijual ecer.');
    }

    if ($bisa_diecer && $harga_ecer_post < 0) {
        redirect_error('Harga ecer tidak boleh negatif.');
    }

    if ($kode_barang === '') {
        $kode_barang = $barcode !== '' ? $barcode : 'BRG-' . date('YmdHis') . rand(10, 99);
    }
}

try {
    /*
     * Jangan taruh CREATE TABLE di dalam transaction.
     * Ini penyebab error: There is no active transaction.
     */
    ensure_harga_riwayat_table($pdo);

    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE id = ? LIMIT 1");
    $stmt->execute([$tenant_id]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException('Tenant/toko tidak valid. Silakan logout lalu login ulang.');
    }

    $pdo->beginTransaction();

    $supplier_id = null;

    if ($mode_input === 'nota' || isset($_SESSION['current_pembelian_id'])) {
        $opsi_supplier = $_POST['opsi_supplier'] ?? 'lama';

        if ($opsi_supplier === 'lama' && !empty($_POST['supplier_id'])) {
            $candidate = (int)$_POST['supplier_id'];

            $stmt = $pdo->prepare("
                SELECT id
                FROM suppliers
                WHERE id = ?
                  AND tenant_id = ?
                LIMIT 1
            ");
            $stmt->execute([$candidate, $tenant_id]);

            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $supplier_id = $candidate;
            }
        }

        if ($opsi_supplier === 'baru') {
            $nama_supplier_baru = clean_str($_POST['nama_supplier_baru'] ?? '', 100);
            $wa_supplier_baru = clean_str($_POST['wa_supplier_baru'] ?? '', 20);

            if ($nama_supplier_baru !== '') {
                $stmt = $pdo->prepare("
                    INSERT INTO suppliers (tenant_id, nama_supplier, no_wa)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$tenant_id, $nama_supplier_baru, $wa_supplier_baru ?: null]);
                $supplier_id = (int)$pdo->lastInsertId();
            }
        }
    }

    $pembelian_id = isset($_SESSION['current_pembelian_id']) ? (int)$_SESSION['current_pembelian_id'] : null;

    if ($pembelian_id) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM pembelian
            WHERE id = ?
              AND tenant_id = ?
            LIMIT 1
        ");
        $stmt->execute([$pembelian_id, $tenant_id]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            unset($_SESSION['current_pembelian_id'], $_SESSION['current_nomor_nota']);
            $pembelian_id = null;
        }
    }

    if (!$pembelian_id) {
        $foto_nota = null;

        if ($mode_input === 'nota') {
            $foto_nota = upload_image('foto_nota', $tenant_id, 'nota');
        }

        $nomor_nota = clean_str($_POST['nomor_nota'] ?? '', 50);
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $tanggal = date('Y-m-d');
        }

        $stmt = $pdo->prepare("
            INSERT INTO pembelian
                (tenant_id, supplier_id, nomor_nota, foto_nota, tanggal, total_pembelian)
            VALUES
                (?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([$tenant_id, $supplier_id, $nomor_nota ?: null, $foto_nota, $tanggal]);

        $pembelian_id = (int)$pdo->lastInsertId();

        if ($mode_input === 'nota' && $aksi === 'tambah') {
            $_SESSION['current_pembelian_id'] = $pembelian_id;
            $_SESSION['current_nomor_nota'] = $nomor_nota ?: ('Nota #' . $pembelian_id);
        }
    }

    if ($barang_id > 0) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM barang
            WHERE id = ?
              AND tenant_id = ?
            LIMIT 1
        ");
        $stmt->execute([$barang_id, $tenant_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            throw new RuntimeException('Barang lama tidak ditemukan.');
        }

        $isi_per_kemasan_final = max(1, (int)($existing['isi_per_kemasan'] ?? 1));
        $qty_satuan_terkecil = ($stok_masuk_utama * $isi_per_kemasan_final) + $stok_masuk_ecer;
        $stok_baru = (int)($existing['stok_gudang'] ?? 0) + $qty_satuan_terkecil;

        $barang_punya_ecer = (
            (int)($existing['isi_per_kemasan'] ?? 1) > 1 ||
            !empty($existing['barcode_eceran']) ||
            $existing['harga_ecer'] !== null ||
            !empty($existing['satuan_ecer'])
        );

        $harga_ecer_baru = null;

        if ($barang_punya_ecer) {
            $harga_ecer_baru = isset($_POST['harga_ecer']) && $_POST['harga_ecer'] !== ''
                ? (float)$_POST['harga_ecer']
                : (float)($existing['harga_ecer'] ?? 0);

            if ($harga_ecer_baru < 0) {
                redirect_error('Harga ecer tidak boleh negatif.');
            }
        }

        $keterangan_harga = 'Perubahan harga dari penerimaan barang / pembelian #' . $pembelian_id;

        catat_riwayat_harga(
            $pdo,
            $tenant_id,
            $barang_id,
            $user_id,
            'harga_beli',
            (float)($existing['harga_beli'] ?? 0),
            $harga_beli,
            $keterangan_harga
        );

        catat_riwayat_harga(
            $pdo,
            $tenant_id,
            $barang_id,
            $user_id,
            'harga_jual',
            (float)($existing['harga_jual'] ?? 0),
            $harga_jual,
            $keterangan_harga
        );

        if ($barang_punya_ecer && $harga_ecer_baru !== null) {
            catat_riwayat_harga(
                $pdo,
                $tenant_id,
                $barang_id,
                $user_id,
                'harga_ecer',
                (float)($existing['harga_ecer'] ?? 0),
                $harga_ecer_baru,
                $keterangan_harga
            );
        }

        $stmt = $pdo->prepare("
            UPDATE barang
            SET supplier_id = COALESCE(?, supplier_id),
                harga_beli = ?,
                harga_jual = ?,
                harga_ecer = ?,
                stok_gudang = ?
            WHERE id = ?
              AND tenant_id = ?
        ");

        $stmt->execute([
            $supplier_id,
            $harga_beli,
            $harga_jual,
            $harga_ecer_baru,
            $stok_baru,
            $barang_id,
            $tenant_id
        ]);

        $final_barang_id = $barang_id;
        $qty_pembelian_utama = $qty_satuan_terkecil / $isi_per_kemasan_final;
        $total_pembelian_item = $qty_pembelian_utama * $harga_beli;

    } else {
        $isi_per_kemasan_final = $isi_per_kemasan_post;
        $qty_satuan_terkecil = ($stok_masuk_utama * $isi_per_kemasan_final) + $stok_masuk_ecer;
        $qty_pembelian_utama = $qty_satuan_terkecil / $isi_per_kemasan_final;
        $total_pembelian_item = $qty_pembelian_utama * $harga_beli;

        $foto_barang = upload_image('foto_barang', $tenant_id, 'barang');

        $stmt = $pdo->prepare("
            INSERT INTO barang
                (
                    tenant_id, supplier_id, kode_barang, barcode, barcode_eceran, nama_barang, kategori,
                    harga_beli, harga_jual, harga_ecer, stok_gudang, satuan,
                    isi_per_kemasan, satuan_ecer, foto_barang
                )
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $tenant_id,
            $supplier_id,
            $kode_barang,
            $barcode ?: null,
            $barcode_eceran ?: null,
            $nama_barang,
            $kategori ?: null,
            $harga_beli,
            $harga_jual,
            $bisa_diecer ? $harga_ecer_post : null,
            $qty_satuan_terkecil,
            $satuan,
            $isi_per_kemasan_final,
            $bisa_diecer ? $satuan_ecer_post : null,
            $foto_barang
        ]);

        $final_barang_id = (int)$pdo->lastInsertId();
    }

    $stmt = $pdo->prepare("
        INSERT INTO pembelian_detail
            (pembelian_id, barang_id, qty, harga, subtotal)
        VALUES
            (?, ?, ?, ?, ?)
    ");
        $stmt->execute([
        $pembelian_id,
        $final_barang_id,
        $qty_pembelian_utama ?? ($qty_satuan_terkecil / max(1, (int)($isi_per_kemasan_final ?? 1))),
        (int)$harga_beli,
        (int)$total_pembelian_item
    ]);

    $stmt = $pdo->prepare("
        UPDATE pembelian
        SET total_pembelian = (
            SELECT COALESCE(SUM(subtotal), 0)
            FROM pembelian_detail
            WHERE pembelian_id = ?
        )
        WHERE id = ?
          AND tenant_id = ?
    ");
    $stmt->execute([$pembelian_id, $pembelian_id, $tenant_id]);

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

    if ($aksi === 'tambah') {
        $_SESSION['flash']['success'] = $barang_id > 0
            ? 'Stok barang lama berhasil ditambah. Harga baru sudah dicatat jika berubah.'
            : 'Barang baru berhasil disimpan. Silakan tambah barang berikutnya.';

        header('Location: tambah.php');
        exit;
    }

    unset($_SESSION['current_pembelian_id'], $_SESSION['current_nomor_nota']);

    $_SESSION['flash']['success'] = $barang_id > 0
        ? 'Stok barang lama berhasil ditambah. Riwayat harga tersimpan jika ada perubahan.'
        : 'Barang baru berhasil disimpan.';

    header('Location: index.php?status=sukses');
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Gagal proses tambah barang: ' . $e->getMessage());

    $_SESSION['flash']['error'] = 'Gagal menyimpan barang. ' . $e->getMessage();
    header('Location: tambah.php');
    exit;
}
