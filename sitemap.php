<?php
require_once __DIR__ . '/includes/public-pages.php';

$pages = [
    ['loc' => '', 'priority' => '1.0', 'changefreq' => 'weekly'],
    ['loc' => 'tentang', 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['loc' => 'kontak', 'priority' => '0.7', 'changefreq' => 'monthly'],
    ['loc' => 'kebijakan-privasi', 'priority' => '0.5', 'changefreq' => 'yearly'],
    ['loc' => 'syarat-ketentuan', 'priority' => '0.5', 'changefreq' => 'yearly'],
    ['loc' => 'disclaimer', 'priority' => '0.5', 'changefreq' => 'yearly'],
];

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($pages as $page): ?>
    <url>
        <loc><?= h(public_absolute_url($page['loc'])) ?></loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq><?= h($page['changefreq']) ?></changefreq>
        <priority><?= h($page['priority']) ?></priority>
    </url>
<?php endforeach; ?>
</urlset>
