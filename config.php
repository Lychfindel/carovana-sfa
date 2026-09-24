<?php
// Configurazione della Carovana: modifica questi valori prima di mettere il sito online.

// Password della pagina di gestione (gestione.php).
// Più sicuro: metti in ADMIN_PASSWORD_HASH l'output di
//   php -r "echo password_hash('la-tua-password', PASSWORD_DEFAULT);"
// e lascia vuota ADMIN_PASSWORD.
const ADMIN_PASSWORD = 'cambiami';
const ADMIN_PASSWORD_HASH = '';

// Cartella dei CSV: deve essere scrivibile dal web server e non accessibile dal web
// (la cartella data/ inclusa è protetta da .htaccess su Apache).
// Fuso orario per la data di invio delle risposte.
const TIMEZONE = 'Europe/Rome';

const DATA_DIR = __DIR__ . '/data';
