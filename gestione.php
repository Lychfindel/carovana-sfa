<?php
// Pagina di gestione (nascosta, protetta da password). Puoi rinominare questo file
// per renderne l'indirizzo meno prevedibile.
require __DIR__ . '/inc/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');

function check_password($pw)
{
    if (ADMIN_PASSWORD_HASH !== '') {
        return password_verify($pw, ADMIN_PASSWORD_HASH);
    }
    return ADMIN_PASSWORD !== '' && hash_equals(ADMIN_PASSWORD, $pw);
}

$is_admin = !empty($_SESSION['admin']);
$self = basename(__FILE__);

if (isset($_GET['esci'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

// --- API usata dall'editor (admin.js) ---
if (isset($_GET['api'])) {
    $table = get_str('api');
    if (!$is_admin) json_out(['error' => 'forbidden'], 403);
    if (!isset(tables()[$table])) json_out(['error' => 'not found'], 404);
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        json_out(['rows' => read_rows($table)]);
    }
    $csrf = isset($_SERVER['HTTP_X_CSRF']) ? $_SERVER['HTTP_X_CSRF'] : '';
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) json_out(['error' => 'forbidden'], 403);
    $body = json_decode(file_get_contents('php://input'), true);
    $rows = (is_array($body) && isset($body['rows']) && is_array($body['rows'])) ? $body['rows'] : null;
    if ($rows === null) json_out(['error' => 'bad request'], 400);
    $clean = [];
    foreach ($rows as $r) {
        $c = [];
        foreach (tables()[$table]['columns'] as $col) {
            $c[$col] = (is_array($r) && isset($r[$col]) && is_scalar($r[$col])) ? (string)$r[$col] : '';
        }
        if (!preg_match(RE_ID, $c['id'])) $c['id'] = new_id();
        $c['approvata'] = is_true($c['approvata']) ? 'True' : 'False';
        if (array_key_exists('foto', $c)) $c['foto'] = implode(';', foto_list($c['foto']));
        $clean[] = $c;
    }
    $old_rows = with_lock(function () use ($table, $clean) {
        $old_rows = [];
        foreach (read_rows($table) as $r) $old_rows[$r['id']] = $r;
        if ($table === 'contributi') { // elimina dal disco le foto tolte o dei contributi eliminati
            $keep = [];
            foreach ($clean as $c) $keep[$c['id']] = foto_list($c['foto']);
            foreach ($old_rows as $old) {
                delete_photos($old['id'], isset($keep[$old['id']]) ? $keep[$old['id']] : []);
            }
        }
        write_rows($table, $clean);
        return $old_rows;
    });
    // avvisa chi ha inviato le righe appena approvate (da non approvata ad approvata)
    $notificati = 0;
    foreach ($clean as $c) {
        if (is_true($c['approvata']) && isset($old_rows[$c['id']]) && !is_true($old_rows[$c['id']]['approvata'])
                && notify_approved($table, $c)) {
            $notificati++;
        }
    }
    json_out(['ok' => true, 'rows' => $clean, 'notificati' => $notificati]);
}

// --- Download CSV ---
if (isset($_GET['csv'])) {
    $table = get_str('csv');
    if (!$is_admin) { http_response_code(403); exit('Accesso negato'); }
    if (!isset(tables()[$table])) { http_response_code(404); exit; }
    $file = tables()[$table]['file'];
    if (!is_file($file)) write_rows($table, []);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $table . '.csv"');
    readfile($file);
    exit;
}

// --- Login ---
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_admin) {
    if (check_password(isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '')) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        header('Location: ' . $self);
        exit;
    }
    sleep(1); // rallenta i tentativi a raffica
    $error = 'Password errata';
}

$title = $is_admin ? 'Gestione' : 'Accesso riservato';
$noindex = true;
$body_class = $is_admin ? 'page-admin' : 'page-form';

if (!$is_admin):
    require __DIR__ . '/inc/header.php'; ?>
<main class="form-wrap narrow">
  <form method="post" class="form">
    <h1>Area riservata</h1>
    <div class="field<?= $error ? ' has-error' : '' ?>">
      <label for="pw">Password</label>
      <input type="password" id="pw" name="password" autofocus required autocomplete="current-password">
      <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
    </div>
    <button type="submit" class="btn btn-big">Entra</button>
  </form>
</main>
<?php
    require __DIR__ . '/inc/footer.php';
    exit;
endif;

$schema = [];
foreach (tables() as $name => $t) {
    $fields = [];
    foreach ($t['form'] as $f) $fields[$f['name']] = $f;
    $schema[$name] = ['columns' => $t['columns'], 'fields' => (object)$fields];
}
$scripts = ['admin.js'];
$json = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
require __DIR__ . '/inc/header.php';
?>
<main class="admin">
  <div class="admin-head">
    <h1>Gestione Carovana</h1>
    <a href="<?= e($self) ?>?esci=1" class="btn btn-small btn-ghost">Esci</a>
  </div>
  <div class="tabs admin-tabs" role="tablist">
    <button class="tab" data-table="iniziative" aria-selected="true">Iniziative <b id="c-iniziative"></b></button>
    <button class="tab" data-table="contributi" aria-selected="false">Contributi <b id="c-contributi"></b></button>
  </div>
  <div class="toolbar">
    <select id="f-state" aria-label="Filtra">
      <option value="todo">Da approvare</option>
      <option value="ok">Approvate</option>
      <option value="all">Tutte</option>
    </select>
    <input type="search" id="f-q" placeholder="Cerca…" aria-label="Cerca">
    <span class="spacer"></span>
    <button class="btn btn-small btn-ghost" id="add">+ Nuova riga</button>
    <a class="btn btn-small btn-ghost" id="dl" href="#">Scarica CSV</a>
    <button class="btn btn-small" id="save" disabled>Salva modifiche</button>
  </div>
  <p class="status" id="status" role="status"></p>
  <div id="rows" class="rows"></div>
</main>
<script>
  window.ADMIN = {
    schema: <?= json_encode($schema, $json) ?>,
    csrf: <?= json_encode($_SESSION['csrf'], $json) ?>,
    api: <?= json_encode($self . '?api=__T__', $json) ?>,
    csv: <?= json_encode($self . '?csv=__T__', $json) ?>,
    foto: <?= json_encode('foto.php?c=__ID__&n=__N__', $json) ?>
  };
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
