<?php
require_once __DIR__ . '/inc/auth.php';
cojep_admin_require_login();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';

$search = trim((string) ($_GET['q'] ?? ''));
$pdo = cojep_db();
cojep_ensure_reminder_column();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $pdo->prepare(
        'SELECT * FROM registrations
         WHERE prenom LIKE :s1 OR nom LIKE :s2 OR email LIKE :s3 OR telephone LIKE :s4
         ORDER BY created_at DESC'
    );
    $stmt->execute(['s1' => $like, 's2' => $like, 's3' => $like, 's4' => $like]);
} else {
    $stmt = $pdo->query('SELECT * FROM registrations ORDER BY created_at DESC');
}

$rows = $stmt->fetchAll();
$totalPersonnes = array_sum(array_column($rows, 'nombre_personnes'));

$reminderStats = $pdo->query(
    'SELECT COUNT(*) AS total, SUM(rappel_envoye_at IS NOT NULL) AS relances, SUM(merci_envoye_at IS NOT NULL) AS remercies FROM registrations'
)->fetch();
$reminderSent = (int) $reminderStats['relances'];
$reminderPending = (int) $reminderStats['total'] - $reminderSent;
$thanksSent = (int) $reminderStats['remercies'];
$thanksPending = (int) $reminderStats['total'] - $thanksSent;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Tableau de bord — Inscriptions Café Politique</title>
<link rel="icon" type="image/png" href="/assets/vector.png" />
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="admin.css" />
</head>
<body class="admin-dash-body">

<header class="admin-header">
  <div class="admin-header-inner">
    <div class="admin-brand">
      <img src="/assets/vector.png" alt="Logo COJEP" />
      <span>COJEP · Admin</span>
    </div>
    <a href="logout.php" class="btn-logout">Déconnexion</a>
  </div>
</header>

