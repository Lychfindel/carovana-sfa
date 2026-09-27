# Carovana per i diritti dell'abitare – SFA

Sito della Carovana del Social Forum dell'Abitare, versione PHP. Senza database e senza
dipendenze da installare: funziona su un normale hosting condiviso con PHP ≥ 7.4
(consigliato 8.x) e l'estensione **GD** (per le foto; quasi sempre già attiva), oppure con Docker.

| Pagina | File |
|---|---|
| Mappa delle iniziative (home) | `index.php` |
| Principi e linee guida (con download PDF) | `principi.php` |
| Volantino in 10 punti (con download PDF) | `volantino.php` |
| Proponi un'iniziativa | `proponi.php` |
| Contribuisci alla proposta (nascosta: nessun link sul sito, si invia per email agli organizzatori) | `contribuisci.php` (anche `contribuisci.php?iniziativa=<id>`) |
| Gestione (nascosta, con password) | `gestione.php` |
| Dati pubblici per la mappa (JSON) | `api.php` |
| Foto dei contributi | `foto.php?c=<id>&n=<file>` |

I dati sono salvati in `data/iniziative.csv` e `data/contributi.csv`. Ogni risposta ha la
colonna `approvata` (default `False`): sulla mappa compaiono solo le righe approvate.
I contributi sono collegati alle iniziative tramite la colonna `iniziativa_id`. Le email non
vengono mai pubblicate.

Il form "contribuisci" non ha un captcha: come antispam l'email deve coincidere con quella
usata per proporre l'iniziativa scelta. Le foto (fino a 6 per contributo, max 10 MB l'una)
vengono ricodificate in JPEG con GD (lato massimo 2000 px, più una miniatura da 480 px),
raddrizzate secondo l'orientamento del telefono e ripulite dai metadati EXIF, come la
posizione GPS; sono salvate in `data/foto/<id contributo>/` e sono visibili al pubblico solo
quando il contributo è approvato. Le foto tolte dalla pagina di gestione, o di un contributo
eliminato, vengono cancellate dal disco al salvataggio.

I PDF scaricabili sono in `static/pdf/`: per aggiornarli basta sostituire i file tenendo lo stesso nome.

La mappa della home è OpenStreetMap ritagliata sull'Italia: fuori dai confini la copre
`static/italia-maschera.geojson`, e `static/italia-regioni.geojson` disegna i confini regionali
(entrambi ricavati dai confini ISTAT 2026 di [openpolis/geojson-italy](https://github.com/openpolis/geojson-italy),
licenza CC-BY). Le tappe sono unite in ordine di data dal percorso della Carovana
(due tappe consecutive nella stessa città non vengono collegate) e i pallini vicini vengono
raggruppati con Leaflet.markercluster.

## Con Docker (consigliato sul server)

```bash
cp .env.example .env        # poi cambia almeno ADMIN_PASSWORD
docker compose up -d --build
```

- L'immagine (`php:8.3-apache`) include GD con JPEG/WebP, EXIF e i limiti di caricamento
  per le foto; i messaggi di errore di PHP vanno nei log, non ai visitatori.
- Il sito ascolta su `127.0.0.1:8000` (porta modificabile con `CAROVANA_PORT` in `.env`):
  va esposto tramite il reverse proxy del server con HTTPS.
- CSV e foto restano sul server nella cartella `./data` (montata nel container): per il
  backup basta copiare questa cartella.
- Apache gira con un utente con UID/GID 1000. Se sul server la cartella del progetto
  appartiene a un altro utente, avvia con `UID=$(id -u) GID=$(id -g) docker compose up -d --build`.
- Aggiornare dopo un `git pull`: `docker compose up -d --build`. Log: `docker compose logs -f`.

Nel reverse proxy nginx serve alzare il limite delle richieste per le foto:

```nginx
location / {
    proxy_pass http://127.0.0.1:8000;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
client_max_body_size 64m;  # default nginx: 1 MB
```

## Su un hosting condiviso

1. Modifica `config.php`: **cambia la password** (`ADMIN_PASSWORD`, oppure meglio
   `ADMIN_PASSWORD_HASH`, le istruzioni sono nel file).
2. Carica tutti i file via FTP/SFTP nella cartella del sito, compresi `.htaccess` e `.user.ini`.
3. Rendi scrivibile dal web server la cartella `data/` (di solito permessi `775` o `777`
   dal pannello dell'hosting o dal client FTP).
4. Consigliato: rinomina `gestione.php` con un nome meno prevedibile (es. `gestione-x7k2.php`).
5. Usa HTTPS (quasi tutti gli hosting offrono Let's Encrypt gratuito).

### Limiti per le foto

PHP di default accetta file fino a 2 MB e richieste fino a 8 MB. I limiti necessari
(`upload_max_filesize = 10M`, `post_max_size = 64M`, `memory_limit = 256M`) sono già in
`.user.ini` (PHP-FPM/CGI) e in `.htaccess` (Apache con mod_php). Se il tuo hosting li ignora,
impostali dal suo pannello di controllo. Se una foto ha una risoluzione troppo alta per la
memoria disponibile, il form lo segnala invece di andare in errore.

### Protezione dei dati

Su **Apache** (la maggior parte degli hosting condivisi) i file `.htaccess` bloccano già
l'accesso diretto a `data/` (CSV e foto), `inc/`, `config.php` e ai file nascosti. Verifica
dopo il caricamento che `https://tuosito/data/iniziative.csv` risponda "Forbidden".

Su **nginx** i `.htaccess` sono ignorati: aggiungi alla configurazione

```nginx
location ~ ^/(data|inc)/ { deny all; }
location ~ (^/config\.php$|/\.|\.(md|csv)$) { deny all; }
```

In alternativa puoi spostare la cartella dei dati fuori dalla root del sito e indicarla in
`DATA_DIR` dentro `config.php`.

Fai un backup periodico della cartella `data/`, foto comprese.

## Provarlo in locale

```bash
docker compose up -d --build    # http://127.0.0.1:8000
# oppure con PHP installato (serve l'estensione GD per le foto):
php -S 127.0.0.1:8000
```

## Modificare le domande dei form

Le domande sono definite in `inc/forms.php` nelle liste `FORM_INIZIATIVA` e
`FORM_CONTRIBUTO` (tipi: `text`, `email`, `url`, `textarea`, `datetime-local`, `select`,
`radio`, `map`, `iniziativa`, `foto`). Le colonne dei CSV si aggiornano da sole;
`'public' => true` indica i campi mostrati sul sito.

## Struttura

```
index.php, principi.php, volantino.php, proponi.php, contribuisci.php, gestione.php
api.php             dati pubblici per la mappa
foto.php            foto dei contributi (solo se approvati)
config.php          password e impostazioni
inc/bootstrap.php   funzioni condivise (CSV con lock, validazione, foto, captcha, rendering campi)
inc/forms.php       definizione delle domande dei form
inc/header.php, inc/footer.php
static/             CSS, JavaScript (mappa, selettore posizione, foto, editor admin), GeoJSON, logo, PDF
data/               CSV e foto (non accessibile dal web)
Dockerfile, docker-compose.yml, .env.example
```
