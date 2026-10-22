<?php
require_once __DIR__ . '/inc/auth.php';
cojep_admin_require_login();

require_once __DIR__ . '/../inc/db.php';

$rows = cojep_db()->query('SELECT * FROM registrations ORDER BY created_at DESC')->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="inscriptions-cafe-politique.csv"');

echo "\xEF\xBB\xBF"; // BOM UTF-8

$out = fopen('php://output', 'w');
$header = ['id', 'prenom', 'nom', 'email', 'telephone', 'nombre_personnes', 'message', 'email_envoye', 'created_at'];
fputcsv($out, $header);

foreach ($rows as $row) {
    fputcsv($out, array_map(fn ($col) => $row[$col] ?? '', $header));
}

fclose($out);
