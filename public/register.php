<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/ratelimit.php';
require_once __DIR__ . '/inc/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

if (cojep_rate_limit_exceeded('register', 20, 15)) {
    http_response_code(429);
    echo json_encode(['error' => "Trop de tentatives d'inscription. Réessayez plus tard."]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$prenom = trim((string) ($input['prenom'] ?? ''));
$nom = trim((string) ($input['nom'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$telephone = trim((string) ($input['telephone'] ?? ''));
$message = trim((string) ($input['message'] ?? ''));
$nombrePersonnes = (int) ($input['nombre_personnes'] ?? 1);

if ($prenom === '' || mb_strlen($prenom) > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Le prénom est requis']);
    exit;
}
if ($nom === '' || mb_strlen($nom) > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Le nom est requis']);
    exit;
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    http_response_code(400);
    echo json_encode(['error' => 'Email invalide']);
    exit;
}
if (mb_strlen($telephone) > 40) {
    http_response_code(400);
    echo json_encode(['error' => 'Numéro de téléphone trop long']);
    exit;
}
if ($nombrePersonnes < 1 || $nombrePersonnes > 10) {
    http_response_code(400);
    echo json_encode(['error' => 'Le nombre de personnes doit être entre 1 et 10']);
    exit;
}
if (mb_strlen($message) > 500) {
    http_response_code(400);
    echo json_encode(['error' => 'Message trop long']);
    exit;
}

$registration = [
    'prenom' => $prenom,
    'nom' => $nom,
    'email' => $email,
    'telephone' => $telephone !== '' ? $telephone : null,
    'nombre_personnes' => $nombrePersonnes,
    'message' => $message !== '' ? $message : null,
];

$pdo = cojep_db();

try {
    $stmt = $pdo->prepare(
        'INSERT INTO registrations (prenom, nom, email, telephone, nombre_personnes, message, ip_address)
         VALUES (:prenom, :nom, :email, :telephone, :nombre_personnes, :message, :ip_address)'
    );
    $stmt->execute([
        'prenom' => $registration['prenom'],
        'nom' => $registration['nom'],
        'email' => $registration['email'],
        'telephone' => $registration['telephone'],
        'nombre_personnes' => $registration['nombre_personnes'],
        'message' => $registration['message'],
        'ip_address' => cojep_client_ip(),
    ]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['error' => 'Cette adresse email est déjà inscrite à l’événement.']);
        exit;
    }
    error_log('[register] erreur insertion : ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Une erreur est survenue, merci de réessayer.']);
    exit;
}

$registrationId = (int) $pdo->lastInsertId();

http_response_code(201);
echo json_encode(['success' => true, 'message' => 'Inscription enregistrée avec succès.']);

// Envoi de l'email après la réponse HTTP pour ne pas ralentir l'utilisateur.
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$sent = cojep_send_confirmation_email($registration);
if ($sent) {
    $pdo->prepare('UPDATE registrations SET email_envoye = 1 WHERE id = :id')
        ->execute(['id' => $registrationId]);
}
