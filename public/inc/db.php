<?php
require_once __DIR__ . '/config.php';

function cojep_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $db = cojep_config()['db'];
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $db['host'],
            $db['name'],
            $db['charset'] ?? 'utf8mb4'
        );

        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $pdo;
}

/**
 * Ajoute la colonne de suivi des rappels si la base a été créée avant cette fonctionnalité.
 */
function cojep_ensure_reminder_column(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo = cojep_db();
    $exists = $pdo->query("SHOW COLUMNS FROM registrations LIKE 'rappel_envoye_at'")->fetch();
    if (!$exists) {
        $pdo->exec('ALTER TABLE registrations ADD COLUMN rappel_envoye_at DATETIME DEFAULT NULL AFTER email_envoye');
    }

    $checked = true;
}
