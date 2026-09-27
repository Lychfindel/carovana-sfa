<?php
// Pagina non collegata dal sito: il link viene inviato per email agli organizzatori delle iniziative.
require __DIR__ . '/inc/bootstrap.php';
list($values, $errors, $sent, $files_lost) = handle_form('contributi', false, 'check_contributo');
$iniziative = approved_iniziative();
$preselect = get_str('iniziativa');
$title = 'Contribuisci alla proposta';
$body_class = 'page-form';
$noindex = true;
$scripts = ['foto.js'];
require __DIR__ . '/inc/header.php';
?>
<main class="form-wrap">
  <div class="form-intro">
    <h1>Contribuisci alla proposta di legge</h1>

    <p><strong>La Carovana dei diritti dell’abitare è partita. Ora tocca a tutte e tutti noi farla avanzare inserisci le proposte!</strong></p>
    <p>Da tre anni il Social Forum Abitare lavora per costruire uno spazio comune di confronto e di iniziativa sul diritto all’abitare, 
      mettendo in relazione realtà, movimenti, associazioni, comitati, sindacati e persone che ogni giorno si confrontano 
      con le tante forme della crisi abitativa. In questi anni abbiamo provato a fare un passo ulteriore:
      <strong>scrivere insieme alcuni punti che riteniamo imprescindibili per una proposta di legge dal basso sull’abitare.</strong> 
      Un primo patrimonio di idee, proposte e rivendicazioni, costruito a partire dalle esperienze e dalle lotte che 
      attraversano i territori: realtà diverse, ma spesso accomunate dagli stessi interrogativi, dalle stesse difficoltà 
      e dalla ricerca di risposte condivise.</p>
    <p><strong>Questo modulo aperto serve per raccogliere il contributo che nasce dal confronto sulla proposta che avete promosso nei territori, nei circoli, fra le organizzazioni a voi vicine, sui posti di lavoro.</strong></p>
    <p>potete liberamente inserire: <strong>osservazioni, proposte, integrazioni, critiche, esperienze, materiali e nuovi punti da mettere in discussione.</strong></p>
    <br>
    <p>Per inviare il contributo usa la <strong>stessa email</strong> con cui hai proposto l'iniziativa.</p>
  </div>
  <?php if ($sent): ?>
  <div class="done">
    <h2>Grazie per il tuo contributo!</h2>
    <p>Lo leggeremo con attenzione. Una volta approvato comparirà, con le foto, nella scheda dell'iniziativa sulla mappa.</p>
    <p><a class="btn" href="index.php">Torna alla mappa</a> <a class="btn btn-ghost" href="contribuisci.php">Invia un altro contributo</a></p>
  </div>
  <?php else: ?>
  <form method="post" class="form" enctype="multipart/form-data" novalidate>
    <?php if ($errors): ?><p class="form-error" role="alert">Controlla i campi segnati in rosso.<?php if ($files_lost): ?> Per sicurezza il browser non conserva i file: seleziona di nuovo le foto.<?php endif; ?></p><?php endif; ?>
    <?php render_fields(FORM_CONTRIBUTO, $values, $errors, null, $iniziative, $preselect); ?>
    <button type="submit" class="btn btn-big">Invia</button>
  </form>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/inc/footer.php'; ?>
