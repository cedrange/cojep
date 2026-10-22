<?php
// Script de diagnostic temporaire — à SUPPRIMER du serveur une fois le problème résolu.
declare(strict_types=1);

require_once __DIR__ . '/inc/config.php';

$admin = cojep_config()['admin'];
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testPassword = (string) ($_POST['password'] ?? '');
    $result = password_verify($testPassword, $admin['password_hash'] ?? '');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>Test admin</title></head>
<body style="font-family: monospace; max-width: 700px; margin: 40px auto; white-space: pre-wrap;">
--- Configuration admin lue depuis config.php ---
username      : '<?= htmlspecialchars($admin['username']) ?>' (longueur : <?= strlen($admin['username']) ?>)
password_hash : '<?= htmlspecialchars($admin['password_hash'] ?? '') ?>'
longueur hash : <?= strlen($admin['password_hash'] ?? '') ?> caractères

<?php if ($result !== null): ?>
--- Résultat du test ---
<?= $result ? '=> SUCCÈS : ce mot de passe correspond au hash.' : '=> ÉCHEC : ce mot de passe NE correspond PAS au hash.' ?>
<?php endif; ?>

<form method="post" style="margin-top: 20px;">
  <label>Mot de passe à tester : <input type="text" name="password" style="width: 300px;" value="<?= htmlspecialchars($_POST['password'] ?? '') ?>"></label>
  <button type="submit">Tester</button>
</form>
</body>
</html>
