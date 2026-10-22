<?php
require_once __DIR__ . '/inc/auth.php';
cojep_admin_require_login();

require_once __DIR__ . '/../inc/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Méthode non autorisée');
}

$id = (int) ($_POST['id'] ?? 0);
$search = (string) ($_POST['q'] ?? '');

if ($id > 0) {
    cojep_db()->prepare('DELETE FROM registrations WHERE id = :id')->execute(['id' => $id]);
}

$redirect = 'dashboard.php';
if ($search !== '') {
    $redirect .= '?q=' . urlencode($search);
}

header('Location: ' . $redirect);
exit;
