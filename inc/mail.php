<?php
// Email (SMTP): conferme a chi invia, avvisi all'admin, avviso di approvazione.
// Se SMTP_HOST non è impostato il sito funziona uguale, senza inviare email.
// Piccolo client SMTP senza dipendenze: STARTTLS o SSL, AUTH PLAIN/LOGIN, testo UTF-8.
defined('CAROVANA') || exit;

const MAIL_FIRMA = "Social Forum dell'Abitare – Carovana per i diritti dell'abitare";

$GLOBALS['mail_queue'] = [];

function mail_enabled()
{
    return SMTP_HOST !== '' && MAIL_FROM !== '';
}

/** "Nome <indirizzo>" → [nome, indirizzo] */
function mail_parse_from($from)
{
    if (preg_match('/^\s*(.*?)\s*<([^<>\s]+)>\s*$/', $from, $m)) {
        return [trim($m[1], " \"'"), $m[2]];
    }
    return ['', trim($from)];
}

function mail_admins()
{
    return array_values(array_filter(array_map('trim', explode(',', ADMIN_EMAIL)), 'mail_valid'));
}

function mail_valid($addr)
{
    return is_string($addr) && filter_var($addr, FILTER_VALIDATE_EMAIL) && !preg_match('/[\r\n<>]/', $addr);
}

/** Intestazione in UTF-8 (RFC 2047), spezzata in parole codificate da ~45 byte. */
function mail_header($s)
{
    $s = trim(preg_replace('/\s+/u', ' ', (string)$s)); // niente a capo negli header
    if (!preg_match('/[^\x20-\x7e]/', $s)) {
        return $s;
    }
    $words = [];
    $chunk = '';
    foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        if (strlen($chunk . $ch) > 45) {
            $words[] = '=?UTF-8?B?' . base64_encode($chunk) . '?=';
            $chunk = '';
        }
        $chunk .= $ch;
    }
    $words[] = '=?UTF-8?B?' . base64_encode($chunk) . '?=';
    return implode("\r\n ", $words);
}

function site_url()
{
    if (SITE_URL !== '') {
        return rtrim(SITE_URL, '/');
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $dir;
}

/** Mette in coda un'email; viene inviata a fine richiesta (vedi mail_flush). */
function send_mail($to, $subject, $body, $reply_to = [])
{
    $to = array_values(array_filter((array)$to, 'mail_valid'));
    if (!mail_enabled() || !$to) {
        return false;
    }
    if (!$GLOBALS['mail_queue'] && !headers_sent()) {
        // tiene la pagina in un buffer per poterla consegnare al browser prima dell'invio (vedi mail_flush)
        ob_start();
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1'); // con la compressione Apache aspetterebbe la fine dell'invio
        }
    }
    $GLOBALS['mail_queue'][] = [$to, $subject, $body . "\n\n-- \n" . MAIL_FIRMA . "\n",
                                array_values(array_filter((array)$reply_to, 'mail_valid'))];
    return true;
}

/** Invia le email in coda dopo aver chiuso la risposta al browser (se il server lo permette). */
function mail_flush()
{
    if (!$GLOBALS['mail_queue']) {
        return;
    }
    ignore_user_abort(true);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close(); // non bloccare altre richieste della stessa sessione durante l'invio
    }
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request(); // PHP-FPM: il visitatore non aspetta l'invio
    } elseif (!headers_sent()) {
        // Apache/mod_php: consegna la pagina completa e chiude la connessione, poi invia
        header('Connection: close');
        header('Content-Length: ' . (ob_get_level() ? ob_get_length() : 0));
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }
    foreach ($GLOBALS['mail_queue'] as $i => $m) {
        try {
            smtp_send($m[0], $m[1], $m[2], $m[3]);
        } catch (Exception $e) { // un problema con la posta non deve mai bloccare il sito
            error_log('Invio email non riuscito a ' . implode(', ', $m[0]) . ': ' . $m[1] . ' - ' . $e->getMessage());
            if (strpos($e->getMessage(), 'connessione') === 0) { // server irraggiungibile: inutile riprovare subito
                error_log('Server SMTP non raggiungibile: ' . (count($GLOBALS['mail_queue']) - $i - 1) . ' altre email non inviate');
                break;
            }
        }
    }
    $GLOBALS['mail_queue'] = [];
}
register_shutdown_function('mail_flush');

