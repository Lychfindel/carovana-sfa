<?php
require __DIR__ . '/inc/bootstrap.php';
list($values, $errors, $sent) = handle_form('contributi');
$captcha = new_captcha();
$iniziative = approved_iniziative();
$preselect = isset($_GET['iniziativa']) ? (string)$_GET['iniziativa'] : '';
$title = 'Contribuisci alla proposta';
$body_class = 'page-form';
require __DIR__ . '/inc/header.php';
?>
<main class="form-wrap">
  <div class="form-intro">
    <h1>Contribuisci alla proposta di legge</h1>
    <p>Hai partecipato o organizzato una tappa della Carovana? Raccontaci cosa è emerso: critiche, modifiche, integrazioni e esperienze dai territori alla proposta di legge dal basso del Social Forum dell'Abitare.</p>
  </div>
  <?php if ($sent): ?>
  <div class="done">
    <h2>Grazie per il tuo contributo!</h2>
    <p>Lo leggeremo con attenzione. Una volta approvato comparirà nella scheda dell'iniziativa sulla mappa.</p>
    <p><a class="btn" href="index.php">Torna alla mappa</a> <a class="btn btn-ghost" href="contribuisci.php">Invia un altro contributo</a></p>
  </div>
  <?php else: ?>
  <form method="post" action="contribuisci.php" class="form" novalidate>
    <?php if ($errors): ?><p class="form-error" role="alert">Controlla i campi segnati in rosso.</p><?php endif; ?>
    <?php render_fields(FORM_CONTRIBUTO, $values, $errors, $captcha, $iniziative, $preselect); ?>
    <button type="submit" class="btn btn-big">Invia</button>
  </form>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
