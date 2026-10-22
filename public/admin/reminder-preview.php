<?php
require_once __DIR__ . '/inc/auth.php';
cojep_admin_require_login();

require_once __DIR__ . '/../inc/mailer.php';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$siteUrl = ($isHttps ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

header('Content-Type: text/html; charset=utf-8');
echo cojep_reminder_email_html(
    ['prenom' => 'Aminata', 'nom' => 'Exemple', 'email' => '', 'nombre_personnes' => 2],
    $siteUrl,
    '/assets/vector.png'
);
