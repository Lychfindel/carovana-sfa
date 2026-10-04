<?php defined('CAROVANA') || exit;
// Variabili opzionali: $cdn_scripts (URL esterni, caricati dopo Leaflet), $scripts (file in static/)
?>

  <!-- Selettore dei colori: temporaneo, finché non viene scelta la palette definitiva -->
  <div class="colori" id="colori">
    <button type="button" class="colori-toggle" aria-expanded="false" aria-controls="colori-panel">
      <span class="colori-swatch" aria-hidden="true"><i class="s1"></i><i class="s2"></i></span> Colori
    </button>
    <div class="colori-panel" id="colori-panel" hidden>
      <fieldset>
        <legend>Principale</legend>
        <div class="colori-row" data-slot="c1"></div>
      </fieldset>
      <fieldset>
        <legend>Secondario</legend>
        <div class="colori-row" data-slot="c2"></div>
      </fieldset>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
  <script src="<?= e(asset('colori.js')) ?>"></script>
  <?php foreach ((isset($cdn_scripts) ? $cdn_scripts : []) as $u): ?>
  <script src="<?= e($u) ?>"></script>
  <?php endforeach; ?>
  <?php foreach ((isset($scripts) ? $scripts : []) as $s): ?>
  <script src="<?= e(asset($s)) ?>"></script>
  <?php endforeach; ?>
</body>
</html>
