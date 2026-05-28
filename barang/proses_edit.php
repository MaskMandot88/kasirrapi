<?php
// barang/proses_edit.php
session_start();

require_once '../config/database.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['Owner', 'Admin', 'Gudang'], true)) {
    http_response_code(403);
    die('Akses ditolak.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Metode request tidak diizinkan.');
}

$tenant_id = (int) $_SESSION['tenant_id'];
$user_id = (int) $_SESSION['user_id'];

function post_str(string $key, bool $required = false, int $max = 255): ?string
{
    $value = trim((string)($_POST[$key] ?? ''));

    if ($required && $value === '') {
        throw new InvalidArgumentException("Field {$key} wajib diisi.");
    }

    if (function_exists('mb_strlen')) {
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException("Field {$key} terlalu panjang. Maksimal {$max} karakter.");
        }
    } else {
        if (strlen($value) > $max) {
            throw new InvalidArgumentException("Field {$key} terlalu panjang. Maksimal {$max} karakter.");
        }
    }

    return $value === '' ? null : $value;
}

function post_int_min(string $key, int $min): int
{
    $raw = $_POST[$key] ?? null;

    if ($raw === null || $raw === '') {
        throw new InvalidArgumentException("Field {$key} wajib diisi.");
    }

    if (!is_numeric($raw)) {
        throw new InvalidArgumentException("Field {$key} harus berupa angka.");
    }

    $value = (int)$raw;

    if ($value < $min) {
        throw new InvalidArgumentException("Field {$key} harus minimal {$min}.");
    }

    return $value;
}

function post_money(string $key, bool $allow_zero = true): float
{
    $raw = trim((string)($_POST[$key] ?? ''));
    $raw = str_replace(['.', ','], ['', '.'], $raw);

    if ($raw === '' || !is_numeric($raw)) {
        throw new InvalidArgumentException("Field {$key} harus berupa angka.");
    }

    $value = (float)$raw;

    if ($value < 0 || (!$allow_zero && $value <= 0)) {
        throw new InvalidArgumentException("Field {$key} harus lebih dari " . ($allow_zero ? 'atau sama dengan 0.' : '0.'));
    }

    return round($value, 2);
}

function upload_foto_barang(string $target_dir): ?string
{
    if (!isset($_FILES['foto_barang']) || ($_FILES['foto_barang']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES['foto_barang']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload foto barang gagal.');
    }

    if ($_FILES['foto_barang']['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Ukuran foto barang maksimal 2MB.');
    }

    $tmp = $_FILES['foto_barang']['tmp_name'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Foto barang harus berupa JPG, PNG, WEBP, atau GIF.');
    }

    if (!is_dir($target_dir) && !mkdir($target_dir, 0755, true)) {
        throw new RuntimeException('Gagal membuat folder upload barang.');
    }

    $filename = 'brg_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
    $dest = rtrim($target_dir, '/') . '/' . $filename;

    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Gagal menyimpan foto barang.');
    }

    return $filename;
}

