<?php
// Carovana per i diritti dell'abitare - Social Forum dell'Abitare.
// Funzioni condivise: CSV, validazione dei form, sessione, rendering dei campi.

define('CAROVANA', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/forms.php';

date_default_timezone_set(TIMEZONE);

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0775, true);
}

session_name('carovana');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

const META_START = ['id', 'inviato_il'];
const META_END = ['approvata'];

// Foto dei contributi: data/foto/<id contributo>/<n>.jpg (+ <n>_t.jpg miniatura)
define('FOTO_DIR', DATA_DIR . '/foto');
const FOTO_MAX = 6;
const FOTO_MAX_BYTES = 10 * 1024 * 1024;
const RE_ID = '/^[0-9a-f]{8}$/';
const RE_FOTO = '/^\d{1,2}(_t)?\.jpg$/';

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Indirizzo di un file in static/ con la data di modifica, così il browser non usa versioni vecchie in cache. */
function asset($path)
{
    $file = __DIR__ . '/../static/' . $path;
    return 'static/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/** Parametro GET come stringa ('' se manca o se è un array, es. ?c[]=...). */
function get_str($k)
{
    return isset($_GET[$k]) && is_string($_GET[$k]) ? $_GET[$k] : '';
}

function cut($s, $n)
{
    return function_exists('mb_substr') ? mb_substr($s, 0, $n, 'UTF-8') : iconv_substr($s, 0, $n, 'UTF-8');
}

function columns_of($form)
{
    $cols = [];
    foreach ($form as $f) {
        if ($f['type'] === 'map') {
            $cols[] = 'lat';
            $cols[] = 'lng';
        } else {
            $cols[] = $f['name'];
        }
    }
    return array_merge(META_START, $cols, META_END);
}

function tables()
{
    static $t = null;
    if ($t === null) {
        $t = [
            'iniziative' => ['file' => DATA_DIR . '/iniziative.csv', 'form' => FORM_INIZIATIVA],
            'contributi' => ['file' => DATA_DIR . '/contributi.csv', 'form' => FORM_CONTRIBUTO],
        ];
        foreach ($t as &$x) {
            $x['columns'] = columns_of($x['form']);
            $pub = array_values(array_filter($x['form'], function ($f) { return $f['public']; }));
            $x['public'] = array_values(array_diff(columns_of($pub), META_START, META_END));
        }
    }
    return $t;
}

// ---------------------------------------------------------------------------
// CSV
// ---------------------------------------------------------------------------
function with_lock($fn)
{
    $h = fopen(DATA_DIR . '/.lock', 'c');
    flock($h, LOCK_EX);
    try {
        return $fn();
    } finally {
        flock($h, LOCK_UN);
        fclose($h);
    }
}

function read_rows($table)
{
    $t = tables()[$table];
    if (!is_file($t['file'])) {
        return [];
    }
    $h = fopen($t['file'], 'r');
    $header = fgetcsv($h, 0, ',', '"', '');
    $rows = [];
    if ($header) {
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); // BOM di Excel
        while (($r = fgetcsv($h, 0, ',', '"', '')) !== false) {
            if ($r === [null]) {
                continue; // riga vuota
            }
            $assoc = [];
            foreach ($header as $i => $k) {
                $assoc[$k] = isset($r[$i]) ? $r[$i] : '';
            }
            // garantisce tutte le colonne anche se il CSV è stato creato con meno campi
            $row = [];
            foreach ($t['columns'] as $c) {
                $row[$c] = isset($assoc[$c]) ? (string)$assoc[$c] : '';
            }
            $rows[] = $row;
        }
    }
    fclose($h);
    return $rows;
}

function write_rows($table, $rows)
{
    $t = tables()[$table];
    $tmp = $t['file'] . '.tmp';
    $h = fopen($tmp, 'w');
    fputcsv($h, $t['columns'], ',', '"', '');
    foreach ($rows as $r) {
        $line = [];
        foreach ($t['columns'] as $c) {
            $line[] = isset($r[$c]) ? $r[$c] : '';
        }
        fputcsv($h, $line, ',', '"', '');
    }
    fclose($h);
    rename($tmp, $t['file']);
}

