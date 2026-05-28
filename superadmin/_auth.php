<?php
// Proteksi akses khusus area Super Admin.

$superadminSessionReady = session_status() === PHP_SESSION_ACTIVE;
if (!$superadminSessionReady) {
    $superadminSessionReady = @session_start();
}
if (!defined('SUPERADMIN_SESSION_READY')) {
    define('SUPERADMIN_SESSION_READY', (bool)$superadminSessionReady);
}

$superadminConfigPath = __DIR__ . '/../config/superadmin.php';
$superadminConfigLoaded = file_exists($superadminConfigPath);
if ($superadminConfigLoaded) {
    require_once $superadminConfigPath;
}
if (!defined('SUPERADMIN_CONFIG_LOADED')) {
    define('SUPERADMIN_CONFIG_LOADED', (bool)$superadminConfigLoaded);
}

if (!defined('SUPERADMIN_USERNAME')) {
    define('SUPERADMIN_USERNAME', '');
}

if (!defined('SUPERADMIN_PASSWORD_HASH')) {
    define('SUPERADMIN_PASSWORD_HASH', '');
}

if (!function_exists('superadmin_is_logged_in')) {
    function superadmin_is_logged_in() {
        if (!SUPERADMIN_SESSION_READY) {
            return false;
        }

        return !empty($_SESSION['superadmin_logged_in'])
            && hash_equals((string)($_SESSION['superadmin_username'] ?? ''), (string)SUPERADMIN_USERNAME);
    }
}

if (!function_exists('superadmin_config_ready')) {
    function superadmin_config_ready() {
        $hash = (string)SUPERADMIN_PASSWORD_HASH;
        $hashInfo = password_get_info($hash);
        $isBcryptHash = !empty($hashInfo['algo'])
            || preg_match('/^\$2[ayb]\$\d{2}\$[\.\/A-Za-z0-9]{53}$/', $hash);

        return SUPERADMIN_CONFIG_LOADED
            && trim((string)SUPERADMIN_USERNAME) !== ''
            && trim($hash) !== ''
            && $hash !== 'ISI_HASH_PASSWORD_DI_SINI'
            && $isBcryptHash;
    }
}

if (!function_exists('superadmin_csrf_token')) {
    function superadmin_csrf_token() {
        if (!SUPERADMIN_SESSION_READY) {
            return '';
        }

        if (empty($_SESSION['superadmin_csrf_token'])) {
            $_SESSION['superadmin_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['superadmin_csrf_token'];
    }
}

if (!function_exists('superadmin_csrf_valid')) {
    function superadmin_csrf_valid($token) {
        if (!SUPERADMIN_SESSION_READY) {
            return false;
        }

        return hash_equals((string)($_SESSION['superadmin_csrf_token'] ?? ''), (string)$token);
    }
}

if (!function_exists('superadmin_verify_login')) {
    function superadmin_verify_login($username, $password) {
        if (!SUPERADMIN_SESSION_READY || !superadmin_config_ready()) {
            return false;
        }

        return hash_equals((string)SUPERADMIN_USERNAME, trim((string)$username))
            && password_verify((string)$password, (string)SUPERADMIN_PASSWORD_HASH);
    }
}

if (!function_exists('superadmin_require_login')) {
    function superadmin_require_login() {
        if (!SUPERADMIN_SESSION_READY) {
            http_response_code(500);
            exit('Session server tidak bisa dimulai. Periksa konfigurasi session.save_path di hosting.');
        }

        if (!superadmin_is_logged_in()) {
            header('Location: login.php');
            exit;
        }
    }
}
