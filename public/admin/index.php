<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/../inc/ratelimit.php';
require_once __DIR__ . '/../inc/helpers.php';

if (cojep_admin_is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (cojep_rate_limit_exceeded('admin_login', 10, 15)) {
        $error = 'Trop de tentatives de connexion. Réessayez plus tard.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $admin = cojep_config()['admin'];

        if (
            $username !== '' && $password !== ''
            && hash_equals($admin['username'], $username)
            && !empty($admin['password_hash'])
            && password_verify($password, $admin['password_hash'])
        ) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            $_SESSION['username'] = $username;
            header('Location: dashboard.php');
            exit;
        }

        $error = 'Identifiants incorrects';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Connexion Admin — COJEP</title>
<link rel="icon" type="image/png" href="/assets/vector.png" />
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="admin.css" />
</head>
<body class="admin-login-body">
  <div class="login-card">
    <img src="/assets/vector.png" alt="Logo COJEP" class="login-logo" />
    <h1>Espace Admin</h1>
    <p class="login-sub">Café Politique du COJEP — Gestion des inscriptions</p>

    <form method="post" novalidate>
      <div class="form-field">
        <label for="username">Identifiant</label>
        <input type="text" id="username" name="username" autocomplete="username" required />
      </div>
      <div class="form-field">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required />
      </div>
      <button type="submit" class="btn-login">Se connecter</button>
      <?php if ($error !== ''): ?>
        <p class="login-feedback"><?= cojep_escape_html($error) ?></p>
      <?php endif; ?>
    </form>
  </div>
</body>
</html>
