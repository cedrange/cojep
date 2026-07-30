# Café Politique du COJEP — 31 octobre

Landing page + gestion des inscriptions pour l'événement « L'Afrique face à son histoire :
exiger réparation pour bâtir l'avenir », le 31 octobre au NH Brussels Carrefour de l'Europe.

## Stack

- Node.js / Express
- SQLite (better-sqlite3) pour stocker les inscriptions
- Nodemailer pour l'email de confirmation
- Espace admin protégé (sessions + bcrypt) pour consulter/exporter/supprimer les inscriptions

## Installation

```bash
npm install
cp .env.example .env
```

Remplissez `.env` :

- `SESSION_SECRET` : une chaîne aléatoire longue.
- `ADMIN_USERNAME` : l'identifiant de connexion admin.
- `ADMIN_PASSWORD_HASH` : générez-le avec :
  ```bash
  npm run hash-password -- "votre-mot-de-passe"
  ```
  puis copiez la ligne affichée dans `.env`.
- `SMTP_*` : vos identifiants d'envoi d'email (ex: Gmail avec un « mot de passe d'application »,
  ou tout autre fournisseur SMTP). Sans ces valeurs, les inscriptions sont bien enregistrées
  mais aucun email de confirmation n'est envoyé (un avertissement s'affiche dans les logs).

## Démarrage

```bash
npm start        # production
npm run dev       # redémarre automatiquement au changement de fichier
```

Le site est servi sur `http://localhost:3000` (ou le `PORT` défini dans `.env`).

- Landing page publique : `/`
- Espace admin : `/admin`

## Espace admin

- Connexion avec l'identifiant/mot de passe définis dans `.env`.
- Tableau de bord : liste des inscriptions, recherche, export CSV, suppression.
- Chaque inscription déclenche un email de confirmation (si SMTP configuré) ; le statut d'envoi
  est visible dans le tableau.

## Déploiement (VPS)

1. Copier le projet sur le serveur, `npm install --omit=dev`.
2. Configurer `.env` (ne jamais committer ce fichier).
3. Lancer avec un gestionnaire de process (ex: `pm2 start server.js --name cojep`).
4. Mettre un reverse proxy (Nginx) devant, avec HTTPS (Let's Encrypt), et passer
   `NODE_ENV=production` pour activer les cookies de session sécurisés.
5. Penser à sauvegarder régulièrement le fichier `db/cojep.db`.
