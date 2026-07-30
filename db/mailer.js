const nodemailer = require('nodemailer');

let transporter = null;

function getTransporter() {
  if (transporter) return transporter;

  if (!process.env.SMTP_HOST || !process.env.SMTP_USER || !process.env.SMTP_PASS) {
    return null;
  }

  transporter = nodemailer.createTransport({
    host: process.env.SMTP_HOST,
    port: Number(process.env.SMTP_PORT || 587),
    secure: process.env.SMTP_SECURE === 'true',
    auth: {
      user: process.env.SMTP_USER,
      pass: process.env.SMTP_PASS,
    },
  });

  return transporter;
}

async function sendConfirmationEmail(registration) {
  const t = getTransporter();
  if (!t) {
    console.warn('[mailer] SMTP non configuré (.env) — email de confirmation non envoyé pour', registration.email);
    return false;
  }

  const fromName = process.env.MAIL_FROM_NAME || 'COJEP';
  const fromAddress = process.env.MAIL_FROM_ADDRESS || process.env.SMTP_USER;

  const html = `
    <div style="font-family: Arial, Helvetica, sans-serif; background:#0b1650; padding:32px 0;">
      <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;">
        <div style="background:linear-gradient(120deg,#0b1650 0%, #4c1d95 55%, #d6247a 100%);padding:28px 32px;color:#fff;">
          <p style="margin:0;font-size:13px;letter-spacing:2px;text-transform:uppercase;opacity:.85;">COJEP · Café Politique</p>
          <h1 style="margin:8px 0 0;font-size:22px;">Inscription confirmée !</h1>
        </div>
        <div style="padding:28px 32px;color:#1c1c28;line-height:1.6;">
          <p>Bonjour ${escapeHtml(registration.prenom)},</p>
          <p>Merci pour votre inscription au <strong>Café Politique du COJEP</strong>. Votre place est confirmée pour :</p>
          <table style="width:100%;border-collapse:collapse;margin:16px 0;">
            <tr><td style="padding:6px 0;color:#6b7280;width:140px;">Thème</td><td style="padding:6px 0;font-weight:600;">L'Afrique face à son histoire : exiger réparation pour bâtir l'avenir</td></tr>
            <tr><td style="padding:6px 0;color:#6b7280;">Date</td><td style="padding:6px 0;font-weight:600;">Samedi 31 octobre, 14h00</td></tr>
            <tr><td style="padding:6px 0;color:#6b7280;">Lieu</td><td style="padding:6px 0;font-weight:600;">NH Brussels Carrefour de l'Europe — Rue du Marché aux Herbes 110, 1000 Bruxelles</td></tr>
            <tr><td style="padding:6px 0;color:#6b7280;">Invité</td><td style="padding:6px 0;font-weight:600;">Charles Blé Goudé</td></tr>
            <tr><td style="padding:6px 0;color:#6b7280;">Nombre de place(s)</td><td style="padding:6px 0;font-weight:600;">${registration.nombre_personnes}</td></tr>
          </table>
          <p>Cet événement est gratuit et ouvert à tous. Présentez-vous simplement à l'accueil, votre nom sera sur la liste des inscrits.</p>
          <p style="margin-top:24px;">À très bientôt,<br/><strong>L'équipe du COJEP</strong></p>
        </div>
      </div>
    </div>
  `;

  await t.sendMail({
    from: `"${fromName}" <${fromAddress}>`,
    to: registration.email,
    subject: 'Confirmation de votre inscription — Café Politique du COJEP, 31 octobre',
    html,
  });

  return true;
}

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

module.exports = { sendConfirmationEmail };
