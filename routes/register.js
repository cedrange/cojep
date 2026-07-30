const express = require('express');
const rateLimit = require('express-rate-limit');
const { body, validationResult } = require('express-validator');
const db = require('../db/database');
const { sendConfirmationEmail } = require('../db/mailer');

const router = express.Router();

const registerLimiter = rateLimit({
  windowMs: 15 * 60 * 1000,
  max: 20,
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: "Trop de tentatives d'inscription. Réessayez plus tard." },
});

const validators = [
  body('prenom').trim().notEmpty().withMessage('Le prénom est requis').isLength({ max: 100 }),
  body('nom').trim().notEmpty().withMessage('Le nom est requis').isLength({ max: 100 }),
  body('email').trim().isEmail().withMessage('Email invalide').normalizeEmail().isLength({ max: 190 }),
  body('telephone').optional({ checkFalsy: true }).trim().isLength({ max: 40 }),
  body('nombre_personnes')
    .optional({ checkFalsy: true })
    .isInt({ min: 1, max: 10 })
    .withMessage('Le nombre de personnes doit être entre 1 et 10'),
  body('message').optional({ checkFalsy: true }).trim().isLength({ max: 500 }),
];

const insertStmt = db.prepare(`
  INSERT INTO registrations (prenom, nom, email, telephone, nombre_personnes, message)
  VALUES (@prenom, @nom, @email, @telephone, @nombre_personnes, @message)
`);

const markEmailSentStmt = db.prepare(`UPDATE registrations SET email_envoye = 1 WHERE id = ?`);

router.post('/register', registerLimiter, validators, async (req, res) => {
  const errors = validationResult(req);
  if (!errors.isEmpty()) {
    return res.status(400).json({ error: errors.array()[0].msg, errors: errors.array() });
  }

  const registration = {
    prenom: req.body.prenom,
    nom: req.body.nom,
    email: req.body.email,
    telephone: req.body.telephone || null,
    nombre_personnes: req.body.nombre_personnes ? Number(req.body.nombre_personnes) : 1,
    message: req.body.message || null,
  };

  let result;
  try {
    result = insertStmt.run(registration);
  } catch (err) {
    if (err.code === 'SQLITE_CONSTRAINT_UNIQUE') {
      return res.status(409).json({ error: 'Cette adresse email est déjà inscrite à l’événement.' });
    }
    console.error('[register] erreur insertion', err);
    return res.status(500).json({ error: "Une erreur est survenue, merci de réessayer." });
  }

  res.status(201).json({ success: true, message: 'Inscription enregistrée avec succès.' });

  try {
    const sent = await sendConfirmationEmail({ ...registration, id: result.lastInsertRowid });
    if (sent) markEmailSentStmt.run(result.lastInsertRowid);
  } catch (err) {
    console.error('[register] échec envoi email de confirmation', err);
  }
});

module.exports = router;
