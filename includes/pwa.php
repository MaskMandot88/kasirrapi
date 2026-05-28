<?php
// includes/pwa.php
// Tag dan konfigurasi global untuk fitur Progressive Web App KasirRapi.

if (file_exists(__DIR__ . '/../config/app.php')) {
    require_once __DIR__ . '/../config/app.php';
}

if (!function_exists('pwa_h')) {
    function pwa_h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('pwa_head_tags')) {
    function pwa_head_tags($enableInstallPrompt = false) {
        $config = [
            'appName' => defined('APP_NAME') ? APP_NAME : 'KasirRapi',
            'version' => defined('APP_VERSION') ? APP_VERSION : '1.0.0',
            'serviceWorkerUrl' => app_url('service-worker.js'),
            'installScriptUrl' => asset_url('app/pwa-install.js'),
        ];

        echo '<link rel="manifest" href="' . pwa_h(app_url('manifest.webmanifest')) . '?v=' . pwa_h($config['version']) . '">
<meta name="application-name" content="' . pwa_h($config['appName']) . '">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="' . pwa_h($config['appName']) . '">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" href="' . pwa_h(asset_url('app/icon-192.png')) . '?v=' . pwa_h($config['version']) . '">';

        if ($enableInstallPrompt) {
            echo '
<script>window.KasirRapiPwa = ' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>
<script defer src="' . pwa_h($config['installScriptUrl']) . '?v=' . pwa_h($config['version']) . '"></script>';
        }
    }
}
