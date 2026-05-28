<?php
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/public-pages.php';
require_once __DIR__ . '/includes/live-chat.php';

if (isset($_SESSION['tenant_id'], $_SESSION['user_id'], $_SESSION['role'])) {
    header('Location: dashboard/index.php');
    exit;
}

$appName = defined('APP_NAME') ? APP_NAME : 'KasirRapi';
$appTagline = 'Transaksi Rapi, Usaha Lebih Pasti';
$appSeoTitle = 'KasirRapi - Transaksi Rapi, Usaha Lebih Pasti';
$appSeoDescription = 'Aplikasi kasir online untuk toko, warung, dan grosir kecil. Kelola transaksi, stok, piutang, laporan, hingga absensi karyawan dalam satu sistem.';

$logoFull = asset_url('app/logo-full.png') . '?v=' . rawurlencode(APP_VERSION);
$favicon = asset_url('app/favicon.png') . '?v=' . rawurlencode(APP_VERSION);
$heroImage = asset_url('app/landing-hero-kasirrapi.png') . '?v=' . rawurlencode(APP_VERSION);
$canonicalUrl = public_absolute_url('');
$logoFullAbs = public_absolute_url('assets/app/logo-full.png');
$heroImageAbs = public_absolute_url('assets/app/landing-hero-kasirrapi.png');

$testimonials = [
    [
        'quote' => 'Pencatatan penjualan jadi lebih rapi. Saya bisa cek omzet, barang menipis, dan piutang tanpa bongkar catatan manual.',
        'name' => 'Owner Toko Sembako',
        'business' => 'Toko retail harian',
    ],
    [
        'quote' => 'Kasir lebih cepat saat jam ramai. Stok juga lebih mudah dipantau karena barang masuk dan keluar langsung tercatat.',
        'name' => 'Pengelola Grosir',
        'business' => 'Grosir kebutuhan pokok',
    ],
    [
        'quote' => 'Fitur absensi dan gaji membantu mengurangi rekap manual. Laporan toko jadi lebih mudah dibaca setiap akhir bulan.',
        'name' => 'Admin Operasional',
        'business' => 'UMKM multi-karyawan',
    ],
];

$faqs = [
    [
        'question' => 'Apa itu KasirRapi?',
        'answer' => 'KasirRapi adalah aplikasi kasir online berbasis web untuk membantu toko, warung, grosir kecil, retail, dan UMKM mengelola transaksi penjualan, stok barang, piutang, absensi, gaji karyawan, dan laporan.',
    ],
    [
        'question' => 'Apakah KasirRapi cocok untuk toko kecil?',
        'answer' => 'Ya. KasirRapi cocok untuk toko sembako, kelontong, warung, minimarket lokal, grosir kecil, dan usaha retail yang ingin mulai mencatat transaksi dan stok secara lebih tertata.',
    ],
    [
        'question' => 'Apakah KasirRapi bisa mencatat piutang pelanggan?',
        'answer' => 'Bisa. KasirRapi mendukung transaksi hutang, catatan piutang pelanggan, pembayaran piutang, dan sisa tagihan.',
    ],
    [
        'question' => 'Apakah aplikasi ini bisa digunakan untuk karyawan?',
        'answer' => 'Bisa. KasirRapi memiliki pengaturan role seperti Owner, Admin, Kasir, Gudang, dan HRD sehingga akses dapat disesuaikan dengan tugas masing-masing.',
    ],
];

