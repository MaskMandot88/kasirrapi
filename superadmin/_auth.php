<?php
// Proteksi akses khusus area Super Admin.

session_start();

$superadminConfigPath = __DIR__ . '/../config/superadmin.php';
if (file_exists($superadminConfigPath)) {
    require_once $superadminConfigPath;
}

if (!defined('SUPERADMIN_USERNAME')) {
    define('SUPERADMIN_USERNAME', '');
}

if (!defined('SUPERADMIN_PASSWORD_HASH')) {
    define('SUPERADMIN_PASSWORD_HASH', '');
}

if (!defined('SUPERADMIN_ALLOWED_IPS')) {
    define('SUPERADMIN_ALLOWED_IPS', []);
}

if (!function_exists('superadmin_client_ip')) {
    function superadmin_client_ip() {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
}

if (!function_exists('superadmin_ip_allowed')) {
    function superadmin_ip_allowed() {
        $allowedIps = SUPERADMIN_ALLOWED_IPS;
        if (!is_array($allowedIps) || count($allowedIps) === 0) {
            return true;
        }

        return in_array(superadmin_client_ip(), $allowedIps, true);
    }
}

if (!function_exists('superadmin_is_logged_in')) {
    function superadmin_is_logged_in() {
        return !empty($_SESSION['superadmin_logged_in'])
            && hash_equals((string)($_SESSION['superadmin_username'] ?? ''), (string)SUPERADMIN_USERNAME);
    }
}

if (!function_exists('superadmin_csrf_token')) {
    function superadmin_csrf_token() {
        if (empty($_SESSION['superadmin_csrf_token'])) {
            $_SESSION['superadmin_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['superadmin_csrf_token'];
    }
}

if (!function_exists('superadmin_csrf_valid')) {
    function superadmin_csrf_valid($token) {
        return hash_equals((string)($_SESSION['superadmin_csrf_token'] ?? ''), (string)$token);
    }
}

if (!function_exists('superadmin_verify_login')) {
    function superadmin_verify_login($username, $password) {
        if (SUPERADMIN_USERNAME === '' || SUPERADMIN_PASSWORD_HASH === '') {
            return false;
        }

        return hash_equals((string)SUPERADMIN_USERNAME, trim((string)$username))
            && password_verify((string)$password, (string)SUPERADMIN_PASSWORD_HASH);
    }
}

if (!function_exists('superadmin_require_login')) {
    function superadmin_require_login() {
        if (!superadmin_ip_allowed()) {
            http_response_code(403);
            exit('Akses ditolak.');
        }

        if (!superadmin_is_logged_in()) {
            header('Location: login.php');
            exit;
        }
    }
}
