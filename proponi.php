<?php
require __DIR__ . '/inc/bootstrap.php';
list($values, $errors, $sent) = handle_form('iniziative');
$captcha = new_captcha();
$title = "Proponi un'iniziativa";
$body_class = 'page-form';
$scripts = $sent ? [] : ['picker.js'];
require __DIR__ . '/inc/header.php';
?>
<main class="form-wrap">
  <div class="form-intro">
    <h1>Proponi un'iniziativa</h1>
    <p>La Carovana del Social Forum dell'Abitare sta per partire!</p>
    <p>Stiamo organizzando iniziative, dibattiti, momenti di approfondimento e vere e proprie vertenze, per discutere del problema dell'abitare a partire dalla proposta di legge dal basso del SFA.</p>
    <p>Ti chiediamo di discuterla, mettere alla prova le proposte, aggiungere ciò che manca e restituirci ciò che emergerà.</p>
  </div>
  <?php if ($sent): ?>
  <div class="done">
    <h2>Grazie, proposta ricevuta!</h2>
    <p>Le daremo un'occhiata e, una volta approvata, la tua iniziativa comparirà sulla mappa della Carovana. Se serve qualche chiarimento ti scriviamo all'email che ci hai lasciato.</p>
    <p><a class="btn" href="index.php">Torna alla mappa</a> <a class="btn btn-ghost" href="proponi.php">Proponi un'altra iniziativa</a></p>
  </div>
  <?php else: ?>
  <form method="post" class="form" novalidate>
    <?php if ($errors): ?><p class="form-error" role="alert">Controlla i campi segnati in rosso.</p><?php endif; ?>
    <?php render_fields(FORM_INIZIATIVA, $values, $errors, $captcha); ?>
    <button type="submit" class="btn btn-big">Invia</button>
  </form>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
