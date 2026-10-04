<?php
defined('CAROVANA') || exit;
// Variabili opzionali: $title, $body_class, $noindex, $cdn_css (lista di URL)
$page = basename($_SERVER['SCRIPT_NAME']);
$nav = [
    'index.php' => 'Mappa',
    'principi.php' => 'Principi e linee guida',
    'volantino.php' => 'Volantino',
    'proponi.php' => "Proponi un'iniziativa",
];
?><!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(isset($title) ? $title : SLOGAN) ?> – Carovana</title>
  <meta name="description" content="La Carovana per i diritti dell'abitare del Social Forum dell'Abitare: iniziative in tutta Italia per discutere la proposta di legge dal basso sull'abitare. <?= e(SLOGAN) ?>.">
  <?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
  <script>
    // colori scelti col selettore (vedi colori.js): applicati prima del disegno della pagina
    try {
      var c = JSON.parse(localStorage.getItem('carovana-colori') || '{}');
      if (c.c1) document.documentElement.dataset.c1 = c.c1;
      if (c.c2) document.documentElement.dataset.c2 = c.c2;
    } catch (e) {}
  </script>
  <link rel="icon" href="<?= asset('brand/casa.svg') ?>" type="image/svg+xml">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
  <?php foreach ((isset($cdn_css) ? $cdn_css : []) as $u): ?>
  <link rel="stylesheet" href="<?= e($u) ?>">
  <?php endforeach; ?>
  <link rel="stylesheet" href="<?= asset('style.css') ?>">
</head>
<body class="<?= e(isset($body_class) ? $body_class : '') ?>">
  <header class="topbar">
    <a href="index.php" class="brand" aria-label="Carovana – <?= e(SLOGAN) ?>">
      <?php readfile(__DIR__ . '/casa.svg'); ?>
      <?php readfile(__DIR__ . '/wordmark.svg'); ?>
      <span class="brand-slogan"><?= e(SLOGAN) ?></span>
    </a>
    <nav>
      <?php foreach ($nav as $href => $label): ?>
      <a href="<?= $href ?>"<?= $page === $href ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </header>
