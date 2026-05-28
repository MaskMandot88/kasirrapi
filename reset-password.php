<?php
session_start();

require_once 'config/database.php';
require_once 'includes/ui.php';

$token = trim($_GET['token'] ?? '');
$status = $_GET['status'] ?? '';

$validToken = false;
$user = null;

if ($token !== '') {
    try {
        $token_hash = hash('sha256', $token);

        $stmt = $pdo->prepare("
            SELECT id, email, reset_token_expired
            FROM users
            WHERE reset_token = ?
              AND reset_token_expired IS NOT NULL
              AND reset_token_expired > NOW()
            LIMIT 1
        ");
        $stmt->execute([$token_hash]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $validToken = true;
        }
    } catch (Throwable $e) {
        $validToken = false;
    }
}

$alertTitle = '';
$alertText = '';
$alertIcon = '';

if ($status === 'password_kosong') {
    $alertTitle = 'Password Belum Lengkap';
    $alertText = 'Isi password baru dan konfirmasi password.';
    $alertIcon = 'warning';
} elseif ($status === 'password_pendek') {
    $alertTitle = 'Password Terlalu Pendek';
    $alertText = 'Password baru minimal 6 karakter.';
    $alertIcon = 'warning';
} elseif ($status === 'password_tidak_sama') {
    $alertTitle = 'Konfirmasi Tidak Cocok';
    $alertText = 'Password baru dan konfirmasi password tidak sama.';
    $alertIcon = 'warning';
} elseif ($status === 'token_invalid') {
    $alertTitle = 'Link Tidak Valid';
    $alertText = 'Link reset password tidak valid atau sudah kedaluwarsa.';
    $alertIcon = 'error';
} elseif ($status === 'gagal') {
    $alertTitle = 'Gagal';
    $alertText = 'Password gagal diperbarui. Silakan coba lagi.';
    $alertIcon = 'error';
}

ui_head('Reset Password');
?>

<body class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-4">

<style>
.auth-card {
    width: 100%;
    max-width: 460px;
    background: rgba(15, 23, 42, .96);
    border: 1px solid rgba(51, 65, 85, .9);
    border-radius: 24px;
    box-shadow: 0 24px 80px rgba(0,0,0,.45);
}

.swal2-container,
.kasirrapi-swal-container {
    position: fixed !important;
    inset: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 999999 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 16px !important;
    overflow: hidden !important;
}

.swal2-popup,
.kasirrapi-swal-popup {
    margin: 0 !important;
    max-width: 92vw !important;
    border-radius: 18px !important;
}

body.swal2-shown,
body.swal2-height-auto {
    height: auto !important;
    overflow: hidden !important;
}
</style>

<div class="auth-card p-6">
    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center gap-3 mb-4">
            <img src="<?= h(asset_url('app/logo-full.png')) ?>?v=<?= h(APP_VERSION) ?>"
                 alt="<?= h(APP_NAME) ?>"
                 class="w-[min(260px,74vw)] h-auto">
        </div>

        <h1 class="text-2xl font-extrabold text-white">Reset Password</h1>
        <p class="text-sm text-slate-400 mt-2">
            Buat password baru untuk akun Anda.
        </p>
    </div>

    <?php if (!$validToken): ?>
        <div class="p-4 rounded-2xl bg-red-950/40 border border-red-700/50 text-red-100 text-sm">
            Link reset password tidak valid atau sudah kedaluwarsa.
        </div>

        <div class="mt-5 grid grid-cols-1 gap-3">
            <a href="lupa-password.php" class="btn btn-primary w-full text-center">
                Minta Link Baru
            </a>

            <a href="login.php" class="btn btn-secondary w-full text-center">
                Kembali ke Login
            </a>
        </div>
    <?php else: ?>
        <form method="POST" action="process_reset_password.php" class="space-y-4">
            <input type="hidden" name="token" value="<?= h($token) ?>">

            <div>
                <label class="label">Password Baru</label>
                <div class="relative">
                    <input
                        type="password"
                        name="password_baru"
                        class="app-input pr-12"
                        autocomplete="new-password"
                        minlength="6"
                        required
                    >

                    <button
                        type="button"
                        class="btn-toggle-password absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-orange-300"
                        data-target="password_baru"
                        aria-label="Tampilkan password baru"
                    >
                        <svg class="icon-eye w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                        </svg>
                        <svg class="icon-eye-off w-5 h-5 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58A2 2 0 0 0 13.42 13.42"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.39A9.77 9.77 0 0 1 12 5.25c6 0 9.75 6.75 9.75 6.75a16.7 16.7 0 0 1-3.1 3.89"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.53 6.53C3.76 8.36 2.25 12 2.25 12s3.75 6.75 9.75 6.75c1.55 0 2.95-.45 4.17-1.1"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div>
                <label class="label">Konfirmasi Password Baru</label>
                <div class="relative">
                    <input
                        type="password"
                        name="password_konfirmasi"
                        class="app-input pr-12"
                        autocomplete="new-password"
                        minlength="6"
                        required
                    >

                    <button
                        type="button"
                        class="btn-toggle-password absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-orange-300"
                        data-target="password_konfirmasi"
                        aria-label="Tampilkan konfirmasi password"
                    >
                        <svg class="icon-eye w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                        </svg>
                        <svg class="icon-eye-off w-5 h-5 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58A2 2 0 0 0 13.42 13.42"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.39A9.77 9.77 0 0 1 12 5.25c6 0 9.75 6.75 9.75 6.75a16.7 16.7 0 0 1-3.1 3.89"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.53 6.53C3.76 8.36 2.25 12 2.25 12s3.75 6.75 9.75 6.75c1.55 0 2.95-.45 4.17-1.1"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-full">
                Simpan Password Baru
            </button>
        </form>

        <div class="mt-5 text-center">
            <a href="login.php" class="text-sm text-orange-300 hover:text-orange-200 font-semibold">
                Kembali ke Login
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const alertTitle = <?= json_encode($alertTitle) ?>;
    const alertText = <?= json_encode($alertText) ?>;
    const alertIcon = <?= json_encode($alertIcon) ?>;

    if (alertTitle && typeof Swal !== 'undefined') {
        Swal.fire({
            title: alertTitle,
            text: alertText,
            icon: alertIcon,
            confirmButtonText: 'OK',
            confirmButtonColor: '#f97316',
            background: '#020617',
            color: '#e5e7eb',
            heightAuto: false,
            scrollbarPadding: false,
            customClass: {
                container: 'kasirrapi-swal-container',
                popup: 'kasirrapi-swal-popup'
            }
        });

        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.delete('status');
            window.history.replaceState({}, document.title, url.pathname + url.search);
        }
    }

    document.querySelectorAll('.btn-toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const targetName = this.dataset.target;
            const input = document.querySelector('input[name="' + targetName + '"]');

            if (!input) return;

            const iconEye = this.querySelector('.icon-eye');
            const iconEyeOff = this.querySelector('.icon-eye-off');

            if (input.type === 'password') {
                input.type = 'text';
                if (iconEye) iconEye.classList.add('hidden');
                if (iconEyeOff) iconEyeOff.classList.remove('hidden');
                this.setAttribute('aria-label', 'Sembunyikan password');
            } else {
                input.type = 'password';
                if (iconEye) iconEye.classList.remove('hidden');
                if (iconEyeOff) iconEyeOff.classList.add('hidden');
                this.setAttribute('aria-label', 'Tampilkan password');
            }
        });
    });
});
</script>

</body>
</html>
