const express = require('express');
const bcrypt = require('bcrypt');
const rateLimit = require('express-rate-limit');
const { body, validationResult } = require('express-validator');
const db = require('../db/database');
const { requireAdmin } = require('../middleware/auth');

const router = express.Router();

const loginLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 10,
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: 'Trop de tentatives de connexion. Réessayez plus tard.' },
});

router.post(
  '/login',
  loginLimiter,
  [body('username').trim().notEmpty(), body('password').notEmpty()],
  async (req, res) => {
    const errors = validationResult(req);
    if (!errors.isEmpty()) {
      return res.status(400).json({ error: 'Identifiants requis' });
    }

    const { username, password } = req.body;
    const expectedUser = process.env.ADMIN_USERNAME;
    const expectedHash = process.env.ADMIN_PASSWORD_HASH;

    if (!expectedUser || !expectedHash) {
      return res.status(500).json({ error: "Compte admin non configuré (.env)." });
    }

    if (username !== expectedUser) {
      return res.status(401).json({ error: 'Identifiants incorrects' });
    }

    const ok = await bcrypt.compare(password, expectedHash);
    if (!ok) {
      return res.status(401).json({ error: 'Identifiants incorrects' });
    }

    req.session.regenerate((err) => {
      if (err) return res.status(500).json({ error: 'Erreur serveur' });
      req.session.isAdmin = true;
      req.session.username = username;
      res.json({ success: true });
    });
  }
);

router.post('/logout', (req, res) => {
  req.session.destroy(() => {
    res.clearCookie('cojep.sid');
    res.json({ success: true });
  });
});

router.get('/session', (req, res) => {
  res.json({ isAdmin: Boolean(req.session && req.session.isAdmin) });
});

router.get('/registrations', requireAdmin, (req, res) => {
  const search = (req.query.q || '').trim();
  let rows;
  if (search) {
    const like = `%${search}%`;
    rows = db
      .prepare(
        `SELECT * FROM registrations
         WHERE prenom LIKE ? OR nom LIKE ? OR email LIKE ? OR telephone LIKE ?
         ORDER BY created_at DESC`
      )
      .all(like, like, like, like);
  } else {
    rows = db.prepare('SELECT * FROM registrations ORDER BY created_at DESC').all();
  }

  const totalPersonnes = rows.reduce((sum, r) => sum + (r.nombre_personnes || 1), 0);
  res.json({ count: rows.length, totalPersonnes, rows });
});

router.get('/registrations/export.csv', requireAdmin, (req, res) => {
  const rows = db.prepare('SELECT * FROM registrations ORDER BY created_at DESC').all();
  const header = ['id', 'prenom', 'nom', 'email', 'telephone', 'nombre_personnes', 'message', 'email_envoye', 'created_at'];
  const csvEscape = (val) => {
    if (val === null || val === undefined) return '';
    const s = String(val).replace(/"/g, '""');
    return `"${s}"`;
  };
  const lines = [header.join(',')];
  for (const row of rows) {
    lines.push(header.map((h) => csvEscape(row[h])).join(','));
  }
  const csv = lines.join('\n');

  res.setHeader('Content-Type', 'text/csv; charset=utf-8');
  res.setHeader('Content-Disposition', 'attachment; filename="inscriptions-cafe-politique.csv"');
  res.send('﻿' + csv);
});

router.delete('/registrations/:id', requireAdmin, (req, res) => {
  const id = Number(req.params.id);
  if (!Number.isInteger(id)) {
    return res.status(400).json({ error: 'Id invalide' });
  }
  const info = db.prepare('DELETE FROM registrations WHERE id = ?').run(id);
  if (info.changes === 0) {
    return res.status(404).json({ error: 'Inscription introuvable' });
  }
  res.json({ success: true });
});

module.exports = router;
