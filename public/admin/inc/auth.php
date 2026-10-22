<?php
require_once __DIR__ . '/../../inc/config.php';

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 8,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $isHttps,
    ]);
    session_name('cojep_admin_sid');
    session_start();
}

function cojep_admin_require_login(): void
{
    if (empty($_SESSION['is_admin'])) {
        header('Location: index.php');
        exit;
    }
}

function cojep_admin_is_logged_in(): bool
{
    return !empty($_SESSION['is_admin']);
}
