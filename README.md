# Carovana per i diritti dell'abitare – SFA

Sito della Carovana del Social Forum dell'Abitare. PHP semplice, senza database e senza
dipendenze: funziona su un normale hosting condiviso con PHP ≥ 7.4 (consigliato 8.x).

| Pagina | File |
|---|---|
| Mappa delle iniziative (home) | `index.php` |
| Principi e linee guida (con download PDF) | `principi.php` |
| Volantino in 10 punti (con download PDF) | `volantino.php` |
| Proponi un'iniziativa | `proponi.php` |
| Contribuisci alla proposta | `contribuisci.php` (anche `contribuisci.php?iniziativa=<id>`) |
| Gestione (nascosta, con password) | `gestione.php` |
| Dati pubblici per la mappa (JSON) | `api.php` |

I dati sono salvati in `data/iniziative.csv` e `data/contributi.csv`. Ogni risposta ha la
colonna `approvata` (default `False`): sulla mappa compaiono solo le righe approvate.
I contributi sono collegati alle iniziative tramite la colonna `iniziativa_id`
(`generale` = contributo non legato a una tappa). Le email non vengono mai pubblicate.

I PDF scaricabili sono in `static/pdf/`: per aggiornarli basta sostituire i file tenendo lo stesso nome.

## Messa online

1. Modifica `config.php`: **cambia la password** (`ADMIN_PASSWORD`, oppure meglio
   `ADMIN_PASSWORD_HASH`, le istruzioni sono nel file).
2. Carica tutti i file via FTP/SFTP nella cartella del sito (compresi i file `.htaccess`).
3. Rendi scrivibile dal web server la cartella `data/` (di solito permessi `775` o `777`
   dal pannello dell'hosting o dal client FTP).
4. Consigliato: rinomina `gestione.php` con un nome meno prevedibile (es. `gestione-x7k2.php`).
5. Usa HTTPS (quasi tutti gli hosting offrono Let's Encrypt gratuito).

### Protezione dei dati

Su **Apache** (la maggior parte degli hosting condivisi) i file `.htaccess` bloccano già
l'accesso diretto a `data/`, `inc/` e `config.php`. Verifica dopo il caricamento che
`https://tuosito/data/iniziative.csv` risponda "Forbidden".

Su **nginx** i `.htaccess` sono ignorati: aggiungi alla configurazione

```nginx
location ~ ^/(data|inc)/ { deny all; }
location ~ (^/config\.php$|/\.|\.(md|csv)$) { deny all; }
```

In alternativa puoi spostare la cartella dei dati fuori dalla root del sito e indicarla in
`DATA_DIR` dentro `config.php`.

Fai un backup periodico della cartella `data/` (anche solo scaricando i CSV da `gestione.php`).

## Provarlo in locale

```bash
php -S 127.0.0.1:8000
# oppure, senza PHP installato:
docker run --rm -p 8000:8000 -v "$PWD":/app -w /app php:8.3-cli php -S 0.0.0.0:8000
```

e apri http://127.0.0.1:8000

## Modificare le domande dei form

Le domande sono definite in `inc/forms.php` nelle liste `FORM_INIZIATIVA` e
`FORM_CONTRIBUTO` (tipi: `text`, `email`, `url`, `textarea`, `datetime-local`, `select`,
`radio`, `map`, `iniziativa`). Le colonne dei CSV si aggiornano da sole; `'public' => true`
indica i campi mostrati sul sito.

## Struttura

```
index.php, principi.php, volantino.php, proponi.php, contribuisci.php, gestione.php, api.php
config.php          password e impostazioni
inc/bootstrap.php   funzioni condivise (CSV con lock, validazione, captcha, rendering campi)
inc/forms.php       definizione delle domande dei form
inc/header.php, inc/footer.php
static/             CSS, JavaScript (mappa, selettore posizione, editor admin), logo, PDF
data/               CSV delle risposte (non accessibile dal web)
```
