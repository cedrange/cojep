<?php
require_once __DIR__ . '/db.php';

function cojep_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Retourne true si l'IP a dépassé le nombre de tentatives autorisées
 * pour une action donnée sur la fenêtre de temps indiquée (en minutes).
 */
function cojep_rate_limit_exceeded(string $action, int $maxAttempts, int $windowMinutes): bool
{
    $pdo = cojep_db();
    $ip = cojep_client_ip();

    $pdo->prepare('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL :minutes MINUTE)')
        ->execute(['minutes' => $windowMinutes]);

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS n FROM rate_limits
         WHERE ip_address = :ip AND action = :action AND created_at >= (NOW() - INTERVAL :minutes MINUTE)'
    );
    $stmt->execute(['ip' => $ip, 'action' => $action, 'minutes' => $windowMinutes]);
    $count = (int) $stmt->fetch()['n'];

    if ($count >= $maxAttempts) {
        return true;
    }

    $pdo->prepare('INSERT INTO rate_limits (ip_address, action) VALUES (:ip, :action)')
        ->execute(['ip' => $ip, 'action' => $action]);

    return false;
}
