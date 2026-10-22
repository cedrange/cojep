<?php
// Copiez ce fichier en "config.php" (à la racine, au même niveau que ce fichier)
// et remplissez les valeurs avant de mettre le site en ligne.
// IMPORTANT : ne mettez jamais config.php dans un dossier accessible publiquement.

return [
    // --- Base de données MySQL (fournie par LWS / votre hébergeur) ---
    'db' => [
        'host' => 'localhost',
        'name' => 'nom_de_la_base',
        'user' => 'utilisateur',
        'pass' => 'mot_de_passe',
        'charset' => 'utf8mb4',
    ],

    // --- Compte admin (espace /admin) ---
    // Générez le hash avec : php -r "echo password_hash('votre-mot-de-passe', PASSWORD_BCRYPT), PHP_EOL;"
    'admin' => [
        'username' => 'admin',
        'password_hash' => '',
    ],

    // --- Envoi d'email (SMTP via PHPMailer) ---
    // Exemple avec Gmail : host=smtp.gmail.com, port=587, secure=tls, + un "mot de passe d'application"
    'smtp' => [
        'host' => '',
        'port' => 587,
        'secure' => 'tls', // 'tls', 'ssl' ou '' pour aucun chiffrement
        'user' => '',
        'pass' => '',
        'from_name' => 'COJEP',
        'from_address' => '',
    ],
];