function append_row($table, $row)
{
    with_lock(function () use ($table, $row) {
        $rows = read_rows($table);
        $rows[] = $row;
        write_rows($table, $rows);
    });
}

function is_true($v)
{
    return in_array(strtolower(trim((string)$v)), ['true', '1', 'si', 'sì', 'yes', 'vero'], true);
}

function new_id()
{
    return bin2hex(random_bytes(4));
}

function approved_iniziative()
{
    $out = [];
    foreach (read_rows('iniziative') as $r) {
        if (is_true($r['approvata']) && is_numeric($r['lat']) && is_numeric($r['lng'])) {
            $out[] = $r;
        }
    }
    usort($out, function ($a, $b) { return strcmp($a['data'], $b['data']); });
    return $out;
}

// ---------------------------------------------------------------------------
// Foto
// ---------------------------------------------------------------------------
function foto_list($value)
{
    $out = [];
    foreach (explode(';', (string)$value) as $n) {
        if (preg_match(RE_FOTO, $n) && substr($n, -6) !== '_t.jpg') {
            $out[] = $n;
        }
    }
    return $out;
}

/** I file caricati nel campo "foto", come lista di ['name', 'tmp', 'error', 'size']. */
function uploaded_files($field)
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
        return [];
    }
    $out = [];
    foreach ($_FILES[$field]['name'] as $i => $name) {
        if ($_FILES[$field]['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = ['name' => (string)$name, 'tmp' => $_FILES[$field]['tmp_name'][$i],
                  'error' => $_FILES[$field]['error'][$i], 'size' => $_FILES[$field]['size'][$i]];
    }
    return $out;
}

function memory_limit_bytes()
{
    $v = trim((string)ini_get('memory_limit'));
    if ($v === '' || $v === '-1') return PHP_INT_MAX;
    $n = (int)$v;
    switch (strtolower(substr($v, -1))) {
        case 'g': $n *= 1024; // no break
        case 'm': $n *= 1024; // no break
        case 'k': $n *= 1024;
    }
    return $n;
}

/** Ridimensiona $src per stare in $max x $max e la salva in JPEG (GD non scrive metadati EXIF). */
function save_jpeg($src, $max, $path, $quality)
{
    $w = imagesx($src);
    $h = imagesy($src);
    $k = min(1, $max / max($w, $h));
    $nw = max(1, (int)round($w * $k));
    $nh = max(1, (int)round($h * $k));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // sfondo bianco per i PNG trasparenti
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = imagejpeg($dst, $path, $quality);
    imagedestroy($dst);
    return $ok;
}

/** Apre una foto caricata con GD e la raddrizza secondo l'orientamento EXIF. Lancia RuntimeException. */
function open_photo($f)
{
    $label = '«' . $f['name'] . '»';
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'] > FOTO_MAX_BYTES) {
        throw new RuntimeException("$label supera i 10 MB");
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp'])) {
        throw new RuntimeException("Caricamento di $label non riuscito, riprova");
    }
    $info = @getimagesize($f['tmp']);
    $types = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng'];
    if (defined('IMAGETYPE_WEBP') && function_exists('imagecreatefromwebp')) {
        $types[IMAGETYPE_WEBP] = 'imagecreatefromwebp';
    }
    if (!$info || !isset($types[$info[2]])) {
        throw new RuntimeException("$label non è una foto JPG, PNG o WebP valida");
    }
    // GD tiene tutta l'immagine in memoria: meglio un errore chiaro che un errore fatale di PHP
    $need = $info[0] * $info[1] * 5 + memory_get_usage() + 16 * 1024 * 1024;
    if ($need > memory_limit_bytes()) {
        @ini_set('memory_limit', (string)ceil($need / 1048576 + 32) . 'M');
        if ($need > memory_limit_bytes()) {
            throw new RuntimeException("$label ha una risoluzione troppo alta per il server: riducila e riprova");
        }
    }
    $img = @$types[$info[2]]($f['tmp']);
    if (!$img) {
        throw new RuntimeException("$label non è una foto JPG, PNG o WebP valida");
    }
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($f['tmp']);
        $o = isset($exif['Orientation']) ? (int)$exif['Orientation'] : 1;
        if (in_array($o, [2, 4, 5, 7], true)) imageflip($img, IMG_FLIP_HORIZONTAL);
        $angle = [3 => 180, 4 => 180, 5 => 270, 6 => 270, 7 => 90, 8 => 90];
        if (isset($angle[$o])) {
            $rot = imagerotate($img, $angle[$o], 0);
            imagedestroy($img);
            $img = $rot;
        }
    }
    return $img;
}

