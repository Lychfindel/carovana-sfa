<?php defined('CAROVANA') || exit; ?>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
  <?php foreach ((isset($scripts) ? $scripts : []) as $s): ?>
  <script src="static/<?= e($s) ?>?v=<?= filemtime(__DIR__ . '/../static/' . $s) ?>"></script>
  <?php endforeach; ?>
</body>
</html>
