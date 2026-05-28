<?php
// config/gemini.php
// Copy file ini menjadi config/gemini.php lalu isi API key Gemini dari Google AI Studio.

if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', 'ISI_API_KEY_GEMINI');
}

if (!defined('GEMINI_MODEL')) {
    define('GEMINI_MODEL', 'gemini-2.5-flash');
}

if (!defined('GEMINI_CHAT_ENABLED')) {
    define('GEMINI_CHAT_ENABLED', true);
}
