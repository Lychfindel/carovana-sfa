<?php
// Configurazione della Carovana: modifica questi valori prima di mettere il sito online.
// Con Docker si impostano invece nel file .env (le variabili d'ambiente hanno la precedenza).

// Password della pagina di gestione (gestione.php).
// Più sicuro: metti in ADMIN_PASSWORD_HASH l'output di
//   php -r "echo password_hash('la-tua-password', PASSWORD_DEFAULT);"
// e lascia vuota ADMIN_PASSWORD.
define('ADMIN_PASSWORD', getenv('ADMIN_PASSWORD') !== false ? getenv('ADMIN_PASSWORD') : 'cambiami');
define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH') !== false ? getenv('ADMIN_PASSWORD_HASH') : '');

// Fuso orario per la data di invio delle risposte.
const TIMEZONE = 'Europe/Rome';

// Cartella dei CSV e delle foto: deve essere scrivibile dal web server e non accessibile dal web
// (la cartella data/ inclusa è protetta da .htaccess su Apache).
define('DATA_DIR', getenv('CAROVANA_DATA_DIR') ?: __DIR__ . '/data');
