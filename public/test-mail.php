<?php
// Script de diagnostic temporaire — à SUPPRIMER du serveur une fois le problème résolu.
declare(strict_types=1);

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

header('Content-Type: text/plain; charset=utf-8');

$to = $_GET['to'] ?? '';
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo "Utilisation : test-mail.php?to=votre-email@exemple.com\n";
    exit;
}

$smtp = cojep_config()['smtp'];

echo "--- Configuration SMTP lue depuis config.php ---\n";
echo "host         : " . ($smtp['host'] ?: '(vide)') . "\n";
echo "port         : " . ($smtp['port'] ?: '(vide)') . "\n";
echo "secure       : " . ($smtp['secure'] ?: '(vide)') . "\n";
echo "user         : " . ($smtp['user'] ?: '(vide)') . "\n";
echo "pass         : " . (empty($smtp['pass']) ? '(vide)' : '(renseigné, ' . strlen($smtp['pass']) . ' caractères)') . "\n";
echo "from_address : " . ($smtp['from_address'] ?: '(vide, utilisera user)') . "\n\n";

if (empty($smtp['host']) || empty($smtp['user']) || empty($smtp['pass'])) {
    echo "=> host/user/pass manquant(s) dans config.php : c'est pour ça qu'aucun email ne part.\n";
    exit;
}

$mail = new PHPMailer(true);
$mail->SMTPDebug = 2; // affiche le dialogue SMTP complet
$mail->Debugoutput = function ($str, $level) {
    echo "[SMTP] " . trim($str) . "\n";
};

try {
    $mail->isSMTP();
    $mail->Host = $smtp['host'];
    $mail->Port = (int) $smtp['port'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp['user'];
    $mail->Password = $smtp['pass'];
    if (!empty($smtp['secure'])) {
        $mail->SMTPSecure = $smtp['secure'];
    }
    $mail->CharSet = 'UTF-8';
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];

    $fromAddress = $smtp['from_address'] ?: $smtp['user'];
    $mail->setFrom($fromAddress, $smtp['from_name'] ?: 'COJEP');
    $mail->addAddress($to);
    $mail->Subject = 'Test envoi COJEP';
    $mail->Body = 'Ceci est un email de test depuis test-mail.php.';

    echo "\n--- Tentative d'envoi ---\n";
    $mail->send();
    echo "\n=> SUCCÈS : email envoyé à {$to}.\n";
} catch (PHPMailerException $e) {
    echo "\n=> ÉCHEC : " . $mail->ErrorInfo . "\n";
}