$homeJsonLd = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            '@id' => $canonicalUrl . '#organization',
            'name' => $appName,
            'url' => $canonicalUrl,
            'logo' => $logoFullAbs,
            'description' => $appSeoDescription,
        ],
        [
            '@type' => 'WebSite',
            '@id' => $canonicalUrl . '#website',
            'name' => $appName,
            'url' => $canonicalUrl,
            'description' => $appSeoDescription,
        ],
        [
            '@type' => 'SoftwareApplication',
            'name' => $appName,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'description' => $appSeoDescription,
            'url' => $canonicalUrl,
            'image' => $heroImageAbs,
            'offers' => [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'IDR',
                'lowPrice' => '0',
                'highPrice' => '129000',
            ],
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(function ($faq) {
                return [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $faq['answer'],
                    ],
                ];
            }, $faqs),
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($appSeoTitle) ?></title>
    <meta name="description" content="<?= h($appSeoDescription) ?>">
    <meta name="robots" content="index,follow">
    <meta name="theme-color" content="#FF6A00">
    <link rel="canonical" href="<?= h($canonicalUrl) ?>">
    <link rel="icon" type="image/png" href="<?= h($favicon) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= h($appName) ?>">
    <meta property="og:title" content="<?= h($appSeoTitle) ?>">
    <meta property="og:description" content="<?= h($appSeoDescription) ?>">
    <meta property="og:url" content="<?= h($canonicalUrl) ?>">
    <meta property="og:image" content="<?= h($heroImageAbs) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <script src="https://cdn.tailwindcss.com"></script>
    <?php live_chat_head_tags(); ?>
    <script type="application/ld+json"><?= json_encode($homeJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <style>
        *{box-sizing:border-box}
        html,body{max-width:100%;overflow-x:hidden;scroll-behavior:smooth}
        body{margin:0;background:#020617;color:#e2e8f0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
        a{text-decoration:none}
        .page{min-height:100vh;background:linear-gradient(180deg,#020617 0%,#0f172a 46%,#020617 100%)}
        .container{width:min(1160px,calc(100% - 32px));margin:0 auto}
        .header{position:sticky;top:0;z-index:50;background:rgba(2,6,23,.9);backdrop-filter:blur(14px);border-bottom:1px solid rgba(148,163,184,.14)}
        .header-inner{min-height:76px;display:flex;align-items:center;justify-content:space-between;gap:16px}
        .brand-logo{width:min(190px,48vw);height:auto;display:block}
        .nav{display:flex;align-items:center;gap:12px}
        .nav-link{color:#cbd5e1;font-size:14px;font-weight:800;padding:10px 4px;white-space:nowrap}
        .nav-link:hover{color:#fdba74}
        .btn{min-height:44px;border-radius:999px;padding:11px 18px;display:inline-flex;align-items:center;justify-content:center;font-weight:900;transition:.16s ease;white-space:nowrap}
        .btn-primary{background:#ff6a00;color:#fff;box-shadow:0 18px 42px rgba(255,106,0,.25)}
        .btn-primary:hover{background:#ff7a1a;transform:translateY(-1px)}
        .btn-secondary{background:rgba(30,41,59,.86);color:#e2e8f0;border:1px solid rgba(148,163,184,.18)}
        .btn-secondary:hover{background:rgba(51,65,85,.92);transform:translateY(-1px)}
        .hero{position:relative;min-height:calc(100vh - 76px);display:flex;align-items:center;overflow:hidden;border-bottom:1px solid rgba(148,163,184,.12)}
        .hero::before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(2,6,23,.96) 0%,rgba(2,6,23,.86) 34%,rgba(2,6,23,.24) 62%,rgba(2,6,23,.16) 100%);z-index:1}
        .hero::after{content:"";position:absolute;inset:0;background-image:linear-gradient(rgba(148,163,184,.08) 1px,transparent 1px),linear-gradient(90deg,rgba(148,163,184,.08) 1px,transparent 1px);background-size:56px 56px;opacity:.28;z-index:1}
        .hero-bg{position:absolute;inset:0;z-index:0;display:flex;justify-content:flex-end;align-items:stretch}
        .hero-photo{width:min(760px,56vw);height:100%;position:relative}
        .hero-photo img{width:100%;height:100%;object-fit:cover;object-position:center;display:block;filter:saturate(1.08) contrast(1.04)}
        .hero-photo::before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(2,6,23,.36) 0%,rgba(2,6,23,.04) 38%,rgba(2,6,23,.08) 100%)}
        .hero-photo::after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 50% 36%,rgba(255,106,0,.18),transparent 36%)}
        .hero-content{position:relative;z-index:2;width:min(760px,100%);padding:76px 0}
        .eyebrow{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border:1px solid rgba(249,115,22,.48);background:rgba(249,115,22,.12);color:#fed7aa;border-radius:999px;font-size:14px;font-weight:900;margin-bottom:24px}
        .hero-title{font-size:clamp(42px,6.4vw,82px);line-height:.96;letter-spacing:0;font-weight:1000;color:#fff;margin:0;max-width:760px}
        .brand-text{color:#ff8a00}
        .hero-desc{font-size:18px;line-height:1.75;color:#cbd5e1;max-width:650px;margin:24px 0 0}
        .hero-actions{display:flex;flex-wrap:wrap;gap:14px;margin-top:32px}
        .trust-line{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px;color:#cbd5e1;font-size:14px}
        .trust-line span{display:inline-flex;align-items:center;gap:7px;background:rgba(15,23,42,.82);border:1px solid rgba(148,163,184,.16);border-radius:999px;padding:8px 12px}
        .section{padding:76px 0;border-bottom:1px solid rgba(148,163,184,.12)}
        .section-kicker{color:#fb923c;font-weight:950;font-size:14px;text-transform:uppercase;letter-spacing:.08em}
        .section-title{font-size:clamp(30px,4vw,52px);line-height:1.08;font-weight:1000;color:#fff;margin:10px 0 0;letter-spacing:0;max-width:820px}
        .section-desc{color:#94a3b8;font-size:17px;line-height:1.75;max-width:760px;margin:16px 0 0}
        .grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:34px}
        .grid-4{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:34px}
        .card{background:rgba(15,23,42,.88);border:1px solid rgba(148,163,184,.14);border-radius:18px;padding:22px}
        .card:hover{border-color:rgba(249,115,22,.48)}
        .icon{width:44px;height:44px;border-radius:14px;background:#ff6a00;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:1000;margin-bottom:18px}
        .card h3{color:#fff;font-size:21px;font-weight:950;margin:0}
        .card p{color:#94a3b8;line-height:1.65;margin:10px 0 0}
        .proof{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:34px}
        .proof-list{display:grid;gap:12px}
        .proof-item{display:grid;grid-template-columns:auto 1fr;gap:12px;align-items:start;background:rgba(15,23,42,.88);border:1px solid rgba(148,163,184,.14);border-radius:16px;padding:18px}
        .check{width:26px;height:26px;border-radius:99px;background:rgba(34,197,94,.16);color:#86efac;display:flex;align-items:center;justify-content:center;font-weight:1000}
        .proof-item strong{display:block;color:#fff}
        .proof-item span{display:block;color:#94a3b8;margin-top:4px;line-height:1.55}
        .workflow{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:34px}
        .step{position:relative;background:#0f172a;border:1px solid rgba(148,163,184,.14);border-radius:18px;padding:20px}
        .step-num{color:#fb923c;font-weight:1000;font-size:14px;margin-bottom:14px}
        .step h3{color:#fff;font-size:18px;font-weight:950;margin:0}
        .step p{color:#94a3b8;line-height:1.62;margin:9px 0 0}
        .price-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:34px}
        .price-card{background:rgba(15,23,42,.9);border:1px solid rgba(148,163,184,.14);border-radius:20px;padding:24px}
        .price-card.highlight{border-color:rgba(255,106,0,.7);box-shadow:0 24px 72px rgba(255,106,0,.14)}
        .price-card h3{font-size:25px;font-weight:1000;color:#fff;margin:0}
        .price{font-size:38px;font-weight:1000;color:#fff;margin-top:16px}
        .price span{font-size:14px;color:#94a3b8}
        .price-card ul{list-style:none;padding:0;margin:22px 0 0;display:grid;gap:11px;color:#cbd5e1}
        .price-card li{line-height:1.45}
        .price-card .btn{width:100%;margin-top:22px}
        .price-note{margin-top:18px;color:#94a3b8;font-size:14px;line-height:1.6}
        .testimonials{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:34px}
        .testimonial{background:#0f172a;border:1px solid rgba(148,163,184,.14);border-radius:20px;padding:24px}
        .stars{color:#fbbf24;letter-spacing:2px;font-weight:1000;margin-bottom:16px}
        .quote{color:#e2e8f0;line-height:1.75;margin:0}
        .person{margin-top:20px;border-top:1px solid rgba(148,163,184,.12);padding-top:16px}
        .person strong{display:block;color:#fff}
        .person span{display:block;color:#94a3b8;margin-top:4px;font-size:14px}
        .faq{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:34px}
        .faq-item{background:rgba(15,23,42,.88);border:1px solid rgba(148,163,184,.14);border-radius:18px;padding:22px}
        .faq-item h3{color:#fff;font-weight:950;font-size:18px;margin:0}
        .faq-item p{color:#94a3b8;line-height:1.7;margin:10px 0 0}
        .cta{padding:76px 0}
        .cta-band{background:linear-gradient(135deg,#ff6a00,#f97316);border-radius:26px;padding:40px;display:grid;grid-template-columns:1fr auto;gap:24px;align-items:center;color:#fff;box-shadow:0 28px 80px rgba(249,115,22,.22)}
        .cta-band h2{font-size:clamp(30px,4vw,48px);line-height:1.05;font-weight:1000;margin:0}
        .cta-band p{font-size:17px;line-height:1.65;margin:12px 0 0;color:#fff7ed;max-width:720px}
        .cta-band .btn{background:#020617;color:#fff;box-shadow:none}
        .footer{border-top:1px solid rgba(148,163,184,.12);padding:30px 0;color:#64748b;font-size:14px}
        .footer-inner{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
        .footer-links{display:flex;gap:14px;flex-wrap:wrap}
        .footer-links a{color:#94a3b8}
        .footer-links a:hover{color:#fdba74}
        @media(max-width:980px){
            .hero{min-height:auto}
            .hero-bg{opacity:.72}
            .hero-photo{width:100%}
            .hero-content{padding:58px 0}
            .grid-3,.grid-4,.proof,.workflow,.price-grid,.testimonials,.faq{grid-template-columns:1fr 1fr}
            .cta-band{grid-template-columns:1fr}
        }
        @media(max-width:640px){
            .container{width:min(100% - 24px,1160px)}
            .header-inner{min-height:62px}
            .brand-logo{width:min(132px,42vw)}
            .nav-link{display:none}
            .btn{padding:9px 12px;font-size:13px;min-height:38px}
            .hero{display:flex;flex-direction:column;align-items:stretch;min-height:auto}
            .hero::before{background:linear-gradient(180deg,rgba(2,6,23,.94),rgba(2,6,23,.84));z-index:0}
            .hero::after{background-size:34px 34px;opacity:.16;z-index:1}
            .hero>.container{position:relative;z-index:3;order:1}
            .hero-bg{position:relative;inset:auto;display:block;order:2;z-index:2;opacity:1;padding:0 18px 28px}
            .hero-photo{width:min(100%,360px);height:auto;margin:0 auto;border:1px solid rgba(148,163,184,.16);border-radius:18px;overflow:hidden;box-shadow:0 14px 30px rgba(0,0,0,.38)}
            .hero-photo img{height:auto;aspect-ratio:4/5;object-fit:cover;object-position:center top}
            .hero-photo::before{background:linear-gradient(180deg,transparent 70%,rgba(2,6,23,.22))}
            .hero-photo::after{display:none}
            .hero-content{padding:30px 0 20px}
            .eyebrow{display:flex;width:100%;align-items:flex-start;border-radius:14px;font-size:11px;line-height:1.4;margin-bottom:14px;padding:8px 10px}
            .hero-title{font-size:30px;line-height:1.08;font-weight:950}
            .hero-desc{font-size:14px;line-height:1.6;margin-top:14px}
            .hero-actions{display:grid;grid-template-columns:1fr;gap:10px;margin-top:22px}
            .hero-actions .btn{width:100%;white-space:normal;text-align:center}
            .trust-line{display:grid;grid-template-columns:1fr;gap:7px;margin-top:14px;font-size:12px}
            .trust-line span{width:100%;border-radius:12px;padding:8px 10px}
            .section{padding:36px 0}
            .section-kicker{font-size:11px}
            .section-title{font-size:24px;line-height:1.16;font-weight:950}
            .section-desc{font-size:14px;line-height:1.62;margin-top:10px}
            .grid-3,.grid-4,.proof,.workflow,.price-grid,.testimonials,.faq{grid-template-columns:1fr}
            .grid-3,.grid-4,.proof,.workflow,.price-grid,.testimonials,.faq{gap:10px;margin-top:18px}
            .card,.testimonial,.faq-item,.price-card{border-radius:14px;padding:15px}
            .icon{width:34px;height:34px;border-radius:10px;margin-bottom:12px;font-size:13px}
            .card h3{font-size:17px}
            .card p,.proof-item span,.step p,.faq-item p,.quote{font-size:13px;line-height:1.58}
            .proof-item{grid-template-columns:1fr;gap:8px;padding:14px;border-radius:14px}
            .proof-item strong{font-size:14px}
            .check{width:22px;height:22px;font-size:12px}
            .workflow{gap:10px}
            .step{padding:15px;border-radius:14px}
            .step-num{font-size:12px;margin-bottom:10px}
            .step h3{font-size:16px}
            .price-card h3{font-size:21px}
            .price{font-size:28px;margin-top:10px}
            .price-card ul{margin-top:16px;gap:8px;font-size:13px}
            .testimonials{margin-top:18px}
            .stars{margin-bottom:10px}
            .person{margin-top:14px;padding-top:12px}
            .faq-item h3{font-size:16px}
            .cta{padding:36px 0}
            .cta-band{padding:18px;border-radius:16px;gap:14px}
            .cta-band h2{font-size:23px;line-height:1.14}
            .cta-band p{font-size:14px;line-height:1.58}
            .cta-band .btn{width:100%}
            .footer{padding:20px 0;font-size:13px}
            .footer-inner{display:grid;gap:14px}
            .footer-links{gap:10px 14px}
        }
        @media(max-width:380px){
            .container{width:min(100% - 20px,1160px)}
            .brand-logo{width:124px}
            .hero-title{font-size:28px}
            .eyebrow{font-size:10.5px}
            .hero-desc{font-size:13.5px}
            .hero-photo{width:min(100%,310px)}
            .section-title{font-size:22px}
            .price{font-size:26px}
        }
    </style>
</head>
<body>
<div class="page">
    <header class="header">
        <div class="container header-inner">
            <a href="<?= h(app_url('index.php')) ?>" aria-label="<?= h($appName) ?>">
                <img src="<?= h($logoFull) ?>" alt="<?= h($appName) ?>" class="brand-logo">
            </a>
            <nav class="nav" aria-label="Navigasi utama">
                <a href="#fitur" class="nav-link">Fitur</a>
                <a href="#harga" class="nav-link">Harga</a>
                <a href="#testimoni" class="nav-link">Testimoni</a>
                <a href="<?= h(public_page_link('tentang')) ?>" class="nav-link">Tentang</a>
                <a href="<?= h(app_url('auth/login.php')) ?>" class="btn btn-secondary">Login</a>
                <a href="<?= h(app_url('registrasi.php')) ?>" class="btn btn-primary">Daftar</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="hero-bg" aria-hidden="true">
                <div class="hero-photo">
                    <img src="<?= h($heroImage) ?>" alt="">
                </div>
            </div>

            <div class="container">
                <div class="hero-content">
                    <div class="eyebrow">Aplikasi kasir online untuk toko, warung, dan grosir kecil</div>
                    <h1 class="hero-title">
                        <span class="brand-text">Transaksi Rapi,</span> Usaha Lebih Pasti
                    </h1>
                    <p class="hero-desc">
                        Aplikasi kasir online untuk toko, warung, dan grosir kecil. Kelola transaksi, stok, piutang, laporan, hingga absensi karyawan dalam satu sistem.
                    </p>
                    <div class="hero-actions">
                        <a href="<?= h(app_url('registrasi.php')) ?>" class="btn btn-primary">Daftar Gratis</a>
                        <a href="<?= h(app_url('auth/login.php')) ?>" class="btn btn-secondary">Login</a>
                        <a href="#fitur" class="btn btn-secondary">Lihat Fitur Unggulan</a>
                    </div>
                    <div class="trust-line">
                        <span>&check; Cocok untuk toko harian</span>
                        <span>&check; Multi role karyawan</span>
                        <span>&check; Pantau stok dan piutang</span>
                    </div>
                </div>
            </div>
        </section>

        <section id="fitur" class="section">
            <div class="container">
                <div class="section-kicker">Fitur Utama</div>
                <h2 class="section-title">Semua kebutuhan operasional toko dalam satu sistem kasir online</h2>
                <p class="section-desc">
                    Dari transaksi cepat di meja kasir sampai laporan owner, KasirRapi dirancang untuk membantu usaha berjalan lebih tertib tanpa rekap manual yang melelahkan.
                </p>
                <div class="grid-3">
                    <article class="card">
                        <div class="icon">01</div>
                        <h3>Kasir Online Cepat</h3>
                        <p>Proses penjualan dengan metode Tunai, QRIS, Transfer, dan Hutang. Cocok untuk kasir toko yang butuh alur transaksi sederhana.</p>
                    </article>
                    <article class="card">
                        <div class="icon">02</div>
                        <h3>Stok & Barang</h3>
                        <p>Kelola stok gudang, harga grosir dan eceran, supplier, barcode, pembelian barang, serta peringatan stok menipis.</p>
                    </article>
                    <article class="card">
                        <div class="icon">03</div>
                        <h3>Piutang Pelanggan</h3>
                        <p>Catat transaksi hutang, pembayaran piutang, sisa tagihan, dan histori pelanggan agar cashflow toko lebih mudah dipantau.</p>
                    </article>
                    <article class="card">
                        <div class="icon">04</div>
                        <h3>Absensi Wajah</h3>
                        <p>Karyawan dapat melakukan absensi masuk dan pulang dengan deteksi wajah, lengkap dengan pengajuan izin dan rekap kehadiran.</p>
                    </article>
                    <article class="card">
                        <div class="icon">05</div>
                        <h3>Gaji Karyawan</h3>
                        <p>Rekap payroll berdasarkan data absensi, telat, lembur, izin, cuti, dan sakit sehingga proses gaji lebih terstruktur.</p>
                    </article>
                    <article class="card">
                        <div class="icon">06</div>
                        <h3>Laporan Owner</h3>
                        <p>Pantau omzet, transaksi, stok, piutang, dan performa toko melalui laporan ringkas dan detail untuk keputusan yang lebih cepat.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="container">
                <div class="section-kicker">Kenapa KasirRapi</div>
                <h2 class="section-title">Dibuat untuk cara kerja toko di Indonesia</h2>
                <p class="section-desc">
                    KasirRapi tidak hanya mencatat penjualan. Sistem ini membantu owner melihat kondisi toko harian dengan data yang mudah dibaca.
                </p>
                <div class="proof">
                    <div class="proof-list">
                        <div class="proof-item">
                            <div class="check">&check;</div>
                            <div><strong>Lebih sedikit rekap manual</strong><span>Transaksi, stok, dan piutang dicatat langsung dari alur kerja toko.</span></div>
                        </div>
                        <div class="proof-item">
                            <div class="check">&check;</div>
                            <div><strong>Akses sesuai role</strong><span>Owner, admin, kasir, gudang, dan HRD dapat bekerja sesuai hak akses masing-masing.</span></div>
                        </div>
                    </div>
                    <div class="proof-list">
                        <div class="proof-item">
                            <div class="check">&check;</div>
                            <div><strong>Lebih mudah membaca kondisi toko</strong><span>Owner bisa melihat transaksi hari ini, omzet, stok habis, stok menipis, dan menu penting dari dashboard.</span></div>
                        </div>
                        <div class="proof-item">
                            <div class="check">&check;</div>
                            <div><strong>Siap untuk usaha yang bertumbuh</strong><span>Mulai dari toko kecil sampai grosir dengan banyak karyawan dan kebutuhan laporan lebih lengkap.</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="container">
                <div class="section-kicker">Alur Kerja</div>
                <h2 class="section-title">Dari transaksi sampai laporan, semuanya mengalir lebih rapi</h2>
                <div class="workflow">
                    <article class="step">
                        <div class="step-num">Langkah 01</div>
                        <h3>Input barang</h3>
                        <p>Masukkan data produk, stok, satuan, harga eceran, harga grosir, dan barcode.</p>
                    </article>
                    <article class="step">
                        <div class="step-num">Langkah 02</div>
                        <h3>Transaksi kasir</h3>
                        <p>Kasir melakukan penjualan dan memilih metode bayar sesuai kebutuhan pelanggan.</p>
                    </article>
                    <article class="step">
                        <div class="step-num">Langkah 03</div>
                        <h3>Pantau operasional</h3>
                        <p>Owner melihat stok, piutang, absensi, dan performa toko dari dashboard.</p>
                    </article>
                    <article class="step">
                        <div class="step-num">Langkah 04</div>
                        <h3>Baca laporan</h3>
                        <p>Gunakan laporan untuk mengevaluasi omzet, barang, dan kebutuhan bisnis berikutnya.</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="harga" class="section">
            <div class="container">
                <div class="section-kicker">Paket Harga</div>
                <h2 class="section-title">Pilih paket KasirRapi sesuai tahap pertumbuhan toko Anda</h2>
                <p class="section-desc">Mulai dari kebutuhan kasir dasar sampai operasional lengkap dengan absensi dan gaji karyawan.</p>
                <div class="price-grid">
                    <article class="price-card">
                        <h3>Gratis</h3>
                        <div class="price">Rp0<span>/bulan</span></div>
                        <ul>
                            <li>&check; 1 user Owner</li>
                            <li>&check; 100 barang aktif</li>
                            <li>&check; 100 transaksi/bulan</li>
                            <li>&check; Kasir dan stok dasar</li>
                        </ul>
                        <a href="<?= h(app_url('registrasi.php?paket=Gratis')) ?>" class="btn btn-secondary">Mulai Gratis</a>
                    </article>
                    <article class="price-card">
                        <h3>Basic</h3>
                        <div class="price">Rp 49rb<span>/bulan</span></div>
                        <ul>
                            <li>&check; 2 user</li>
                            <li>&check; Kasir penjualan</li>
                            <li>&check; 500 barang aktif</li>
                            <li>&check; Transaksi unlimited</li>
                        </ul>
                        <a href="<?= h(app_url('registrasi.php?paket=Basic')) ?>" class="btn btn-secondary">Pilih Basic</a>
                    </article>
                    <article class="price-card highlight">
                        <h3>Plus</h3>
                        <div class="price">Rp 79rb<span>/bulan</span></div>
                        <ul>
                            <li>&check; Semua fitur Basic</li>
                            <li>&check; Piutang pelanggan</li>
                            <li>&check; Supplier dan pembelian</li>
                            <li>&check; Export dan custom struk</li>
                        </ul>
                        <a href="<?= h(app_url('registrasi.php?paket=Plus')) ?>" class="btn btn-primary">Pilih Plus</a>
                    </article>
                    <article class="price-card">
                        <h3>Pro</h3>
                        <div class="price">Rp 129rb<span>/bulan</span></div>
                        <ul>
                            <li>&check; Semua fitur Plus</li>
                            <li>&check; Laporan detail</li>
                            <li>&check; Audit harga dan stok</li>
                            <li>&check; Support prioritas</li>
                        </ul>
                        <a href="<?= h(app_url('registrasi.php?paket=Pro')) ?>" class="btn btn-secondary">Pilih Pro</a>
                    </article>
                </div>
                <p class="price-note">Absensi & Gaji tersedia sebagai add-on untuk paket Plus dan Pro. Trial Plus 14 hari dapat diaktifkan dari halaman registrasi.</p>
            </div>
        </section>

        <section id="testimoni" class="section">
            <div class="container">
                <div class="section-kicker">Testimoni</div>
                <h2 class="section-title">Dipercaya untuk membuat pencatatan toko lebih tertib</h2>
                <p class="section-desc">Beberapa cerita penggunaan KasirRapi dari pelaku usaha yang ingin mengurangi rekap manual dan membaca data toko lebih cepat.</p>
                <div class="testimonials">
                    <?php foreach ($testimonials as $testimonial): ?>
                        <article class="testimonial">
                            <div class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
                            <p class="quote">"<?= h($testimonial['quote']) ?>"</p>
                            <div class="person">
                                <strong><?= h($testimonial['name']) ?></strong>
                                <span><?= h($testimonial['business']) ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="container">
                <div class="section-kicker">FAQ</div>
                <h2 class="section-title">Pertanyaan yang sering ditanyakan tentang aplikasi kasir online KasirRapi</h2>
                <div class="faq">
                    <?php foreach ($faqs as $faq): ?>
                        <article class="faq-item">
                            <h3><?= h($faq['question']) ?></h3>
                            <p><?= h($faq['answer']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="cta">
            <div class="container">
                <div class="cta-band">
                    <div>
                        <h2>Siap membuat operasional toko lebih rapi?</h2>
                        <p>Masuk ke KasirRapi dan mulai kelola transaksi, stok, piutang, karyawan, dan laporan dalam satu aplikasi.</p>
                    </div>
                    <a href="<?= h(app_url('registrasi.php')) ?>" class="btn">Daftar Gratis</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container footer-inner">
            <div>&copy; <?= date('Y') ?> <?= h($appName) ?>. <?= h($appTagline) ?></div>
            <nav class="footer-links" aria-label="Link informasi">
                <?php foreach (public_nav_links() as $link): ?>
                    <a href="<?= h($link['url']) ?>"><?= h($link['label']) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </footer>
</div>
</body>
</html>