<main class="admin-main">
  <h1>Inscriptions — Café Politique du 31 octobre</h1>

  <?php if ($flash): ?>
    <div class="flash flash-<?= cojep_escape_html($flash['type']) ?>">
      <?= cojep_escape_html($flash['text']) ?>
      <?php if (!empty($flash['log'])): ?>
        <details class="flash-log">
          <summary>Détail du dialogue SMTP</summary>
          <pre><?= cojep_escape_html(implode("\n", $flash['log'])) ?></pre>
        </details>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="stats-row">
    <div class="stat-card">
      <span class="stat-value"><?= count($rows) ?></span>
      <span class="stat-label">Inscriptions</span>
    </div>
    <div class="stat-card">
      <span class="stat-value"><?= (int) $totalPersonnes ?></span>
      <span class="stat-label">Personnes attendues</span>
    </div>
    <div class="stat-card">
      <span class="stat-value"><?= $reminderSent ?></span>
      <span class="stat-label">Rappels envoyés</span>
    </div>
    <div class="stat-card">
      <span class="stat-value"><?= $thanksSent ?></span>
      <span class="stat-label">Remerciements envoyés</span>
    </div>
  </div>

  <section class="reminder-panel reminder-panel-thanks">
    <div class="reminder-intro">
      <h2>Mail de remerciement</h2>
      <p>Remerciements après l'événement, avec le lien vers la page Facebook pour récupérer les photos. Les inscrits déjà remerciés ne reçoivent pas le mail une seconde fois.</p>
      <a href="reminder-preview.php?campaign=merci" target="_blank" rel="noopener" class="reminder-link">Prévisualiser le mail ↗</a>
    </div>
    <div class="reminder-actions">
      <form method="post" action="send-reminder.php" class="reminder-test">
        <input type="hidden" name="campaign" value="merci" />
        <input type="hidden" name="mode" value="test" />
        <input type="hidden" name="q" value="<?= cojep_escape_html($search) ?>" />
        <input type="email" name="test_email" required placeholder="Email de test" value="cedrange@gmail.com" />
        <button type="submit" class="btn-secondary">Envoyer un test</button>
      </form>
      <form method="post" action="send-reminder.php"
            onsubmit="if (!confirm('Envoyer le mail de remerciement à <?= $thanksPending ?> inscrit(s) ?')) return false; this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Envoi en cours…';">
        <input type="hidden" name="campaign" value="merci" />
        <input type="hidden" name="mode" value="pending" />
        <input type="hidden" name="q" value="<?= cojep_escape_html($search) ?>" />
        <button type="submit" class="btn-reminder" <?= $thanksPending === 0 ? 'disabled' : '' ?>>
          <?= $thanksPending === 0 ? 'Tous les inscrits ont été remerciés' : 'Envoyer à ' . $thanksPending . ' inscrit(s) non remercié(s)' ?>
        </button>
      </form>
    </div>
  </section>

  <section class="reminder-panel">
    <div class="reminder-intro">
      <h2>Mail de rappel du 31 octobre</h2>
      <p>Message de remobilisation signé par Angaman Ange Cedrick. Les inscrits déjà relancés ne reçoivent pas le mail une seconde fois.</p>
      <a href="reminder-preview.php" target="_blank" rel="noopener" class="reminder-link">Prévisualiser le mail ↗</a>
    </div>
    <div class="reminder-actions">
      <form method="post" action="send-reminder.php" class="reminder-test">
        <input type="hidden" name="campaign" value="rappel" />
        <input type="hidden" name="mode" value="test" />
        <input type="hidden" name="q" value="<?= cojep_escape_html($search) ?>" />
        <input type="email" name="test_email" required placeholder="Email de test" value="cedrange@gmail.com" />
        <button type="submit" class="btn-secondary">Envoyer un test</button>
      </form>
      <form method="post" action="send-reminder.php"
            onsubmit="if (!confirm('Envoyer le mail de rappel à <?= $reminderPending ?> inscrit(s) ?')) return false; this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Envoi en cours…';">
        <input type="hidden" name="campaign" value="rappel" />
        <input type="hidden" name="mode" value="pending" />
        <input type="hidden" name="q" value="<?= cojep_escape_html($search) ?>" />
        <button type="submit" class="btn-reminder" <?= $reminderPending === 0 ? 'disabled' : '' ?>>
          <?= $reminderPending === 0 ? 'Tous les inscrits ont été relancés' : 'Envoyer à ' . $reminderPending . ' inscrit(s) non relancé(s)' ?>
        </button>
      </form>
    </div>
  </section>

  <form method="get" class="toolbar">
    <input type="search" name="q" placeholder="Rechercher par nom, prénom, email, téléphone..." value="<?= cojep_escape_html($search) ?>" />
    <button type="submit" class="btn-export">Rechercher</button>
    <a href="export.php" class="btn-export">Exporter en CSV</a>
  </form>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Prénom</th>
          <th>Nom</th>
          <th>Email</th>
          <th>Téléphone</th>
          <th>Pers.</th>
          <th>Message</th>
          <th>Email envoyé</th>
          <th>Rappel</th>
          <th>Merci</th>
          <th>Inscrit le</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="11" class="empty-row">Aucune inscription trouvée.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= cojep_escape_html($r['prenom']) ?></td>
            <td><?= cojep_escape_html($r['nom']) ?></td>
            <td><?= cojep_escape_html($r['email']) ?></td>
            <td><?= cojep_escape_html($r['telephone'] ?? '') ?: '—' ?></td>
            <td><?= (int) $r['nombre_personnes'] ?></td>
            <td><?= cojep_escape_html($r['message'] ?? '') ?: '—' ?></td>
            <td>
              <?php if ($r['email_envoye']): ?>
                <span class="badge badge-ok">Envoyé</span>
              <?php else: ?>
                <span class="badge badge-pending">En attente</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($r['rappel_envoye_at'])): ?>
                <span class="badge badge-ok" title="<?= cojep_escape_html((new DateTime($r['rappel_envoye_at']))->format('d/m/Y H:i')) ?>">Relancé</span>
              <?php else: ?>
                <span class="badge badge-pending">Non relancé</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($r['merci_envoye_at'])): ?>
                <span class="badge badge-ok" title="<?= cojep_escape_html((new DateTime($r['merci_envoye_at']))->format('d/m/Y H:i')) ?>">Remercié</span>
              <?php else: ?>
                <span class="badge badge-pending">Non remercié</span>
              <?php endif; ?>
            </td>
            <td><?= cojep_escape_html((new DateTime($r['created_at']))->format('d/m/Y H:i')) ?></td>
            <td class="row-actions">
              <form method="post" action="send-reminder.php" onsubmit="return confirm('Envoyer le mail de rappel à <?= cojep_escape_html(addslashes($r['email'])) ?> ?');">
                <input type="hidden" name="campaign" value="rappel" />
                <input type="hidden" name="mode" value="single" />
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>" />
                <input type="hidden" name="q" value="<?= cojep_escape_html($search) ?>" />
                <button type="submit" class="btn-remind-row"><?= empty($r['rappel_envoye_at']) ? 'Relancer' : 'Renvoyer' ?></button>
              </form>
              <form method="post" action="send-reminder.php" onsubmit="return confirm('Envoyer le mail de remerciement à <?= cojep_escape_html(addslashes($r['email'])) ?> ?');">
                <input type="hidden" name="campaign" value="merci" />
                <input type="hidden" name="mode" value="single" />
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>" />
                <input type="hidden" name="q" value="<?= cojep_escape_html($search) ?>" />
                <button type="submit" class="btn-remind-row"><?= empty($r['merci_envoye_at']) ? 'Remercier' : 'Re-remercier' ?></button>
              </form>
              <form method="post" action="delete.php" onsubmit="return confirm('Supprimer définitivement cette inscription ?');">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>" />
                <input type="hidden" name="q" value="<?= cojep_escape_html($search) ?>" />
                <button type="submit" class="btn-delete">Supprimer</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</main>

</body>
</html>
