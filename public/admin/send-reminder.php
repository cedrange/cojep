<?php
require_once __DIR__ . '/inc/auth.php';
cojep_admin_require_login();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Méthode non autorisée');
}

// Un envoi groupé peut dépasser le max_execution_time par défaut du mutualisé.
@set_time_limit(0);
ignore_user_abort(true);

cojep_ensure_reminder_column();
$pdo = cojep_db();

$mode = (string) ($_POST['mode'] ?? '');
$search = (string) ($_POST['q'] ?? '');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$siteUrl = ($isHttps ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$markSent = function (array $registration) use ($pdo): void {
    if (!empty($registration['id'])) {
        $pdo->prepare('UPDATE registrations SET rappel_envoye_at = NOW() WHERE id = :id')
            ->execute(['id' => (int) $registration['id']]);
    }
};

$flash = null;

if ($mode === 'test') {
    $email = trim((string) ($_POST['test_email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $flash = ['type' => 'error', 'text' => 'Adresse email de test invalide.'];
    } else {
        $result = cojep_send_reminder_emails(
            [['id' => 0, 'prenom' => 'Test', 'nom' => '', 'email' => $email, 'nombre_personnes' => 1]],
            $siteUrl
        );
        $flash = $result['sent'] === 1
            ? ['type' => 'success', 'text' => "Mail de test envoyé à {$email}."]
            : ['type' => 'error', 'text' => $result['error'] ?? "Échec de l'envoi du mail de test à {$email}."];
    }
} elseif ($mode === 'pending' || $mode === 'single') {
    if ($mode === 'pending') {
        $registrations = $pdo->query('SELECT * FROM registrations WHERE rappel_envoye_at IS NULL ORDER BY id')->fetchAll();
    } else {
        $stmt = $pdo->prepare('SELECT * FROM registrations WHERE id = :id');
        $stmt->execute(['id' => (int) ($_POST['id'] ?? 0)]);
        $registrations = $stmt->fetchAll();
    }

    if (!$registrations) {
        $flash = ['type' => 'info', 'text' => 'Aucun inscrit à relancer.'];
    } else {
        $result = cojep_send_reminder_emails($registrations, $siteUrl, $markSent);

        if ($result['error'] !== null) {
            $flash = ['type' => 'error', 'text' => $result['error']];
        } elseif ($result['failed']) {
            $flash = [
                'type' => 'error',
                'text' => "{$result['sent']} rappel(s) envoyé(s), " . count($result['failed']) . ' échec(s) : ' . implode(', ', $result['failed']),
            ];
        } else {
            $flash = ['type' => 'success', 'text' => "{$result['sent']} rappel(s) envoyé(s) avec succès."];
        }
    }
} else {
    $flash = ['type' => 'error', 'text' => 'Action inconnue.'];
}

$_SESSION['flash'] = $flash;

$redirect = 'dashboard.php';
if ($search !== '') {
    $redirect .= '?q=' . urlencode($search);
}

header('Location: ' . $redirect);
exit;
