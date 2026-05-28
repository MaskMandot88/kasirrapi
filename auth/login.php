<?php
session_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/pwa.php';
require_once __DIR__ . '/../includes/live-chat.php';

if (isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ' . app_url('dashboard/index.php'));
    exit;
}

$error = $_SESSION['flash']['error'] ?? '';
unset($_SESSION['flash']['error']);

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= h(APP_NAME) ?></title>
    <meta name="description" content="<?= h(APP_SEO_DESCRIPTION) ?>">
    <meta name="theme-color" content="#FF6A00">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= h(asset_url('app/app-ui.css')) ?>?v=<?= h(APP_VERSION) ?>">
    <link rel="icon" href="<?= h(asset_url('app/favicon.png')) ?>?v=<?= h(APP_VERSION) ?>" type="image/png">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php pwa_head_tags(); ?>
    <?php live_chat_head_tags(); ?>

    <style>
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(255,106,0,.18), transparent 30rem),
                radial-gradient(circle at bottom right, rgba(255,138,0,.12), transparent 28rem),
                #020617;
        }
        .login-card {
            background: rgba(15, 23, 42, .94);
            border: 1px solid rgba(255, 106, 0, .28);
            box-shadow: 0 30px 90px rgba(0,0,0,.35);
            backdrop-filter: blur(12px);
        }
    </style>
</head>
<body class="text-slate-200 flex items-center justify-center p-4">

<div id="globalLoader">
    <div class="loader-card">
        <img src="<?= h(asset_url('app/logo-full.png')) ?>?v=<?= h(APP_VERSION) ?>" alt="<?= h(APP_NAME) ?>" class="loader-logo-full">
        <div id="globalLoaderText" class="font-extrabold text-white"><?= h(APP_NAME) ?> memuat...</div>
        <div class="text-sm text-slate-400 mt-1">Mohon tunggu sebentar</div>
        <div class="loader-ring"></div>
    </div>
</div>

<div id="appFlashData" data-error="<?= h($error) ?>"></div>

<div class="w-full max-w-md">
    <div class="text-center mb-7">
        <img src="<?= h(asset_url('app/logo-full.png')) ?>?v=<?= h(APP_VERSION) ?>" alt="<?= h(APP_NAME) ?>" class="w-[min(300px,82vw)] h-auto mx-auto mb-4 drop-shadow-xl">
        <p class="text-slate-400 mt-2"><?= h(APP_TAGLINE) ?></p>
    </div>

    <form action="proses_login.php" method="POST" class="login-card rounded-3xl p-5 md:p-6 space-y-4">
        <label class="block">
            <span class="label">Email</span>
            <input type="email" name="email" required autocomplete="username" class="app-input mt-1" placeholder="Masukkan email...">
        </label>

        <label class="block">
            <span class="label">Password</span>
            <input type="password" name="password" required autocomplete="current-password" class="app-input mt-1" placeholder="Masukkan password...">
        </label>

        <button type="submit" class="btn btn-primary w-full">
            Masuk Dashboard
        </button>
        <div class="mt-4 text-center">
    <a href="/lupa-password.php" class="text-sm text-orange-300 hover:text-orange-200 font-semibold">
        Lupa password?
    </a>
</div>
    </form>

    <div class="text-center mt-6">
        <a href="<?= h(app_url('index.php')) ?>" class="text-slate-400 hover:text-orange-300 text-sm" data-no-loading="1">
            &larr; Kembali ke halaman utama
        </a>
    </div>
</div>

<script src="<?= h(asset_url('app/app-ui.js')) ?>?v=<?= h(APP_VERSION) ?>"></script>
</body>
</html>
