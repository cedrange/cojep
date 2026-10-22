<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Signataire du mail de rappel (utilisé aussi comme adresse de réponse).
const COJEP_REMINDER_SIGNATURE = [
    'nom' => 'Angaman Ange Cedrick',
    'poste' => 'PCO et Président des Jeunes du COJEP en Belgique',
    'email' => 'cedrange@gmail.com',
    'telephone' => '+32498429238',
];

const COJEP_EVENT_DATE = '2026-10-31 14:00:00';
const COJEP_EVENT_PLACE = 'NH Brussels Carrefour de l\'Europe';
const COJEP_EVENT_ADDRESS = 'Rue du Marché aux Herbes 110, 1000 Bruxelles';

/**
 * Crée une instance PHPMailer prête à l'emploi, ou null si le SMTP n'est pas configuré.
 */
function cojep_create_mailer(): ?PHPMailer
{
    $smtp = cojep_config()['smtp'];

    if (empty($smtp['host']) || empty($smtp['user']) || empty($smtp['pass'])) {
        return null;
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtp['host'];
    $mail->Port = (int) $smtp['port'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp['user'];
    $mail->Password = $smtp['pass'];
    if (!empty($smtp['secure'])) {
        $mail->SMTPSecure = $smtp['secure'];
    }
    $mail->CharSet = 'UTF-8';

    // Le certificat TLS du mutualisé LWS est émis pour *.lwspanel.com, pas pour le nom
    // d'hôte du domaine client (ex: mail.cojep.be) : on assouplit la vérification du nom
    // tout en gardant la connexion chiffrée.
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];

    $fromAddress = $smtp['from_address'] ?: $smtp['user'];
    $mail->setFrom($fromAddress, $smtp['from_name'] ?: 'COJEP');

    return $mail;
}

function cojep_send_confirmation_email(array $registration): bool
{
    $mail = cojep_create_mailer();
    if ($mail === null) {
        error_log('[mailer] SMTP non configuré (config.php) — email de confirmation non envoyé pour ' . $registration['email']);
        return false;
    }

    try {
        $mail->addAddress($registration['email'], $registration['prenom'] . ' ' . $registration['nom']);

        $mail->isHTML(true);
        $mail->Subject = "Confirmation de votre inscription — Café Politique du COJEP, 31 octobre";
        $mail->Body = cojep_confirmation_email_html($registration);

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('[mailer] échec envoi email de confirmation : ' . $mail->ErrorInfo);
        return false;
    }
}

function cojep_confirmation_email_html(array $registration): string
{
    $prenom = cojep_escape_html($registration['prenom']);
    $nombre = (int) $registration['nombre_personnes'];

    return <<<HTML
    <div style="font-family: Arial, Helvetica, sans-serif; background:#0b1650; padding:32px 0;">
      <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;">
        <div style="background:linear-gradient(120deg,#0b1650 0%, #4c1d95 55%, #d6247a 100%);padding:28px 32px;color:#fff;">
          <p style="margin:0;font-size:13px;letter-spacing:2px;text-transform:uppercase;opacity:.85;">COJEP · Café Politique</p>
          <h1 style="margin:8px 0 0;font-size:22px;">Inscription confirmée !</h1>
        </div>
        <div style="padding:28px 32px;color:#1c1c28;line-height:1.6;">
          <p>Bonjour {$prenom},</p>
          <p>Merci pour votre inscription au <strong>Café Politique du COJEP</strong>. Votre place est confirmée pour :</p>
          <table style="width:100%;border-collapse:collapse;margin:16px 0;">
            <tr><td style="padding:6px 0;color:#6b7280;width:140px;">Thème</td><td style="padding:6px 0;font-weight:600;">L'Afrique face à son histoire : exiger réparation pour bâtir l'avenir</td></tr>
            <tr><td style="padding:6px 0;color:#6b7280;">Date</td><td style="padding:6px 0;font-weight:600;">Samedi 31 octobre, 14h00</td></tr>
            <tr><td style="padding:6px 0;color:#6b7280;">Lieu</td><td style="padding:6px 0;font-weight:600;">NH Brussels Carrefour de l'Europe — Rue du Marché aux Herbes 110, 1000 Bruxelles</td></tr>
            <tr><td style="padding:6px 0;color:#6b7280;">Invité</td><td style="padding:6px 0;font-weight:600;">Charles Blé Goudé</td></tr>
            <tr><td style="padding:6px 0;color:#6b7280;">Nombre de place(s)</td><td style="padding:6px 0;font-weight:600;">{$nombre}</td></tr>
          </table>
          <p>Cet événement est gratuit et ouvert à tous. Présentez-vous simplement à l'accueil, votre nom sera sur la liste des inscrits.</p>
          <p style="margin-top:24px;">À très bientôt,<br/><strong>L'équipe du COJEP</strong></p>
        </div>
      </div>
    </div>
    HTML;
}

/* ------------------------------------------------------------------ */
/*  Mail de rappel / remobilisation                                   */
/* ------------------------------------------------------------------ */

/**
 * Nombre de jours restants avant l'événement (heure de Bruxelles). 0 = le jour même.
 */
function cojep_days_until_event(): int
{
    $tz = new DateTimeZone('Europe/Brussels');
    $today = new DateTime('today', $tz);
    $event = new DateTime(substr(COJEP_EVENT_DATE, 0, 10), $tz);

    return max(0, (int) $today->diff($event)->format('%r%a'));
}

function cojep_reminder_subject(): string
{
    $days = cojep_days_until_event();

    if ($days === 0) {
        return "C'est aujourd'hui : on vous attend à 14h — Café Politique du COJEP";
    }
    if ($days === 1) {
        return "C'est demain : votre place vous attend — Café Politique du COJEP";
    }

    return "J-{$days} : le 31 octobre, nous comptons sur vous — Café Politique du COJEP";
}

/**
 * Envoie le rappel à une liste d'inscrits en réutilisant une seule connexion SMTP.
 * $onSent est appelé après chaque envoi réussi (pour marquer l'inscription en base au fil de l'eau).
 *
 * @return array{sent:int, failed:string[], error:?string}
 */
function cojep_send_reminder_emails(array $registrations, string $siteUrl, ?callable $onSent = null): array
{
    $result = ['sent' => 0, 'failed' => [], 'error' => null];

    $mail = cojep_create_mailer();
    if ($mail === null) {
        $result['error'] = 'SMTP non configuré (config.php) : aucun rappel envoyé.';
        return $result;
    }

    $signature = COJEP_REMINDER_SIGNATURE;

    try {
        $mail->SMTPKeepAlive = true;
        $mail->addReplyTo($signature['email'], $signature['nom']);
        $mail->addEmbeddedImage(__DIR__ . '/../assets/vector.png', 'cojep-logo', 'cojep.png');
        $mail->isHTML(true);
        $mail->Subject = cojep_reminder_subject();
    } catch (PHPMailerException $e) {
        $result['error'] = 'Préparation du mail impossible : ' . $e->getMessage();
        return $result;
    }

    foreach ($registrations as $registration) {
        try {
            $mail->clearAddresses();
            $mail->addAddress($registration['email'], trim($registration['prenom'] . ' ' . $registration['nom']));
            $mail->Body = cojep_reminder_email_html($registration, $siteUrl, 'cid:cojep-logo');
            $mail->AltBody = cojep_reminder_email_text($registration, $siteUrl);
            $mail->send();

            $result['sent']++;
            if ($onSent !== null) {
                $onSent($registration);
            }
        } catch (PHPMailerException $e) {
            error_log('[mailer] échec envoi rappel à ' . $registration['email'] . ' : ' . $mail->ErrorInfo);
            $result['failed'][] = $registration['email'];
            // Une erreur SMTP peut laisser la connexion dans un état incohérent : on repart proprement.
            $mail->getSMTPInstance()->reset();
        }
    }

    $mail->smtpClose();

    return $result;
}

function cojep_event_links(): array
{
    $query = rawurlencode(COJEP_EVENT_PLACE . ', ' . COJEP_EVENT_ADDRESS);

    $calendar = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
        . '&text=' . rawurlencode('Café Politique du COJEP — avec Charles Blé Goudé')
        . '&dates=20261031T140000/20261031T180000&ctz=Europe/Brussels'
        . '&details=' . rawurlencode("L'Afrique face à son histoire : exiger réparation pour bâtir l'avenir. Accueil dès 13h30.")
        . '&location=' . $query;

    return [
        'maps' => 'https://www.google.com/maps/search/?api=1&query=' . $query,
        'calendar' => $calendar,
    ];
}

function cojep_reminder_email_html(array $registration, string $siteUrl, string $logoSrc): string
{
    $e = 'cojep_escape_html';
    $prenom = $e($registration['prenom']);
    $nombre = max(1, (int) ($registration['nombre_personnes'] ?? 1));
    $placesLabel = $nombre > 1 ? "{$nombre} places" : '1 place';

    $days = cojep_days_until_event();
    if ($days === 0) {
        $countdown = "C'est aujourd'hui";
    } elseif ($days === 1) {
        $countdown = "C'est demain";
    } else {
        $countdown = "J-{$days}";
    }
    $preheader = $days > 1
        ? "Plus que {$days} jours : votre place vous attend samedi 31 octobre à 14h, avec Charles Blé Goudé."
        : "Votre place vous attend samedi 31 octobre à 14h, avec Charles Blé Goudé.";

    $links = cojep_event_links();
    $maps = $e($links['maps']);
    $calendar = $e($links['calendar']);
    $site = $e($siteUrl);
    $siteLabel = $e(preg_replace('#^https?://#', '', $siteUrl));
    $logo = $e($logoSrc);

    $sig = COJEP_REMINDER_SIGNATURE;
    $sigNom = $e($sig['nom']);
    $sigPoste = $e($sig['poste']);
    $sigEmail = $e($sig['email']);
    $sigTel = $e($sig['telephone']);
    $sigTelHref = $e(preg_replace('/[^+\d]/', '', $sig['telephone']));
    $sigTelLabel = $e(preg_replace('/^\+32(\d{3})(\d{2})(\d{2})(\d{2})$/', '+32 $1 $2 $3 $4', $sig['telephone']));

    $place = $e(COJEP_EVENT_PLACE);
    $address = $e(COJEP_EVENT_ADDRESS);

    $promises = [
        ['Un échange direct', 'Charles Blé Goudé face à vous, sans filtre ni langue de bois, sur la question des réparations et de l\'avenir du continent.'],
        ['La parole à la salle', 'Un vrai temps de questions-réponses : vos interrogations, vos convictions et vos propositions auront leur place.'],
        ['Des rencontres qui comptent', 'Jeunes, militants, entrepreneurs, étudiants de la diaspora : vous repartirez avec un réseau, pas seulement des idées.'],
        ['Une suite concrète', 'Ce rendez-vous ne s\'arrêtera pas à la sortie de la salle. Les propositions issues des échanges seront portées par le COJEP, et vous serez tenu(e) informé(e) des suites.'],
    ];
    $promisesHtml = '';
    foreach ($promises as $i => [$title, $text]) {
        $n = $i + 1;
        $title = $e($title);
        $text = $e($text);
        $promisesHtml .= <<<HTML
              <tr>
                <td width="44" valign="top" style="padding:0 0 18px 0;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td width="32" height="32" align="center" valign="middle" bgcolor="#d6247a" style="width:32px;height:32px;border-radius:16px;background:#d6247a;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:bold;line-height:32px;">{$n}</td>
                  </tr></table>
                </td>
                <td valign="top" style="padding:0 0 18px 0;font-family:Arial,Helvetica,sans-serif;">
                  <p style="margin:0 0 3px;font-size:15px;font-weight:bold;color:#0b1650;line-height:1.4;">{$title}</p>
                  <p style="margin:0;font-size:14px;color:#4b5068;line-height:1.55;">{$text}</p>
                </td>
              </tr>
HTML;
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="color-scheme" content="light" />
<title>Café Politique du COJEP — 31 octobre</title>
<style>
  @media only screen and (max-width: 620px) {
    .px { padding-left: 22px !important; padding-right: 22px !important; }
    .hero-title { font-size: 25px !important; }
    .stack { display: block !important; width: 100% !important; text-align: center !important; }
    .stack a { display: block !important; }
    .gap { display: block !important; width: 100% !important; height: 10px !important; line-height: 10px !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background:#eef0f7;">
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#eef0f7;">{$preheader}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#eef0f7" style="background:#eef0f7;">
  <tr>
    <td align="center" style="padding:28px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;">

        <!-- Logo -->
        <tr>
          <td align="center" bgcolor="#ffffff" style="padding:28px 24px 18px;">
            <img src="{$logo}" width="120" alt="COJEP" style="display:block;width:120px;max-width:120px;height:auto;border:0;" />
            <p style="margin:10px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#1e3fd6;font-weight:bold;">Café Politique · Bruxelles</p>
          </td>
        </tr>

        <!-- Bandeau -->
        <tr>
          <td align="center" bgcolor="#0b1650" style="background:#0b1650;background-image:linear-gradient(125deg,#0b1650 0%,#1e3fd6 45%,#6d28d9 75%,#d6247a 100%);padding:38px 32px 40px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
              <td bgcolor="#d6247a" style="background:#d6247a;border-radius:999px;padding:6px 16px;font-family:Arial,Helvetica,sans-serif;font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#ffffff;">{$countdown}</td>
            </tr></table>
            <h1 class="hero-title" style="margin:18px 0 10px;font-family:Georgia,'Times New Roman',serif;font-size:30px;line-height:1.25;font-weight:bold;color:#ffffff;">Le 31 octobre,<br />votre voix compte.</h1>
            <p style="margin:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.55;color:#dfe3ff;font-style:italic;">« L'Afrique face à son histoire : exiger réparation pour bâtir l'avenir »</p>
          </td>
        </tr>

        <!-- Message -->
        <tr>
          <td class="px" style="padding:34px 40px 8px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.7;color:#1c1c28;">
            <p style="margin:0 0 16px;">Bonjour {$prenom},</p>
            <p style="margin:0 0 16px;">Dans quelques jours, nous nous retrouverons pour le <strong>Café Politique du COJEP</strong>, aux côtés de <strong>Charles Blé Goudé</strong>. Votre inscription n'était pas un simple clic : c'était un engagement. Celui de ne plus laisser d'autres raconter notre histoire à notre place.</p>
            <p style="margin:0 0 16px;">La question des réparations n'est pas un débat réservé aux experts. C'est <strong>notre</strong> débat, celui de la jeunesse et de la diaspora. Et il n'aura de force que si la salle est pleine, vivante et exigeante.</p>
            <p style="margin:0 0 6px;">Alors samedi, nous comptons sur vous. <strong style="color:#d6247a;">Pas en spectateur, mais en acteur.</strong></p>
          </td>
        </tr>

        <!-- Promesses -->
        <tr>
          <td class="px" style="padding:22px 40px 6px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f6f4fd" style="background:#f6f4fd;border-radius:14px;">
              <tr>
                <td style="padding:26px 26px 10px;">
                  <p style="margin:0 0 20px;font-family:Arial,Helvetica,sans-serif;font-size:12px;letter-spacing:2px;text-transform:uppercase;font-weight:bold;color:#6d28d9;">Notre engagement envers vous</p>
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
{$promisesHtml}
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Infos pratiques -->
        <tr>
          <td class="px" style="padding:26px 40px 4px;">
            <p style="margin:0 0 12px;font-family:Arial,Helvetica,sans-serif;font-size:12px;letter-spacing:2px;text-transform:uppercase;font-weight:bold;color:#6d28d9;">Votre rendez-vous</p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;border-top:1px solid #eceefa;">
              <tr>
                <td width="130" style="padding:11px 0;color:#6b7080;border-bottom:1px solid #eceefa;">Date</td>
                <td style="padding:11px 0;color:#0b1650;font-weight:bold;border-bottom:1px solid #eceefa;">Samedi 31 octobre 2026</td>
              </tr>
              <tr>
                <td style="padding:11px 0;color:#6b7080;border-bottom:1px solid #eceefa;">Heure</td>
                <td style="padding:11px 0;color:#0b1650;font-weight:bold;border-bottom:1px solid #eceefa;">14h00 <span style="font-weight:normal;color:#4b5068;">— accueil dès 13h30</span></td>
              </tr>
              <tr>
                <td valign="top" style="padding:11px 0;color:#6b7080;border-bottom:1px solid #eceefa;">Lieu</td>
                <td style="padding:11px 0;color:#0b1650;font-weight:bold;border-bottom:1px solid #eceefa;">{$place}<br /><span style="font-weight:normal;color:#4b5068;">{$address}</span></td>
              </tr>
              <tr>
                <td style="padding:11px 0;color:#6b7080;border-bottom:1px solid #eceefa;">Invité d'honneur</td>
                <td style="padding:11px 0;color:#0b1650;font-weight:bold;border-bottom:1px solid #eceefa;">Charles Blé Goudé</td>
              </tr>
              <tr>
                <td style="padding:11px 0;color:#6b7080;">Réservation</td>
                <td style="padding:11px 0;color:#0b1650;font-weight:bold;">{$placesLabel} à votre nom</td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Boutons -->
        <tr>
          <td align="center" class="px" style="padding:24px 40px 8px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
              <td class="stack" bgcolor="#d6247a" style="border-radius:10px;background:#d6247a;">
                <a href="{$maps}" target="_blank" style="display:inline-block;padding:14px 24px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:10px;">Préparer mon itinéraire</a>
              </td>
              <td class="gap" width="12" style="width:12px;">&nbsp;</td>
              <td class="stack" style="border-radius:10px;border:2px solid #1e3fd6;">
                <a href="{$calendar}" target="_blank" style="display:inline-block;padding:12px 22px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:#1e3fd6;text-decoration:none;border-radius:10px;">Ajouter à mon agenda</a>
              </td>
            </tr></table>
          </td>
        </tr>

        <!-- Mobilisation -->
        <tr>
          <td class="px" style="padding:22px 40px 8px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.7;color:#1c1c28;">
            <p style="margin:0 0 16px;"><strong>Faites grandir le mouvement.</strong> Transférez ce message à un proche, un collègue, un camarade : chaque nouvelle personne inscrite sur <a href="{$site}" target="_blank" style="color:#1e3fd6;font-weight:bold;text-decoration:none;">{$siteLabel}</a> renforce notre voix.</p>
            <p style="margin:0 0 16px;color:#4b5068;font-size:14px;">Un empêchement de dernière minute ? Répondez simplement à ce mail : votre place pourra profiter à quelqu'un d'autre.</p>
            <p style="margin:24px 0 0;font-family:Georgia,'Times New Roman',serif;font-size:18px;line-height:1.5;color:#0b1650;font-style:italic;">Le 31 octobre, soyons nombreux, soyons dignes, soyons debout.</p>
          </td>
        </tr>

        <!-- Signature -->
        <tr>
          <td class="px" style="padding:26px 40px 36px;">
            <p style="margin:0 0 14px;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#1c1c28;">Avec toute ma détermination,</p>
            <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
              <td width="4" bgcolor="#d6247a" style="width:4px;background:#d6247a;border-radius:2px;">&nbsp;</td>
              <td style="padding:2px 0 2px 16px;font-family:Arial,Helvetica,sans-serif;line-height:1.55;">
                <p style="margin:0;font-size:16px;font-weight:bold;color:#0b1650;">{$sigNom}</p>
                <p style="margin:0 0 6px;font-size:13px;color:#6d28d9;font-weight:bold;">{$sigPoste}</p>
                <p style="margin:0;font-size:13px;color:#4b5068;">
                  <a href="mailto:{$sigEmail}" style="color:#1e3fd6;text-decoration:none;">{$sigEmail}</a>
                  &nbsp;·&nbsp;
                  <a href="tel:{$sigTelHref}" style="color:#1e3fd6;text-decoration:none;">{$sigTelLabel}</a>
                </p>
              </td>
            </tr></table>
          </td>
        </tr>

        <!-- Pied -->
        <tr>
          <td align="center" bgcolor="#0b1650" style="background:#0b1650;padding:20px 32px;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.6;color:#aab1d9;">
            <strong style="color:#ffffff;">COJEP</strong> · Café Politique du 31 octobre · Bruxelles<br />
            Vous recevez ce message car vous êtes inscrit(e) à cet événement.
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;
}

function cojep_reminder_email_text(array $registration, string $siteUrl): string
{
    $sig = COJEP_REMINDER_SIGNATURE;
    $links = cojep_event_links();
    $nombre = max(1, (int) ($registration['nombre_personnes'] ?? 1));

    return "Bonjour {$registration['prenom']},\n\n"
        . "Dans quelques jours, nous nous retrouverons pour le Café Politique du COJEP, aux côtés de Charles Blé Goudé. "
        . "Votre inscription était un engagement : celui de ne plus laisser d'autres raconter notre histoire à notre place.\n\n"
        . "Samedi, nous comptons sur vous. Pas en spectateur, mais en acteur.\n\n"
        . "NOTRE ENGAGEMENT ENVERS VOUS\n"
        . "1. Un échange direct avec Charles Blé Goudé, sans filtre ni langue de bois.\n"
        . "2. La parole à la salle : un vrai temps de questions-réponses.\n"
        . "3. Des rencontres qui comptent avec la jeunesse et la diaspora.\n"
        . "4. Une suite concrète : les propositions issues des échanges seront portées par le COJEP.\n\n"
        . "VOTRE RENDEZ-VOUS\n"
        . "Date : samedi 31 octobre 2026\n"
        . "Heure : 14h00 (accueil dès 13h30)\n"
        . 'Lieu : ' . COJEP_EVENT_PLACE . ', ' . COJEP_EVENT_ADDRESS . "\n"
        . "Réservation : {$nombre} place(s) à votre nom\n"
        . "Itinéraire : {$links['maps']}\n\n"
        . "Faites grandir le mouvement : partagez l'invitation ({$siteUrl}).\n"
        . "Un empêchement ? Répondez simplement à ce mail.\n\n"
        . "Le 31 octobre, soyons nombreux, soyons dignes, soyons debout.\n\n"
        . "{$sig['nom']}\n{$sig['poste']}\n{$sig['email']} · {$sig['telephone']}\n";
}
