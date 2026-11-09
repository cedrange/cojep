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
$campaignKey = (string) ($_POST['campaign'] ?? 'rappel');
$campaign = cojep_campaign($campaignKey);
if ($campaign === null) {
    http_response_code(400);
    exit('Campagne inconnue');
}
$column = $campaign['column'];
$label = $campaign['label'];
$search = (string) ($_POST['q'] ?? '');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$siteUrl = ($isHttps ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$markSent = function (array $registration) use ($pdo, $column): void {
    if (!empty($registration['id'])) {
        $pdo->prepare("UPDATE registrations SET {$column} = NOW() WHERE id = :id")
            ->execute(['id' => (int) $registration['id']]);
    }
};

$flash = null;

if ($mode === 'test') {
    $email = trim((string) ($_POST['test_email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $flash = ['type' => 'error', 'text' => 'Adresse email de test invalide.'];
    } else {
        $result = cojep_send_campaign_emails(
            $campaignKey,
            [['id' => 0, 'prenom' => 'Test', 'nom' => '', 'email' => $email, 'nombre_personnes' => 1]],
            $siteUrl,
            null,
            true
        );
        $flash = $result['sent'] === 1
            ? ['type' => 'success', 'text' => "Mail de test ({$label}) accepté par le serveur SMTP pour {$email}. S'il n'arrive pas, vérifiez les spams : un accusé de livraison avec la réponse du destinataire arrivera aussi dans la boîte d'expédition."]
            : ['type' => 'error', 'text' => $result['error'] ?? "Échec de l'envoi du mail de test à {$email}."];
        $flash['log'] = $result['log'];
    }
} elseif ($mode === 'pending' || $mode === 'single') {
    if ($mode === 'pending') {
        $registrations = $pdo->query("SELECT * FROM registrations WHERE {$column} IS NULL ORDER BY id")->fetchAll();
    } else {
        $stmt = $pdo->prepare('SELECT * FROM registrations WHERE id = :id');
        $stmt->execute(['id' => (int) ($_POST['id'] ?? 0)]);
        $registrations = $stmt->fetchAll();
    }

    if (!$registrations) {
        $flash = ['type' => 'info', 'text' => "Aucun inscrit en attente de ce mail ({$label})."];
    } else {
        $result = cojep_send_campaign_emails($campaignKey, $registrations, $siteUrl, $markSent);

        if ($result['error'] !== null) {
            $flash = ['type' => 'error', 'text' => $result['error']];
        } elseif ($result['failed']) {
            $flash = [
                'type' => 'error',
                'text' => "{$result['sent']} mail(s) de {$label} envoyé(s), " . count($result['failed']) . ' échec(s) : ' . implode(', ', $result['failed']),
            ];
        } else {
            $flash = ['type' => 'success', 'text' => "{$result['sent']} mail(s) de {$label} envoyé(s) avec succès."];
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
