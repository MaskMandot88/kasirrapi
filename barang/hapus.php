<?php
// barang/hapus.php
// Sekarang file ini berfungsi untuk MENGARSIPKAN barang, bukan menghapus permanen.

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['Owner', 'Admin', 'Gudang'], true)) {
    die("Akses ditolak.");
}

$tenant_id = (int) $_SESSION['tenant_id'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['flash']['error'] = 'ID barang tidak valid.';
    header("Location: index.php?status=gagal");
    exit;
}

try {
    // Pastikan barang milik tenant ini
    $stmt = $pdo->prepare("
        SELECT id, nama_barang, is_aktif
        FROM barang
        WHERE id = ?
          AND tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$id, $tenant_id]);
    $barang = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$barang) {
        $_SESSION['flash']['error'] = 'Barang tidak ditemukan atau bukan milik toko ini.';
        header("Location: index.php?status=gagal");
        exit;
    }

    if ((int)($barang['is_aktif'] ?? 1) === 0) {
        $_SESSION['flash']['error'] = 'Barang "' . $barang['nama_barang'] . '" sudah diarsipkan sebelumnya.';
        header("Location: index.php?status=gagal");
        exit;
    }

    // Arsipkan barang, bukan hapus permanen
    $stmt = $pdo->prepare("
        UPDATE barang
        SET is_aktif = 0
        WHERE id = ?
          AND tenant_id = ?
    ");
    $stmt->execute([$id, $tenant_id]);

    $_SESSION['flash']['success'] = 'Barang "' . $barang['nama_barang'] . '" berhasil diarsipkan.';
    header("Location: index.php?status=arsip");
    exit;

} catch (PDOException $e) {
    $_SESSION['flash']['error'] = 'Barang gagal diarsipkan.';
    header("Location: index.php?status=gagal");
    exit;
}
