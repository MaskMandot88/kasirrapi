<?php
require_once __DIR__ . '/_auth.php';

unset(
    $_SESSION['superadmin_logged_in'],
    $_SESSION['superadmin_username'],
    $_SESSION['superadmin_csrf_token']
);

session_regenerate_id(true);

header('Location: login.php');
exit;
