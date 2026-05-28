<?php
session_start();

require_once 'config/database.php';
require_once 'includes/ui.php';

$status = $_GET['status'] ?? '';
$alertTitle = '';
$alertText = '';
$alertIcon = '';

if ($status === 'cek_email') {
    $alertTitle = 'Cek Email Anda';
    $alertText = 'Jika email terdaftar, link reset password sudah dikirim.';
    $alertIcon = 'success';
} elseif ($status === 'email_invalid') {
    $alertTitle = 'Email Tidak Valid';
    $alertText = 'Masukkan alamat email yang benar.';
    $alertIcon = 'warning';
} elseif ($status === 'email_gagal') {
    $alertTitle = 'Email Gagal Dikirim';
    $alertText = 'Server belum bisa mengirim email. Periksa konfigurasi email hosting.';
    $alertIcon = 'error';
} elseif ($status === 'gagal') {
    $alertTitle = 'Gagal';
    $alertText = 'Terjadi kesalahan. Silakan coba lagi.';
    $alertIcon = 'error';
}

ui_head('Lupa Password');
?>

<body class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-4">

<style>
.auth-card {
    width: 100%;
    max-width: 440px;
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

        <h1 class="text-2xl font-extrabold text-white">Lupa Password</h1>
        <p class="text-sm text-slate-400 mt-2">
            Masukkan email akun Anda. Kami akan mengirim link untuk membuat password baru.
        </p>
    </div>

    <form method="POST" action="process_lupa_password.php" class="space-y-4">
        <div>
            <label class="label">Email Akun</label>
            <input
                type="email"
                name="email"
                class="app-input"
                placeholder="contoh@email.com"
                autocomplete="email"
                required
            >
        </div>

        <button type="submit" class="btn btn-primary w-full">
            Kirim Link Reset Password
        </button>
    </form>

    <div class="mt-5 text-center">
        <a href="login.php" class="text-sm text-orange-300 hover:text-orange-200 font-semibold">
            Kembali ke Login
        </a>
    </div>
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
});
</script>

</body>
</html>
