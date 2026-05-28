<?php
// includes/live-chat.php
// Tag global untuk widget live chat KasirRapi berbasis Gemini API.

if (file_exists(__DIR__ . '/../config/app.php')) {
    require_once __DIR__ . '/../config/app.php';
}
require_once __DIR__ . '/live-chat-knowledge.php';

if (!function_exists('live_chat_h')) {
    function live_chat_h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('live_chat_head_tags')) {
    function live_chat_head_tags() {
        $avatarFiles = kasirrapi_live_chat_cs_avatar_files();
        $avatars = [];
        foreach ($avatarFiles as $name => $file) {
            $avatars[$name] = asset_url($file);
        }

        $config = [
            'appName' => defined('APP_NAME') ? APP_NAME : 'KasirRapi',
            'endpoint' => app_url('api/live-chat.php'),
            'escalationEndpoint' => app_url('api/live-chat-escalate.php'),
            'csNames' => kasirrapi_live_chat_cs_names(),
            'avatars' => $avatars,
            'avatarUrl' => asset_url('app/cs-photo-nadia.jpg'),
            'launcherImageUrl' => asset_url('app/live-chat-cs.png'),
            'collapsedIconUrl' => asset_url('app/live-chat-icon.svg'),
            'assetVersion' => defined('APP_VERSION') ? APP_VERSION : '1.0.0',
        ];

        echo '<link rel="stylesheet" href="' . live_chat_h(asset_url('app/live-chat.css')) . '?v=' . live_chat_h($config['assetVersion']) . '">
<script>window.KasirRapiLiveChat = ' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>
<script defer src="' . live_chat_h(asset_url('app/live-chat.js')) . '?v=' . live_chat_h($config['assetVersion']) . '"></script>';
    }
}