/** Ricodifica le foto caricate in JPEG (toglie i metadati EXIF, es. la posizione GPS). Restituisce [nomi, errore]. */
function save_photos($files, $cid)
{
    if (!$files) {
        return [[], null];
    }
    if (count($files) > FOTO_MAX) {
        return [[], 'Puoi caricare al massimo ' . FOTO_MAX . ' foto'];
    }
    if (!function_exists('imagecreatetruecolor')) {
        return [[], "Il server non può elaborare le foto (manca l'estensione PHP GD)"];
    }
    $folder = FOTO_DIR . '/' . $cid;
    if (!is_dir($folder)) mkdir($folder, 0775, true);
    $names = [];
    try {
        foreach (array_values($files) as $i => $f) {
            $n = $i + 1;
            $img = open_photo($f);
            $ok = save_jpeg($img, 2000, "$folder/$n.jpg", 85) && save_jpeg($img, 480, "$folder/{$n}_t.jpg", 80);
            imagedestroy($img);
            if (!$ok) throw new RuntimeException('Salvataggio delle foto non riuscito');
            $names[] = "$n.jpg";
        }
    } catch (RuntimeException $e) {
        delete_photos($cid);
        return [[], $e->getMessage()];
    }
    return [$names, null];
}

function delete_photos($cid, $keep = [])
{
    $folder = FOTO_DIR . '/' . $cid;
    if (!preg_match(RE_ID, $cid) || !is_dir($folder)) {
        return;
    }
    $keep = array_merge($keep, array_map(function ($n) { return str_replace('.jpg', '_t.jpg', $n); }, $keep));
    foreach (scandir($folder) as $name) {
        if ($name !== '.' && $name !== '..' && !in_array($name, $keep, true)) {
            @unlink("$folder/$name");
        }
    }
    @rmdir($folder); // riesce solo se la cartella è rimasta vuota
}

function foto_url($cid, $name, $thumb = false)
{
    return 'foto.php?c=' . rawurlencode($cid) . '&n=' . rawurlencode($thumb ? str_replace('.jpg', '_t.jpg', $name) : $name);
}

// ---------------------------------------------------------------------------
// Form
// ---------------------------------------------------------------------------
function new_captcha()
{
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION['captcha'] = $a + $b;
    return "$a + $b =";
}

function validate($form, $data)
{
    $row = [];
    $errors = [];
    foreach ($form as $f) {
        $name = $f['name'];
        if ($f['type'] === 'foto') {
            continue; // gestite a parte da save_photos
        }
        if ($f['type'] === 'map') {
            $lat = trim(isset($data['lat']) ? $data['lat'] : '');
            $lng = trim(isset($data['lng']) ? $data['lng'] : '');
            if (is_numeric($lat) && is_numeric($lng) && abs($lat) <= 90 && abs($lng) <= 180) {
                $row['lat'] = sprintf('%.6f', $lat);
                $row['lng'] = sprintf('%.6f', $lng);
            } else {
                $errors[$name] = 'Segna un punto sulla mappa';
            }
            continue;
        }
        $v = trim(isset($data[$name]) ? (string)$data[$name] : '');
        if ($f['required'] && $v === '') {
            $errors[$name] = 'Campo obbligatorio';
        } elseif ($v !== '' && $f['type'] === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $errors[$name] = 'Email non valida';
        } elseif ($v !== '' && $f['type'] === 'url' && !preg_match('#^https?://#i', $v)) {
            $v = 'https://' . $v;
        } elseif ($v !== '' && in_array($f['type'], ['select', 'radio'], true) && !in_array($v, $f['options'], true)) {
            $errors[$name] = 'Scelta non valida';
        } elseif ($v !== '' && $f['type'] === 'datetime-local') {
            $d = DateTime::createFromFormat('Y-m-d\TH:i', $v);
            if (!$d || $d->format('Y-m-d\TH:i') !== $v) {
                $errors[$name] = 'Data non valida';
            }
        } elseif ($v !== '' && $f['type'] === 'iniziativa'
                  && !in_array($v, array_column(approved_iniziative(), 'id'), true)) {
            $errors[$name] = 'Iniziativa non trovata';
        }
        $row[$name] = cut($v, 5000);
    }
    return [$row, $errors];
}

