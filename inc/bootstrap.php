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

function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
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
        } elseif ($v !== '' && $f['type'] === 'iniziativa' && $v !== 'generale'
                  && !in_array($v, array_column(approved_iniziative(), 'id'), true)) {
            $errors[$name] = 'Iniziativa non trovata';
        }
        $row[$name] = cut($v, 5000);
    }
    return [$row, $errors];
}

/** Gestisce GET/POST di un form pubblico; restituisce [values, errors, sent]. */
function handle_form($table)
{
    $values = [];
    $errors = [];
    $sent = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $values = array_map(function ($v) { return is_string($v) ? $v : ''; }, $_POST);
        if (!empty($values['sito_web'])) { // honeypot anti-spam: i bot lo compilano
            $sent = true;
        } else {
            list($row, $errors) = validate(tables()[$table]['form'], $values);
            $captcha = isset($values['captcha']) ? trim($values['captcha']) : '';
            if (!ctype_digit($captcha) || (int)$captcha !== (isset($_SESSION['captcha']) ? $_SESSION['captcha'] : -1)) {
                $errors['captcha'] = 'Risultato sbagliato, riprova';
            }
            if (!$errors) {
                $row['id'] = new_id();
                $row['inviato_il'] = date('Y-m-d H:i:s');
                $row['approvata'] = 'False';
                append_row($table, $row);
                $sent = true;
            }
        }
    }
    return [$values, $errors, $sent];
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
                echo '<option value="generale"' . ($cur === 'generale' ? ' selected' : '') . '>Nessuna in particolare (contributo generale)</option>';
                echo '</select>';
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
