# Carovana per i diritti dell'abitare – SFA

Sito della Carovana del Social Forum dell'Abitare.

| Pagina | Indirizzo |
|---|---|
| Mappa delle iniziative (home) | `/` |
| Principi e linee guida (con download PDF) | `/principi` |
| Volantino in 10 punti (con download PDF) | `/volantino` |
| Proponi un'iniziativa | `/proponi` |
| Contribuisci alla proposta (nascosta: nessun link sul sito, si invia per email agli organizzatori) | `/contribuisci` (anche `/contribuisci?iniziativa=<id>`) |
| Gestione (nascosta, con password) | `/gestione` (configurabile) |

I dati sono salvati in `data/iniziative.csv` e `data/contributi.csv`. Ogni risposta ha la
colonna `approvata` (default `False`): sulla mappa compaiono solo le righe approvate.
I contributi sono collegati alle iniziative tramite la colonna `iniziativa_id`. Le email non
vengono mai pubblicate.

Il form "contribuisci" non ha un captcha: come antispam l'email deve coincidere con quella
usata per proporre l'iniziativa scelta. Le foto (fino a 6 per contributo, max 10 MB l'una)
vengono ricodificate in JPEG (lato massimo 2000 px, più una miniatura da 480 px) togliendo i
metadati EXIF, come la posizione GPS; sono salvate in `data/foto/<id contributo>/` e sono
visibili al pubblico solo quando il contributo è approvato. Le foto tolte dalla pagina di
gestione, o di un contributo eliminato, vengono cancellate dal disco al salvataggio.

I PDF scaricabili sono in `static/pdf/`: per aggiornarli basta sostituire i file tenendo lo stesso nome.

La mappa della home è OpenStreetMap ritagliata sull'Italia: fuori dai confini la copre
`static/italia-maschera.geojson`, e `static/italia-regioni.geojson` disegna i confini regionali
(entrambi ricavati dai confini ISTAT 2026 di [openpolis/geojson-italy](https://github.com/openpolis/geojson-italy),
licenza CC-BY). Le tappe sono unite in ordine di data dal percorso della Carovana
(due tappe consecutive nella stessa città non vengono collegate) e i pallini vicini vengono
raggruppati con Leaflet.markercluster.

## Con Docker (consigliato sul server)

```bash
cp .env.example .env        # poi modifica almeno ADMIN_PASSWORD e SECRET_KEY
docker compose up -d --build
```

- Il sito ascolta su `127.0.0.1:8000` (porta modificabile con `CAROVANA_PORT` in `.env`):
  va esposto tramite il reverse proxy del server (nginx, Caddy, Traefik…) con HTTPS.
- I CSV e le foto restano sul server nella cartella `./data` (montata in `/data` nel container):
  per il backup basta copiare questa cartella.
- Il container gira come utente non-root con UID/GID 1000. Se sul server la cartella del
  progetto appartiene a un altro utente, avvia con `UID=$(id -u) GID=$(id -g) docker compose up -d --build`.
- Aggiornare dopo un `git pull`: `docker compose up -d --build`.
- Log: `docker compose logs -f`.

Esempio di blocco nginx (sul server, fuori dal container):

```nginx
server {
    server_name carovana.example.org;
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
    client_max_body_size 64m;  # necessario per le foto dei contributi (default nginx: 1 MB)
}
```

## Avvio in locale

```bash
python3 -m venv .venv
.venv/bin/pip install -r requirements.txt
ADMIN_PASSWORD='una-password-robusta' .venv/bin/python app.py
# -> http://127.0.0.1:5000
```

## Configurazione (variabili d'ambiente)

| Variabile | Default | Significato |
|---|---|---|
| `ADMIN_PASSWORD` | `cambiami` | password della pagina di gestione — **da cambiare** |
| `ADMIN_PATH` | `gestione` | indirizzo della pagina di gestione |
| `SECRET_KEY` | generata in `data/.secret_key` | chiave per le sessioni |
| `CAROVANA_DATA_DIR` | `./data` (`/data` in Docker) | cartella dei CSV |
| `COOKIE_SECURE` | vuoto | `1` se il sito è in HTTPS |

## In produzione senza Docker

```bash
ADMIN_PASSWORD='...' .venv/bin/gunicorn -w 1 --threads 4 -b 127.0.0.1:8000 app:app
```

dietro un reverse proxy (nginx/Caddy) con HTTPS. Usa **un solo worker** (`-w 1`), perché
la scrittura dei CSV è protetta da un lock interno al processo. Fai un backup periodico della cartella `data/`.

## Modificare le domande dei form

Le domande sono definite in `app.py` nelle liste `FORM_INIZIATIVA` e `FORM_CONTRIBUTO`
(tipi: `text`, `email`, `url`, `textarea`, `datetime-local`, `select`, `radio`, `map`,
`iniziativa`). Le colonne dei CSV si aggiornano da sole; `public: True` indica i campi
mostrati sul sito.
