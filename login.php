<?php
require_once __DIR__ . '/config/app.php';
header('Location: ' . app_url('auth/login.php'));
exit;
