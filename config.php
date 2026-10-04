<?php
// Configurazione della Carovana: modifica questi valori prima di mettere il sito online.
// Con Docker si impostano invece nel file .env (le variabili d'ambiente hanno la precedenza).

// Password della pagina di gestione (gestione.php).
// Più sicuro: metti in ADMIN_PASSWORD_HASH l'output di
//   php -r "echo password_hash('la-tua-password', PASSWORD_DEFAULT);"
// e lascia vuota ADMIN_PASSWORD.
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') !== false ? getenv('ADMIN_PASSWORD') : 'cambiami');
define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH') !== false ? getenv('ADMIN_PASSWORD_HASH') : '');

// Slogan della Carovana, mostrato nel menu, nel titolo della home e nei metadati delle pagine
define('SLOGAN', getenv('SLOGAN') ?: 'Per vivere non per speculare');

// Fuso orario per la data di invio delle risposte.
const TIMEZONE = 'Europe/Rome';

// Cartella dei CSV e delle foto: deve essere scrivibile dal web server e non accessibile dal web
// (la cartella data/ inclusa è protetta da .htaccess su Apache).
define('DATA_DIR', getenv('CAROVANA_DATA_DIR') ?: __DIR__ . '/data');

// Nome del file della pagina di gestione (se rinomini gestione.php, aggiorna anche qui: serve per il link nelle email)
define('ADMIN_PAGE', getenv('ADMIN_PAGE') ?: 'gestione.php');

// --- Email: lascia vuoto SMTP_HOST per non inviare email ---
// Indirizzo pubblico del sito per i link nelle email, es. https://carovana.example.org
// (vuoto = ricavato dalla richiesta; dietro un reverse proxy va impostato)
define('SITE_URL', getenv('SITE_URL') ?: '');
define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
// 'starttls' (porta 587), 'ssl' (porta 465) oppure 'none'
define('SMTP_SECURITY', strtolower(getenv('SMTP_SECURITY') ?: (SMTP_PORT === 465 ? 'ssl' : 'starttls')));
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
// Mittente, es. 'Carovana SFA <carovana@example.org>'
define('MAIL_FROM', getenv('MAIL_FROM') ?: '');
// Chi riceve gli avvisi di nuove proposte e contributi (più indirizzi separati da virgola)
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: '');
