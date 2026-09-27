<?php defined('CAROVANA') || exit;
// Variabili opzionali: $cdn_scripts (URL esterni, caricati dopo Leaflet), $scripts (file in static/)
?>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
  <?php foreach ((isset($cdn_scripts) ? $cdn_scripts : []) as $u): ?>
  <script src="<?= e($u) ?>"></script>
  <?php endforeach; ?>
  <?php foreach ((isset($scripts) ? $scripts : []) as $s): ?>
  <script src="<?= e(asset($s)) ?>"></script>
  <?php endforeach; ?>
</body>
</html>
