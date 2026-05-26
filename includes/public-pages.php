<?php
require_once __DIR__ . '/../config/app.php';

if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('public_absolute_url')) {
    function public_absolute_url($path = '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host . app_url($path);
    }
}

if (!function_exists('public_page_link')) {
    function public_page_link($slug) {
        return app_url($slug);
    }
}

if (!function_exists('public_nav_links')) {
    function public_nav_links() {
        return [
            ['label' => 'Tentang', 'url' => public_page_link('tentang')],
            ['label' => 'Kontak', 'url' => public_page_link('kontak')],
            ['label' => 'Privasi', 'url' => public_page_link('kebijakan-privasi')],
            ['label' => 'Syarat', 'url' => public_page_link('syarat-ketentuan')],
            ['label' => 'Disclaimer', 'url' => public_page_link('disclaimer')],
        ];
    }
}

if (!function_exists('render_public_page')) {
    function render_public_page($page) {
        $slug = trim((string)($page['slug'] ?? ''));
        $title = trim((string)($page['title'] ?? APP_NAME));
        $description = trim((string)($page['description'] ?? APP_SEO_DESCRIPTION));
        $heading = trim((string)($page['heading'] ?? $title));
        $intro = trim((string)($page['intro'] ?? ''));
        $eyebrow = trim((string)($page['eyebrow'] ?? APP_NAME));
        $canonical = public_absolute_url($slug);
        $logo = asset_url('app/logo-full.png') . '?v=' . rawurlencode(APP_VERSION);
        $favicon = asset_url('app/favicon.png') . '?v=' . rawurlencode(APP_VERSION);

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => public_absolute_url('') . '#organization',
                    'name' => APP_NAME,
                    'url' => public_absolute_url(''),
                    'logo' => public_absolute_url('assets/app/logo-full.png'),
                    'description' => APP_SEO_DESCRIPTION,
                ],
                [
                    '@type' => 'WebPage',
                    '@id' => $canonical . '#webpage',
                    'url' => $canonical,
                    'name' => $title,
                    'description' => $description,
                    'isPartOf' => [
                        '@type' => 'WebSite',
                        '@id' => public_absolute_url('') . '#website',
                        'name' => APP_NAME,
                        'url' => public_absolute_url(''),
                    ],
                    'about' => ['@id' => public_absolute_url('') . '#organization'],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => $canonical . '#breadcrumb',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Beranda',
                            'item' => public_absolute_url(''),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => $heading,
                            'item' => $canonical,
                        ],
                    ],
                ],
            ],
        ];
        ?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?></title>
    <meta name="description" content="<?= h($description) ?>">
    <meta name="robots" content="<?= h($page['robots'] ?? 'index,follow') ?>">
    <meta name="theme-color" content="#FF6A00">
    <link rel="canonical" href="<?= h($canonical) ?>">
    <link rel="icon" type="image/png" href="<?= h($favicon) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= h(APP_NAME) ?>">
    <meta property="og:title" content="<?= h($title) ?>">
    <meta property="og:description" content="<?= h($description) ?>">
    <meta property="og:url" content="<?= h($canonical) ?>">
    <meta property="og:image" content="<?= h(public_absolute_url('assets/app/logo-full.png')) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= h(asset_url('app/app-ui.css')) ?>?v=<?= h(APP_VERSION) ?>">
    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <style>
        body{min-height:100vh;background:radial-gradient(circle at top left,rgba(255,106,0,.14),transparent 30rem),radial-gradient(circle at bottom right,rgba(255,138,0,.08),transparent 28rem),#020617;color:#e2e8f0}
        .site-wrap{min-height:100vh;display:flex;flex-direction:column}
        .brand-logo{width:min(190px,48vw);height:auto}
        .content-card{background:rgba(15,23,42,.86);border:1px solid rgba(148,163,184,.14);border-radius:24px}
        .legal-copy p{color:#cbd5e1;line-height:1.8;margin-top:12px}
        .legal-copy ul{color:#cbd5e1;line-height:1.8;margin-top:12px;padding-left:20px;list-style:disc}
        .legal-copy h2{color:#fff;font-size:1.35rem;font-weight:900;margin-top:34px}
        .footer-link{color:#94a3b8}
        .footer-link:hover{color:#fdba74}
    </style>
</head>
<body>
<div class="site-wrap">
    <header class="sticky top-0 z-30 bg-slate-950/90 backdrop-blur border-b border-slate-800">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between gap-4">
            <a href="<?= h(app_url('index.php')) ?>" aria-label="<?= h(APP_NAME) ?>">
                <img src="<?= h($logo) ?>" alt="<?= h(APP_NAME) ?>" class="brand-logo">
            </a>
            <nav class="hidden md:flex items-center gap-5 text-sm font-bold">
                <a href="<?= h(app_url('index.php#fitur')) ?>" class="text-slate-300 hover:text-orange-300">Fitur</a>
                <a href="<?= h(public_page_link('tentang')) ?>" class="text-slate-300 hover:text-orange-300">Tentang</a>
                <a href="<?= h(public_page_link('kontak')) ?>" class="text-slate-300 hover:text-orange-300">Kontak</a>
                <a href="<?= h(app_url('auth/login.php')) ?>" class="btn btn-primary !w-auto">Login</a>
            </nav>
            <a href="<?= h(app_url('auth/login.php')) ?>" class="md:hidden btn btn-primary !w-auto">Login</a>
        </div>
    </header>

    <main class="flex-1">
        <section class="max-w-6xl mx-auto px-4 py-12 md:py-16">
            <div class="max-w-3xl">
                <div class="inline-flex px-3 py-2 rounded-full bg-orange-950/50 border border-orange-700 text-orange-200 text-sm font-bold mb-5">
                    <?= h($eyebrow) ?>
                </div>
                <h1 class="text-4xl md:text-5xl font-black leading-tight text-white"><?= h($heading) ?></h1>
                <?php if ($intro !== ''): ?>
                    <p class="text-lg text-slate-300 mt-5 leading-8"><?= h($intro) ?></p>
                <?php endif; ?>
            </div>

            <article class="content-card p-5 md:p-8 mt-8 legal-copy">
                <?php foreach (($page['sections'] ?? []) as $section): ?>
                    <section>
                        <h2><?= h($section['title'] ?? '') ?></h2>
                        <?php foreach (($section['paragraphs'] ?? []) as $paragraph): ?>
                            <p><?= h($paragraph) ?></p>
                        <?php endforeach; ?>
                        <?php if (!empty($section['items'])): ?>
                            <ul>
                                <?php foreach ($section['items'] as $item): ?>
                                    <li><?= h($item) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </article>
        </section>
    </main>

    <footer class="border-t border-slate-800 py-8">
        <div class="max-w-6xl mx-auto px-4 grid gap-4 md:grid-cols-[1fr_auto] md:items-center">
            <div class="text-sm text-slate-500">&copy; <?= date('Y') ?> <?= h(APP_NAME) ?>. <?= h(APP_TAGLINE) ?></div>
            <nav class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
                <?php foreach (public_nav_links() as $link): ?>
                    <a href="<?= h($link['url']) ?>" class="footer-link"><?= h($link['label']) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </footer>
</div>
</body>
</html><?php
    }
}