/** Antispam del form contributi: l'email deve essere quella usata per proporre l'iniziativa. */
function check_email_iniziativa($row, &$errors)
{
    if (isset($errors['iniziativa_id']) || isset($errors['email'])) {
        return;
    }
    foreach (read_rows('iniziative') as $r) {
        if ($r['id'] === $row['iniziativa_id']) {
            if (strtolower(trim($r['email'])) === strtolower(trim($row['email']))) {
                return;
            }
            break;
        }
    }
    $errors['email'] = "L'email non corrisponde a quella usata per proporre questa iniziativa";
}

function check_contributo($row, &$errors)
{
    check_email_iniziativa($row, $errors);
    // nessun campo del contenuto è obbligatorio, ma un contributo vuoto non ha senso
    if ($row['info'] === '' && $row['proposte'] === '' && !uploaded_files('foto')) {
        $errors['proposte'] = 'Scrivi qualcosa o aggiungi almeno una foto';
    }
}

/**
 * Gestisce GET/POST di un form pubblico; restituisce [values, errors, sent, files_lost].
 * $captcha: chiede il calcolo anti-spam; $check: controllo aggiuntivo function($row, &$errors).
 */
function handle_form($table, $captcha = true, $check = null)
{
    $values = [];
    $errors = [];
    $sent = false;
    $form = tables()[$table]['form'];
    $has_foto = in_array('foto', array_column($form, 'type'), true);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $values = array_map(function ($v) { return is_string($v) ? $v : ''; }, $_POST);
        if (!$_POST && !empty($_SERVER['CONTENT_LENGTH'])) {
            // la richiesta supera post_max_size: PHP la scarta per intero
            $errors['foto'] = 'Le foto sono troppo pesanti tutte insieme: prova con meno foto o più leggere';
            return [$values, $errors, false, true];
        }
        if (!empty($values['sito_web'])) { // honeypot anti-spam: i bot lo compilano
            $sent = true;
        } else {
            list($row, $errors) = validate($form, $values);
            if ($captcha) {
                $c = isset($values['captcha']) ? trim($values['captcha']) : '';
                if (!ctype_digit($c) || (int)$c !== (isset($_SESSION['captcha']) ? $_SESSION['captcha'] : -1)) {
                    $errors['captcha'] = 'Risultato sbagliato, riprova';
                }
            }
            if ($check) {
                $check($row, $errors);
            }
            $row['id'] = new_id();
            if (!$errors && $has_foto) {
                list($names, $err) = save_photos(uploaded_files('foto'), $row['id']);
                if ($err) {
                    $errors['foto'] = $err;
                }
                $row['foto'] = implode(';', $names);
            }
            if (!$errors) {
                $row['inviato_il'] = date('Y-m-d H:i:s');
                $row['approvata'] = 'False';
                append_row($table, $row);
                $sent = true;
            }
        }
    }
    $files_lost = $errors && $has_foto && uploaded_files('foto');
    return [$values, $errors, $sent, $files_lost];
}

function val($values, $k, $default = '')
{
    return isset($values[$k]) ? $values[$k] : $default;
}

