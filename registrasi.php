<?php
session_start();

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/pwa.php';
require_once __DIR__ . '/includes/tripay.php';

if (isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: ' . app_url('dashboard/index.php'));
    exit;
}

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (empty($_SESSION['csrf_registrasi'])) {
    $_SESSION['csrf_registrasi'] = bin2hex(random_bytes(32));
}

$packages = tripay_packages();
$methods = tripay_payment_methods();
$addons = kasirrapi_addons();
$selectedPackage = $_GET['paket'] ?? 'Plus';
if (!isset($packages[$selectedPackage])) $selectedPackage = 'Plus';
$defaultMethod = defined('TRIPAY_DEFAULT_METHOD') && isset($methods[TRIPAY_DEFAULT_METHOD]) ? TRIPAY_DEFAULT_METHOD : 'QRIS';
$error = $_SESSION['flash']['registrasi_error'] ?? '';
unset($_SESSION['flash']['registrasi_error']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Toko - <?= h(APP_NAME) ?></title>
    <meta name="description" content="Daftarkan toko Anda ke KasirRapi. Mulai gratis, trial Plus 14 hari, atau bayar paket Basic, Plus, dan Pro melalui Tripay.">
    <meta name="theme-color" content="#FF6A00">
    <link rel="icon" type="image/png" href="<?= h(asset_url('app/favicon.png')) ?>?v=<?= h(APP_VERSION) ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php pwa_head_tags(); ?>
    <style>
        body{min-height:100vh;background:radial-gradient(circle at top left,rgba(255,106,0,.18),transparent 32rem),#020617;color:#e2e8f0}
        .registration-card{background:rgba(15,23,42,.92);border:1px solid rgba(148,163,184,.16);box-shadow:0 28px 80px rgba(0,0,0,.32)}
        .field{width:100%;border:1px solid #334155;background:#020617;color:#e2e8f0;border-radius:14px;padding:13px 14px;outline:none}
        .field:focus{border-color:#ff6a00;box-shadow:0 0 0 3px rgba(255,106,0,.18)}
        .label{display:block;color:#cbd5e1;font-size:13px;font-weight:800;margin-bottom:7px}
        .package{border:1px solid #334155;background:#020617;border-radius:16px;padding:15px;cursor:pointer}
        .package:has(input:checked){border-color:#ff6a00;background:rgba(255,106,0,.1)}
        .btn{min-height:46px;border-radius:14px;padding:12px 18px;font-weight:950;display:inline-flex;align-items:center;justify-content:center}
        .btn-primary{background:#ff6a00;color:#fff}
        .btn-secondary{background:#1e293b;color:#e2e8f0;border:1px solid #334155}
        .top-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end}
        @media(max-width:640px){.top-actions .btn{min-height:40px;padding:9px 12px;font-size:13px}}
    </style>
</head>
<body>
<main class="min-h-screen px-4 py-6 md:py-10">
    <div class="max-w-6xl mx-auto">
        <header class="flex items-center justify-between gap-4 mb-8">
            <a href="<?= h(app_url('index.php')) ?>" aria-label="<?= h(APP_NAME) ?>">
                <img src="<?= h(asset_url('app/logo-full.png')) ?>?v=<?= h(APP_VERSION) ?>" alt="<?= h(APP_NAME) ?>" class="w-[170px] max-w-[48vw]">
            </a>
            <div class="top-actions">
                <a href="<?= h(app_url('index.php#harga')) ?>" class="btn btn-secondary">Kembali</a>
                <a href="<?= h(app_url('auth/login.php')) ?>" class="btn btn-secondary">Login</a>
            </div>
        </header>

        <div class="grid lg:grid-cols-[.9fr_1.1fr] gap-6 items-start">
            <section class="pt-2 md:pt-8">
                <div class="inline-flex rounded-full border border-orange-700 bg-orange-950/40 text-orange-200 px-3 py-2 text-sm font-bold mb-5">
                    Registrasi Online
                </div>
                <h1 class="text-4xl md:text-5xl font-black text-white leading-tight">
                    Daftarkan toko dan aktifkan akun <?= h(APP_NAME) ?>.
                </h1>
                <p class="text-slate-300 leading-8 mt-5 text-lg">
                    Mulai dari paket Gratis, trial Plus 14 hari, atau pilih paket berbayar dan selesaikan pembayaran melalui Tripay.
                    Akun Owner akan dibuat otomatis setelah registrasi aktif.
                </p>
                <div class="grid sm:grid-cols-3 gap-3 mt-7">
                    <div class="registration-card rounded-2xl p-4"><b class="text-white">1. Isi Data</b><p class="text-sm text-slate-400 mt-1">Nama toko, owner, email, dan WA.</p></div>
                    <div class="registration-card rounded-2xl p-4"><b class="text-white">2. Pilih paket</b><p class="text-sm text-slate-400 mt-1">Gratis, Basic, Plus, atau Pro.</p></div>
                    <div class="registration-card rounded-2xl p-4"><b class="text-white">3. Login</b><p class="text-sm text-slate-400 mt-1">Akun Owner aktif setelah callback PAID.</p></div>
                </div>
            </section>

            <form action="<?= h(app_url('proses-registrasi.php')) ?>" method="POST" class="registration-card rounded-3xl p-5 md:p-6 space-y-5">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_registrasi']) ?>">

                <?php if (!tripay_is_configured()): ?>
                    <div class="rounded-2xl border border-yellow-600/40 bg-yellow-950/30 text-yellow-100 p-4 text-sm leading-6">
                        Konfigurasi Tripay belum aktif. Paket Gratis dan Trial tetap bisa dibuat. Paket berbayar perlu <b>config/tripay.php</b> berisi API Key, Private Key, dan Merchant Code.
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="rounded-2xl border border-red-600/40 bg-red-950/30 text-red-100 p-4 text-sm leading-6">
                        <?= h($error) ?>
                    </div>
                <?php endif; ?>

                <div class="grid sm:grid-cols-2 gap-4">
                    <label>
                        <span class="label">Nama Toko *</span>
                        <input type="text" name="nama_toko" class="field" required placeholder="Contoh: Toko Berkah">
                    </label>
                    <label>
                        <span class="label">Nama Pemilik *</span>
                        <input type="text" name="nama_pemilik" class="field" required placeholder="Nama owner">
                    </label>
                    <label>
                        <span class="label">Email Login Owner *</span>
                        <input type="email" name="email" class="field" required autocomplete="email" placeholder="owner@email.com">
                    </label>
                    <label>
                        <span class="label">Nomor WhatsApp</span>
                        <input type="text" name="no_wa" class="field" placeholder="08xxxxxxxxxx">
                    </label>
                    <label>
                        <span class="label">Password Owner *</span>
                        <input type="password" name="password" class="field" required minlength="6" autocomplete="new-password" placeholder="Minimal 6 karakter">
                    </label>
                    <label>
                        <span class="label">Ulangi Password *</span>
                        <input type="password" name="password_confirm" class="field" required minlength="6" autocomplete="new-password" placeholder="Ulangi password">
                    </label>
                </div>

                <section>
                    <span class="label">Pilih Paket *</span>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <?php foreach ($packages as $code => $package): ?>
                            <label class="package">
                                <input type="radio" name="paket_langganan" value="<?= h($code) ?>" class="sr-only js-plan" <?= $code === $selectedPackage ? 'checked' : '' ?> data-paid="<?= (int)$package['price'] > 0 ? '1' : '0' ?>" data-hrd="<?= $package['addon_hrd_available'] ? '1' : '0' ?>">
                                <div class="flex items-start justify-between gap-3">
                                    <b class="text-white"><?= h($package['name']) ?></b>
                                    <?php $badge = kasirrapi_plan($code)['badge'] ?? ''; ?>
                                    <?php if ($badge): ?><span class="text-[10px] uppercase tracking-wide text-orange-200 bg-orange-950/60 border border-orange-700 rounded-full px-2 py-1"><?= h($badge) ?></span><?php endif; ?>
                                </div>
                                <div class="text-orange-300 font-black mt-2"><?= h(kasirrapi_format_price($package['price'])) ?><span class="text-xs text-slate-500">/bulan</span></div>
                                <div class="text-xs text-slate-500 mt-1">
                                    <?= h(kasirrapi_unlimited_label($package['max_users'], ' user')) ?>,
                                    <?= h(kasirrapi_unlimited_label($package['max_products'], ' barang')) ?>,
                                    transaksi <?= h(kasirrapi_unlimited_label($package['max_transactions_per_month'], '/bulan')) ?>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="grid sm:grid-cols-2 gap-3" id="billing_section">
                    <label class="package">
                        <input type="radio" name="billing_cycle" value="monthly" class="sr-only" checked>
                        <b class="text-white">Bulanan</b>
                        <div class="text-xs text-slate-500 mt-1">Bayar per bulan melalui Tripay.</div>
                    </label>
                    <label class="package">
                        <input type="radio" name="billing_cycle" value="yearly" class="sr-only">
                        <b class="text-white">Tahunan</b>
                        <div class="text-xs text-slate-500 mt-1">Harga hemat setara 10 bulan.</div>
                    </label>
                </section>

                <label class="package block" id="trial_section">
                    <input type="checkbox" name="start_trial" value="1" id="start_trial" class="mr-2 align-middle">
                    <b class="text-white">Trial Plus 14 hari</b>
                    <div class="text-xs text-slate-500 mt-1">Semua fitur Plus aktif, tanpa pembayaran. Setelah trial, toko turun ke Gratis jika tidak berlangganan.</div>
                </label>

                <label class="package block hidden" id="addon_hrd_section">
                    <input type="checkbox" name="addon_hrd" value="1" id="addon_hrd" class="mr-2 align-middle">
                    <b class="text-white">Tambah Absensi & Gaji</b>
                    <div class="text-xs text-slate-500 mt-1">
                        +<?= h(kasirrapi_format_price($addons['hrd']['monthly_price'])) ?>/bulan sampai <?= (int)$addons['hrd']['max_employees'] ?> karyawan.
                    </div>
                </label>

                <label class="block" id="payment_method_section">
                    <span class="label">Metode Pembayaran Tripay *</span>
                    <select name="payment_method" class="field" required>
                        <?php foreach ($methods as $code => $label): ?>
                            <option value="<?= h($code) ?>" <?= $code === $defaultMethod ? 'selected' : '' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <button type="submit" id="submit_button" class="btn btn-primary w-full">
                    Lanjut Registrasi
                </button>

                <p class="text-xs text-slate-500 leading-5">
                    Dengan registrasi, Anda menyetujui syarat layanan KasirRapi. Pembayaran diproses oleh Tripay dan akun aktif setelah status pembayaran sukses.
                </p>
            </form>
        </div>
    </div>
</main>

<script>
function updateRegistrationOptions() {
    const selected = document.querySelector('.js-plan:checked');
    const trial = document.getElementById('start_trial');
    const paid = selected && selected.dataset.paid === '1';
    const hrdAvailable = selected && selected.dataset.hrd === '1';
    const trialActive = trial && trial.checked;
    const billing = document.getElementById('billing_section');
    const payment = document.getElementById('payment_method_section');
    const addon = document.getElementById('addon_hrd_section');
    const addonInput = document.getElementById('addon_hrd');
    const submit = document.getElementById('submit_button');

    if (trialActive) {
        document.querySelectorAll('.js-plan').forEach(function (radio) {
            radio.checked = radio.value === 'Plus';
        });
    }

    if (billing) billing.classList.toggle('hidden', !paid || trialActive);
    if (payment) payment.classList.toggle('hidden', !paid || trialActive);
    if (payment) payment.querySelector('select').disabled = !paid || trialActive;
    if (addon) addon.classList.toggle('hidden', !hrdAvailable || trialActive);
    if ((!hrdAvailable || trialActive) && addonInput) addonInput.checked = false;

    if (submit) {
        if (trialActive) {
            submit.textContent = 'Aktifkan Trial Plus 14 Hari';
        } else if (paid) {
            submit.textContent = 'Proses Pembayaran';
        } else {
            submit.textContent = 'Aktifkan Paket Gratis';
        }
    }
}

document.querySelectorAll('.js-plan, input[name="billing_cycle"], #start_trial, #addon_hrd').forEach(function (el) {
    el.addEventListener('change', updateRegistrationOptions);
});
updateRegistrationOptions();

<?php if ($error): ?>
Swal.fire({
    icon: 'error',
    title: 'Registrasi belum bisa diproses',
    text: <?= json_encode($error) ?>,
    confirmButtonColor: '#ff6a00',
    background: '#0f172a',
    color: '#e2e8f0'
});
<?php endif; ?>
</script>
</body>
</html>