function ensure_harga_riwayat_table(PDO $pdo): void
{
    /*
     * Penting:
     * Fungsi ini HARUS dipanggil sebelum beginTransaction().
     * CREATE TABLE di MySQL bisa melakukan implicit commit.
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

function harga_berubah($lama, $baru): bool
{
    return abs((float)$lama - (float)$baru) > 0.0001;
}

function catat_riwayat_harga(PDO $pdo, int $tenant_id, int $barang_id, int $user_id, string $jenis, $harga_lama, $harga_baru, ?string $keterangan = null): void
{
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

try {
    $id = post_int_min('id', 1);

    $kode_barang = post_str('kode_barang', false, 50);
    $barcode = post_str('barcode', true, 100);
    $barcode_eceran = post_str('barcode_eceran', false, 100);

    if ($kode_barang === null || $kode_barang === '') {
        $kode_barang = $barcode;
    }

    $nama_barang = post_str('nama_barang', true, 150);
    $kategori = post_str('kategori', false, 50);
    $satuan = post_str('satuan', true, 20);
    $harga_beli = post_money('harga_beli', true);
    $harga_jual = post_money('harga_jual', true);

    $bisa_diecer = isset($_POST['bisa_diecer']);
    $stok_gudang_utama = isset($_POST['stok_gudang_utama'])
        ? post_int_min('stok_gudang_utama', 0)
        : post_int_min('stok_gudang', 0);
    $stok_gudang_ecer = max(0, (int)($_POST['stok_gudang_ecer'] ?? 0));

    $isi_per_kemasan = 1;
    $satuan_ecer = null;
    $harga_ecer = null;

    if ($bisa_diecer) {
        $isi_per_kemasan = post_int_min('isi_per_kemasan', 1);
        $satuan_ecer = post_str('satuan_ecer', true, 50);
        $harga_ecer = post_money('harga_ecer', true);
    } else {
        $barcode_eceran = null;
        $stok_gudang_ecer = 0;
    }

    $stok_gudang = ($stok_gudang_utama * $isi_per_kemasan) + $stok_gudang_ecer;

    // Buat tabel riwayat harga sebelum transaction agar tidak terjadi implicit commit.
    ensure_harga_riwayat_table($pdo);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT *
        FROM barang
        WHERE id = ?
          AND tenant_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$id, $tenant_id]);
    $barang_lama = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$barang_lama) {
        throw new RuntimeException('Data barang tidak ditemukan.');
    }

    /*
     * Barcode utama dan barcode ecer boleh sama pada barang yang sama.
     * Namun tidak boleh bentrok dengan kode/barcode/barcode ecer milik barang lain.
     */
    $cekValues = array_values(array_unique(array_filter([
        $kode_barang,
        $barcode,
        $barcode_eceran
    ], function ($v) {
        return trim((string)$v) !== '';
    })));

    if (!empty($cekValues)) {
        $placeholders = implode(',', array_fill(0, count($cekValues), '?'));

        $sqlDup = "
            SELECT id, nama_barang
            FROM barang
            WHERE tenant_id = ?
              AND id <> ?
              AND (
                    kode_barang IN ($placeholders)
                    OR barcode IN ($placeholders)
                    OR barcode_eceran IN ($placeholders)
                  )
            LIMIT 1
        ";

        $paramsDup = array_merge(
            [$tenant_id, $id],
            $cekValues,
            $cekValues,
            $cekValues
        );

        $stmt_duplikat = $pdo->prepare($sqlDup);
        $stmt_duplikat->execute($paramsDup);
        $duplikat = $stmt_duplikat->fetch(PDO::FETCH_ASSOC);

        if ($duplikat) {
            throw new RuntimeException('Kode/barcode sudah dipakai oleh barang lain: ' . $duplikat['nama_barang']);
        }
    }

    $folder_barang = "../public/uploads/tenant_{$tenant_id}/barang/";
    $foto_baru = upload_foto_barang($folder_barang);
    $foto_final = $foto_baru ?: ($barang_lama['foto_barang'] ?? null);

    $keterangan = 'Perubahan harga dari halaman edit barang';

    catat_riwayat_harga(
        $pdo,
        $tenant_id,
        $id,
        $user_id,
        'harga_beli',
        (float)($barang_lama['harga_beli'] ?? 0),
        $harga_beli,
        $keterangan
    );

    catat_riwayat_harga(
        $pdo,
        $tenant_id,
        $id,
        $user_id,
        'harga_jual',
        (float)($barang_lama['harga_jual'] ?? 0),
        $harga_jual,
        $keterangan
    );

    if ($bisa_diecer) {
        catat_riwayat_harga(
            $pdo,
            $tenant_id,
            $id,
            $user_id,
            'harga_ecer',
            (float)($barang_lama['harga_ecer'] ?? 0),
            $harga_ecer,
            $keterangan
        );
    }

    $stmt_update = $pdo->prepare("
        UPDATE barang
        SET kode_barang = ?,
            barcode = ?,
            barcode_eceran = ?,
            nama_barang = ?,
            kategori = ?,
            harga_beli = ?,
            harga_jual = ?,
            harga_ecer = ?,
            stok_gudang = ?,
            satuan = ?,
            isi_per_kemasan = ?,
            satuan_ecer = ?,
            foto_barang = ?
        WHERE id = ?
          AND tenant_id = ?
    ");

    $stmt_update->execute([
        $kode_barang,
        $barcode,
        $barcode_eceran,
        $nama_barang,
        $kategori,
        $harga_beli,
        $harga_jual,
        $harga_ecer,
        $stok_gudang,
        $satuan,
        $isi_per_kemasan,
        $satuan_ecer,
        $foto_final,
        $id,
        $tenant_id
    ]);

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

    if ($foto_baru && !empty($barang_lama['foto_barang'])) {
        $path_lama = $folder_barang . $barang_lama['foto_barang'];
        if (is_file($path_lama)) {
            @unlink($path_lama);
        }
    }

    $_SESSION['flash']['success'] = 'Data barang berhasil diperbarui.';
    header('Location: index.php?status=sukses');
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Gagal proses edit barang: ' . $e->getMessage());

    $_SESSION['flash']['error'] = 'Gagal menyimpan perubahan: ' . $e->getMessage();
    header('Location: edit.php?id=' . urlencode((string)($_POST['id'] ?? 0)));
    exit;
}