function smtp_send($to, $subject, $body, $reply_to)
{
    list($from_name, $from_addr) = mail_parse_from(MAIL_FROM);
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => SMTP_HOST]]);
    $remote = (SMTP_SECURITY === 'ssl' ? 'ssl://' : 'tcp://') . SMTP_HOST . ':' . SMTP_PORT;
    $fp = @stream_socket_client($remote, $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException("connessione a $remote non riuscita: "
            . ($errstr !== '' ? $errstr : 'server non raggiungibile o certificato TLS non valido'));
    }
    stream_set_timeout($fp, 20);
    $cmd = function ($line, $expect, $label = null) use ($fp) {
        if ($line !== null) {
            fwrite($fp, $line . "\r\n");
        }
        $resp = '';
        while (($l = fgets($fp, 1024)) !== false) {
            $resp .= $l;
            if (strlen($l) < 4 || $l[3] !== '-') break; // ultima riga di una risposta multilinea
        }
        if (!in_array((int)substr($resp, 0, 3), (array)$expect, true)) {
            $what = $label ?: ($line === null ? 'connessione' : strtok($line, ' :'));
            throw new RuntimeException("SMTP $what: " . trim($resp ?: 'nessuna risposta'));
        }
        return $resp;
    };
    try {
        $helo = preg_replace('/[^A-Za-z0-9.-]/', '', gethostname() ?: '') ?: 'localhost';
        $cmd(null, 220);
        $ehlo = $cmd("EHLO $helo", 250);
        if (SMTP_SECURITY === 'starttls') {
            $cmd('STARTTLS', 220);
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS non riuscito (certificato non valido?)');
            }
            $ehlo = $cmd("EHLO $helo", 250);
        }
        if (SMTP_USER !== '') {
            if (preg_match('/^250[ -]AUTH\b.*\bPLAIN\b/mi', $ehlo)) {
                $cmd('AUTH PLAIN ' . base64_encode("\0" . SMTP_USER . "\0" . SMTP_PASSWORD), 235, 'AUTH');
            } else {
                $cmd('AUTH LOGIN', 334);
                $cmd(base64_encode(SMTP_USER), 334, 'AUTH');
                $cmd(base64_encode(SMTP_PASSWORD), 235, 'AUTH');
            }
        }
        $cmd("MAIL FROM:<$from_addr>", 250);
        foreach ($to as $a) {
            $cmd("RCPT TO:<$a>", [250, 251]);
        }
        $cmd('DATA', 354);
        $domain = substr(strrchr($from_addr, '@'), 1) ?: 'localhost';
        $headers = [
            'Date: ' . date('r'),
            'From: ' . ($from_name !== '' ? mail_header($from_name) . " <$from_addr>" : $from_addr),
            'To: ' . implode(', ', $to),
            'Subject: ' . mail_header($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        if ($reply_to) {
            $headers[] = 'Reply-To: ' . implode(', ', $reply_to);
        }
        $text = preg_replace("/\r?\n/", "\r\n", $body);
        // base64 non contiene mai righe che iniziano con "." (niente dot-stuffing da gestire)
        $cmd(implode("\r\n", $headers) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($text), 76, "\r\n")) . "\r\n.", 250, 'DATA');
        try {
            $cmd('QUIT', 221);
        } catch (RuntimeException $e) {
            // il messaggio è già stato accettato
        }
    } finally {
        fclose($fp);
    }
}

// ---------------------------------------------------------------------------
// Testi delle email
// ---------------------------------------------------------------------------
function fmt_data($v)
{
    $d = DateTime::createFromFormat('Y-m-d\TH:i', (string)$v);
    return $d ? $d->format('d/m/Y, \o\r\e H:i') : (string)$v;
}

function find_row($table, $id)
{
    foreach (read_rows($table) as $r) {
        if ($r['id'] === $id) return $r;
    }
    return null;
}

/** Tutti i campi del modulo, per la mail all'admin. */
function dettagli($table, $row)
{
    $righe = [];
    foreach (tables()[$table]['form'] as $f) {
        $n = $f['name'];
        if ($f['type'] === 'map') {
            $v = "https://www.openstreetmap.org/?mlat={$row['lat']}&mlon={$row['lng']}#map=16/{$row['lat']}/{$row['lng']}";
        } elseif ($f['type'] === 'foto') {
            $v = count(foto_list(isset($row['foto']) ? $row['foto'] : '')) . ' foto';
        } elseif ($f['type'] === 'iniziativa') {
            $ini = find_row('iniziative', $row[$n]);
            $v = $ini ? "{$ini['titolo']} ({$ini['citta']}, " . fmt_data($ini['data']) . ')' : $row[$n];
        } elseif ($f['type'] === 'datetime-local') {
            $v = fmt_data($row[$n]);
        } else {
            $v = isset($row[$n]) ? $row[$n] : '';
        }
        $v = $v !== '' ? $v : '—';
        $label = rtrim($f['label'], '?');
        $righe[] = (strpos($v, "\n") !== false || mb_strlen_safe($v) > 60) ? "$label\n$v\n" : "$label: $v";
    }
    return implode("\n", $righe);
}

