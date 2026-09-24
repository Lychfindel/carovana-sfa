<?php
defined('CAROVANA') || exit;
// Variabili attese: $title (opzionale), $body_class (opzionale), $noindex (opzionale)
$page = basename($_SERVER['SCRIPT_NAME']);
$nav = [
    'index.php' => 'Mappa',
    'principi.php' => 'Principi e linee guida',
    'volantino.php' => 'Volantino',
    'proponi.php' => "Proponi un'iniziativa",
    'contribuisci.php' => 'Contribuisci',
];
?><!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(isset($title) ? $title : "Carovana per i diritti dell'abitare") ?> – SFA</title>
  <meta name="description" content="La Carovana del Social Forum dell'Abitare: iniziative in tutta Italia per discutere la proposta di legge dal basso sull'abitare.">
  <?php if (!empty($noindex)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
  <link rel="icon" href="static/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
  <link rel="stylesheet" href="static/style.css?v=<?= filemtime(__DIR__ . '/../static/style.css') ?>">
</head>
<body class="<?= e(isset($body_class) ? $body_class : '') ?>">
  <header class="topbar">
    <a href="index.php" class="brand">
      <img src="static/logo.png" alt="">
      <span>Carovana <em>per i diritti dell'abitare</em></span>
    </a>
    <nav>
      <?php foreach ($nav as $href => $label): ?>
      <a href="<?= $href ?>"<?= $page === $href ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </header>
