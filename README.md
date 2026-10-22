# Café Politique du COJEP — 31 octobre

Landing page + gestion des inscriptions pour l'événement « L'Afrique face à son histoire :
exiger réparation pour bâtir l'avenir », le 31 octobre au NH Brussels Carrefour de l'Europe.

## Stack (version PHP — hébergement mutualisé LWS)

- PHP 7.4+ / PDO MySQL
- PHPMailer pour l'email de confirmation (SMTP)
- Espace admin protégé (sessions PHP + bcrypt via `password_hash`) pour consulter/exporter/supprimer les inscriptions

## Installation

1. **Base de données** : créez une base MySQL (via cPanel → "Bases de données MySQL" sur LWS), puis
   importez `sql/schema.sql` (via phpMyAdmin, onglet "Importer").
2. **Configuration** :
   ```bash
   cp public/config.sample.php public/config.php
   ```
   Remplissez `public/config.php` :
   - `db` : host/nom/utilisateur/mot de passe de la base MySQL créée à l'étape 1.
   - `admin.username` / `admin.password_hash` : générez le hash avec
     ```bash
     php -r "echo password_hash('votre-mot-de-passe', PASSWORD_BCRYPT), PHP_EOL;"
     ```
   - `smtp.*` : vos identifiants d'envoi d'email (ex: Gmail avec un « mot de passe d'application »,
     ou tout autre fournisseur SMTP). Sans ces valeurs, les inscriptions sont bien enregistrées
     mais aucun email de confirmation n'est envoyé (un message est écrit dans les logs PHP).
3. **PHPMailer** : déjà présent dans `public/vendor/phpmailer/phpmailer/`. Si vous partez d'une copie
   propre du dépôt, réinstallez-le avec :
   ```bash
   composer install
   ```
   (ou téléchargez manuellement PHPMailer et placez son dossier `src/` dans
   `public/vendor/phpmailer/phpmailer/src/`).

## Arborescence

Tout le nécessaire à l'exécution vit **dans `public/`**, car sur certains hébergements mutualisés
(dont LWS) seul ce dossier est déployé comme racine web (`htdocs`), sans accès à un dossier parent.
`config.php` et `vendor/` sont malgré tout protégés contre l'accès HTTP direct via `.htaccess`
(`public/.htaccess` et `public/vendor/.htaccess`).

```
sql/schema.sql              <- structure de la base, à importer une seule fois (pas besoin sur le serveur)
public/                     <- tout ce dossier = contenu de htdocs sur LWS
  config.php                  <- vos identifiants réels (protégé par .htaccess, jamais committé)
  config.sample.php           <- modèle à copier
  vendor/phpmailer/...         <- librairie d'envoi d'email (protégée par .htaccess)
  .htaccess                    <- bloque l'accès direct à config.php
  index.html                   <- landing page
  register.php                 <- API d'inscription (appelée en fetch par js/main.js)
  inc/                          <- connexion DB, config, email, anti-spam
  admin/                        <- espace admin (index.php = login, dashboard, export, suppression)
```

## Déploiement sur LWS (hébergement mutualisé cPanel)

1. Uploadez le **contenu du dossier `public/`** directement dans `htdocs` (ou le dossier racine web
   de votre domaine sur LWS) — fichiers et sous-dossiers à plat, pas le dossier `public/` lui-même.
2. Créez la base MySQL et importez `sql/schema.sql` via phpMyAdmin (ce fichier n'a pas besoin d'être
   uploadé sur le serveur, il sert uniquement à l'import initial).
3. Copiez et remplissez `config.php` (à partir de `config.sample.php`) directement dans `htdocs`,
   comme décrit ci-dessus.
4. Vérifiez que `vendor/phpmailer/...` est bien présent dans `htdocs/vendor/` (sinon `register.php`
   plantera à l'envoi de l'email — erreur "Failed to open stream" sur `Exception.php`/`PHPMailer.php`).
5. Vérifiez que votre offre LWS autorise l'envoi SMTP sortant vers un serveur externe (port 587/465) ;
   sinon utilisez le SMTP fourni par LWS pour votre domaine.
6. Testez l'inscription sur la landing page, puis connectez-vous sur `/admin/` avec l'identifiant/mot
   de passe définis dans `config.php`.

## Espace admin

- URL : `/admin/` (redirige vers le formulaire de connexion si non connecté).
- Tableau de bord : liste des inscriptions, recherche, export CSV, suppression.
- Chaque inscription déclenche un email de confirmation (si SMTP configuré) ; le statut d'envoi
  est visible dans le tableau ("Envoyé" / "En attente").