function mb_strlen_safe($s)
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : preg_match_all('/./us', $s);
}

function notify_new($table, $row)
{
    $site = site_url();
    $admin_link = $site . '/' . ADMIN_PAGE;
    if ($table === 'iniziative') {
        send_mail(mail_admins(), "[Carovana] Nuova proposta di iniziativa: {$row['titolo']} ({$row['citta']})",
            "È arrivata una nuova proposta di iniziativa da approvare.\n\n" . dettagli($table, $row)
            . "\n\nPer approvarla: $admin_link", $row['email']);
        send_mail($row['email'], 'Abbiamo ricevuto la tua proposta di iniziativa',
            "Ciao,\n\n"
            . "grazie per aver proposto l'iniziativa «{$row['titolo']}» ({$row['citta']}, " . fmt_data($row['data']) . ') '
            . "alla Carovana per i diritti dell'abitare.\n\n"
            . 'La proposta è stata inviata correttamente: la controlleremo e, appena sarà approvata, '
            . "comparirà sulla mappa della Carovana ($site). Ti scriveremo quando sarà online.\n\n"
            . 'Se vuoi correggere o aggiungere qualcosa, rispondi pure a questa email.', mail_admins());
        return;
    }
    $ini = find_row('iniziative', $row['iniziativa_id']) ?: ['titolo' => '?', 'citta' => '?'];
    send_mail(mail_admins(), "[Carovana] Nuovo contributo: {$ini['titolo']} ({$ini['citta']})",
        "È arrivato un nuovo contributo da approvare.\n\n" . dettagli($table, $row)
        . "\n\nPer approvarlo: $admin_link", $row['email']);
    send_mail($row['email'], 'Abbiamo ricevuto il tuo contributo',
        "Ciao,\n\n"
        . "grazie per il contributo sull'iniziativa «{$ini['titolo']}» ({$ini['citta']}).\n\n"
        . 'Il contributo è stato inviato correttamente: lo leggeremo con attenzione e, appena sarà '
        . "approvato, comparirà nella scheda dell'iniziativa sulla mappa della Carovana:\n"
        . "$site/#{$row['iniziativa_id']}\n\n"
        . 'Se vuoi correggere o aggiungere qualcosa, rispondi pure a questa email.', mail_admins());
}

function notify_approved($table, $row)
{
    $site = site_url();
    if ($table === 'iniziative') {
        return send_mail($row['email'], "La tua iniziativa è sulla mappa della Carovana: {$row['titolo']}",
            "Ciao,\n\n"
            . "l'iniziativa «{$row['titolo']}» ({$row['citta']}, " . fmt_data($row['data']) . ') è stata approvata '
            . "ed è ora visibile a tutti sulla mappa della Carovana:\n"
            . "$site/#{$row['id']}\n\n"
            . "Dopo l'iniziativa raccontaci com'è andata e cosa è emerso sulla proposta di legge: "
            . 'proposte, osservazioni, suggerimenti e qualche foto. Puoi farlo da questa pagina, '
            . "usando questa stessa email:\n"
            . "$site/contribuisci.php?iniziativa={$row['id']}", mail_admins());
    }
    $ini = find_row('iniziative', $row['iniziativa_id']) ?: ['titolo' => '?', 'citta' => '?'];
    return send_mail($row['email'], "Il tuo contributo è stato pubblicato: {$ini['titolo']}",
        "Ciao,\n\n"
        . "il tuo contributo sull'iniziativa «{$ini['titolo']}» ({$ini['citta']}) è stato approvato "
        . "ed è ora visibile a tutti nella scheda dell'iniziativa sulla mappa della Carovana:\n"
        . "$site/#{$row['iniziativa_id']}\n\n"
        . "Grazie per aver contribuito alla proposta di legge dal basso sull'abitare!", mail_admins());
}
