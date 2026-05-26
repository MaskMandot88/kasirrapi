<?php
// gaji/_auth.php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id_login = (int)$_SESSION['user_id'];
$role_login = $_SESSION['role'];
$nama_login = $_SESSION['nama'] ?? ($_SESSION['nama_user'] ?? 'User');

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rupiah')) {
    function rupiah($angka) {
        return 'Rp ' . number_format((float)$angka, 0, ',', '.');
    }
}

if (!function_exists('is_payroll_role')) {
    function is_payroll_role() {
        global $role_login;
        return in_array($role_login, ['Owner','Admin','HRD'], true);
    }
}

if (!function_exists('flash')) {
    function flash($key) {
        if (!isset($_SESSION['flash'][$key])) return null;
        $value = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $value;
    }
}

if (!function_exists('redirect_with')) {
    function redirect_with($url, $key, $message) {
        $_SESSION['flash'][$key] = $message;
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('tanggal_id')) {
    function tanggal_id($date) {
        if (!$date) return '-';
        return date('d/m/Y', strtotime($date));
    }
}

if (!is_payroll_role()) {
    die('Akses ditolak. Modul gaji hanya untuk Owner/Admin/HRD.');
}