function render_fields($fields, $values, $errors, $captcha, $iniziative = [], $preselect = '')
{
    echo '<input type="text" name="sito_web" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">' . "\n";
    foreach ($fields as $f) {
        $n = $f['name'];
        $err = isset($errors[$n]) ? $errors[$n] : null;
        $req = !empty($f['required']) ? ' required' : '';
        $v = val($values, $n);
        echo '<div class="field' . ($err ? ' has-error' : '') . '">';
        echo '<label for="f-' . e($n) . '">' . e($f['label'])
            . ($req ? ' <span class="req" aria-label="obbligatorio">*</span>' : '') . '</label>';
        if (!empty($f['help'])) {
            echo '<p class="help">' . e($f['help']) . '</p>';
        }
        $ph = e(isset($f['placeholder']) ? $f['placeholder'] : '');
        switch ($f['type']) {
            case 'textarea':
                echo '<textarea id="f-' . e($n) . '" name="' . e($n) . '" rows="6" placeholder="' . $ph . '"' . $req . '>' . e($v) . '</textarea>';
                break;
            case 'select':
                echo '<select id="f-' . e($n) . '" name="' . e($n) . '"' . $req . '><option value="">Scegli…</option>';
                foreach ($f['options'] as $o) {
                    echo '<option' . ($v === $o ? ' selected' : '') . '>' . e($o) . '</option>';
                }
                echo '</select>';
                break;
            case 'radio':
                echo '<div class="choices" id="f-' . e($n) . '">';
                foreach ($f['options'] as $o) {
                    echo '<label class="choice"><input type="radio" name="' . e($n) . '" value="' . e($o) . '"'
                        . ($v === $o ? ' checked' : '') . $req . '> ' . e($o) . '</label>';
                }
                echo '</div>';
                break;
            case 'iniziativa':
                $cur = val($values, $n, $preselect);
                echo '<select id="f-' . e($n) . '" name="' . e($n) . '"' . $req . '><option value="">Scegli…</option>';
                foreach ($iniziative as $i) {
                    echo '<option value="' . e($i['id']) . '"' . ($cur === $i['id'] ? ' selected' : '') . '>'
                        . e(str_replace('-', '/', substr($i['data'], 0, 10)) . ' · ' . $i['citta'] . ' – ' . $i['titolo']) . '</option>';
                }
                echo '</select>';
                break;
            case 'foto':
                echo '<label class="file-drop" for="f-' . e($n) . '">'
                    . '<input id="f-' . e($n) . '" name="' . e($n) . '[]" type="file" accept="image/jpeg,image/png,image/webp" multiple data-max="' . FOTO_MAX . '" data-max-mb="10">'
                    . '<span class="file-drop-txt">📷 Scegli le foto <small>oppure trascinale qui</small></span>'
                    . '</label>'
                    . '<div class="file-previews" id="f-' . e($n) . '-previews" aria-live="polite"></div>';
                break;
            case 'map':
                echo '<div class="picker">'
                    . '<div class="picker-search">'
                    . '<input type="search" id="geo-q" placeholder="Cerca un indirizzo, es. Campo Santa Margherita, Venezia" aria-label="Cerca un indirizzo">'
                    . '<button type="button" class="btn btn-small" id="geo-btn">Cerca</button>'
                    . '</div>'
                    . '<p class="geo-msg" id="geo-msg" role="status"></p>'
                    . '<div id="picker-map" class="picker-map"></div>'
                    . '<p class="help">Oppure clicca direttamente sulla mappa. Puoi trascinare il segnaposto per spostarlo.</p>'
                    . '</div>'
                    . '<input type="hidden" name="lat" id="lat" value="' . e(val($values, 'lat')) . '">'
                    . '<input type="hidden" name="lng" id="lng" value="' . e(val($values, 'lng')) . '">';
                break;
            default:
                $type = $f['type'] === 'url' ? 'text" inputmode="url' : $f['type'];
                echo '<input id="f-' . e($n) . '" name="' . e($n) . '" type="' . $type . '" value="' . e($v) . '" placeholder="' . $ph . '"' . $req . '>';
        }
        if ($err) {
            echo '<p class="error">' . e($err) . '</p>';
        }
        echo "</div>\n";
    }
    if ($captcha === null) {
        return;
    }
    $err = isset($errors['captcha']) ? $errors['captcha'] : null;
    echo '<div class="field captcha' . ($err ? ' has-error' : '') . '">'
        . '<label for="f-captcha">Controllo anti-spam <span class="req">*</span></label>'
        . '<div class="captcha-row"><span>' . e($captcha) . '</span><input id="f-captcha" name="captcha" inputmode="numeric" autocomplete="off" required></div>'
        . ($err ? '<p class="error">' . e($err) . '</p>' : '')
        . "</div>\n";
}

function json_out($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
