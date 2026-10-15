<?php
// Email della Carovana: tabelle e stili in linea, perché i programmi di posta (Gmail, Outlook…)
// ignorano gran parte del CSS. Variabili: $titolo, $blocchi, $anteprima, $logo_cid, $site, $site_label
// (vedi mail_html in inc/mail.php). Stessa grafica di templates/email.html della versione Python.
defined('CAROVANA') || exit;
?><!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= e($titolo) ?></title>
</head>
<body style="margin:0; padding:0; background:#f4f4f2;">
<div style="display:none; max-height:0; overflow:hidden; opacity:0;"><?= e($anteprima) ?></div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f2;">
<tr><td align="center" style="padding:24px 12px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; background:#ffffff; border:2px solid #000000;">
    <tr>
      <td style="background:#DF6CE5; padding:20px 28px 16px; border-bottom:2px solid #000000;">
        <img src="cid:<?= e($logo_cid) ?>" width="220" alt="Carovana" style="display:block; width:220px; max-width:100%; height:auto; border:0; font-family:Arial, Helvetica, sans-serif; font-size:28px; font-weight:bold; color:#000000;">
        <p style="margin:10px 0 0; font-family:Arial, Helvetica, sans-serif; font-size:14px; font-weight:bold; color:#000000;"><?= e(SLOGAN) ?></p>
      </td>
    </tr>
    <tr>
      <td style="padding:28px 28px 8px; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:1.55; color:#111111;">
        <h1 style="margin:0 0 18px; font-family:Arial, Helvetica, sans-serif; font-size:23px; line-height:1.2; color:#000000;"><?= e($titolo) ?></h1>
        <?php foreach ($blocchi as $b): ?>
        <?php if ($b['tipo'] === 'p'): ?>
        <p style="margin:0 0 16px;"><?= e($b['testo']) ?></p>
        <?php elseif ($b['tipo'] === 'pulsante'): ?>
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:2px 0 22px;">
          <tr><td style="background:#DF6CE5; border:2px solid #000000; border-radius:999px;">
            <a href="<?= e($b['url']) ?>" style="display:inline-block; padding:12px 24px; font-family:Arial, Helvetica, sans-serif; font-size:16px; font-weight:bold; color:#000000; text-decoration:none;"><?= e($b['testo']) ?></a>
          </td></tr>
        </table>
        <?php elseif ($b['tipo'] === 'dettagli'): ?>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 22px; border:2px solid #000000; border-collapse:collapse;">
          <?php foreach ($b['righe'] as list($label, $valore, $link)): ?>
          <tr>
            <td valign="top" style="padding:9px 12px; background:#FFF8BF; border-bottom:1px solid #000000; font-family:Arial, Helvetica, sans-serif; font-size:13px; font-weight:bold; color:#000000; width:34%;"><?= e($label) ?></td>
            <td valign="top" style="padding:9px 12px; border-bottom:1px solid #000000; font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#111111; white-space:pre-line;"><?php if ($link): ?><a href="<?= e($link) ?>" style="color:#000000; font-weight:bold;"><?= e($valore) ?></a><?php else: ?><?= e($valore) ?><?php endif; ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
        <?php elseif ($b['tipo'] === 'riquadro'): ?>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 22px; background:#FFF8BF; border:2px dashed #000000;">
          <tr><td style="padding:16px 18px; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.5; color:#111111;">
            <p style="margin:0 0 6px; font-size:17px; font-weight:bold; color:#000000;"><?= e($b['titolo']) ?></p>
            <p style="margin:0 0 12px;"><?= e($b['testo']) ?></p>
            <a href="<?= e($b['url']) ?>" style="font-weight:bold; color:#000000;"><?= e($b['link']) ?></a>
          </td></tr>
        </table>
        <?php endif; ?>
        <?php endforeach; ?>
      </td>
    </tr>
    <tr>
      <td style="padding:16px 28px 22px; border-top:2px solid #000000; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.5; color:#5c5c5c;">
        <?= e(MAIL_FIRMA) ?><br>
        <a href="<?= e($site) ?>" style="color:#000000;"><?= e($site_label) ?></a>
      </td>
    </tr>
  </table>
</td></tr>
</table>
</body>
</html>
