<?php
require_once __DIR__ . '/_auth.php';

$setupError = '';
if (!SUPERADMIN_SESSION_READY) {
    $setupError = 'Session server tidak bisa dimulai. Periksa session.save_path di hosting atau hubungi support hosting.';
} elseif (!SUPERADMIN_CONFIG_LOADED) {
    $setupError = 'File config/superadmin.php belum ada di hosting. Salin dari config/superadmin.example.php lalu isi username dan hash password.';
} elseif (!superadmin_config_ready()) {
    $setupError = 'Konfigurasi superadmin belum valid. Pastikan username terisi dan SUPERADMIN_PASSWORD_HASH berisi hash dari password_hash(), bukan password polos.';
}

if (superadmin_is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if ($setupError !== '') {
        $error = $setupError;
    } elseif (!superadmin_csrf_valid($csrf)) {
        $error = 'Sesi login tidak valid. Coba lagi.';
    } elseif (superadmin_verify_login($username, $password)) {
        session_regenerate_id(true);
        $_SESSION['superadmin_logged_in'] = true;
        $_SESSION['superadmin_username'] = SUPERADMIN_USERNAME;
        unset($_SESSION['superadmin_csrf_token']);
        session_write_close();
        header('Location: index.php');
        exit;
    } else {
        $error = 'Username atau password salah.';
    }
}

function superadmin_login_h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Super Admin</title>
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            color: #e5e7eb;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .login-card {
            width: 100%;
            max-width: 380px;
            background: #111827;
            border: 1px solid #374151;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 24px 80px rgba(0,0,0,.35);
        }
        h1 {
            margin: 0 0 6px;
            font-size: 24px;
        }
        p {
            margin: 0 0 20px;
            color: #9ca3af;
        }
        label {
            display: block;
            margin-top: 14px;
            font-weight: bold;
            font-size: 14px;
        }
        input {
            width: 100%;
            box-sizing: border-box;
            margin-top: 7px;
            padding: 11px 12px;
            border-radius: 6px;
            border: 1px solid #4b5563;
            background: #020617;
            color: #f9fafb;
            font-size: 15px;
        }
        button {
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            border: 0;
            border-radius: 6px;
            background: #f97316;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }
        .error {
            margin: 0 0 14px;
            padding: 10px 12px;
            border-radius: 6px;
            background: #7f1d1d;
            color: #fecaca;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <form method="POST" class="login-card">
        <h1>Super Admin</h1>
        <p>Masuk untuk mengelola tenant dan tiket bantuan.</p>

        <?php if ($setupError !== ''): ?>
            <div class="error"><?= superadmin_login_h($setupError) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="error"><?= superadmin_login_h($error) ?></div>
        <?php endif; ?>

        <input type="hidden" name="csrf_token" value="<?= superadmin_login_h(superadmin_csrf_token()) ?>">

        <label>
            Username
            <input type="text" name="username" autocomplete="username" required autofocus>
        </label>

        <label>
            Password
            <input type="password" name="password" autocomplete="current-password" required>
        </label>

        <button type="submit">Masuk</button>
    </form>
</body>
</html>
