# Carovana per i diritti dell'abitare – SFA

Sito della Carovana del Social Forum dell'Abitare.

| Pagina | Indirizzo |
|---|---|
| Mappa delle iniziative (home) | `/` |
| Principi e linee guida (con download PDF) | `/principi` |
| Volantino in 10 punti (con download PDF) | `/volantino` |
| Proponi un'iniziativa | `/proponi` |
| Contribuisci alla proposta | `/contribuisci` (anche `/contribuisci?iniziativa=<id>`) |
| Gestione (nascosta, con password) | `/gestione` (configurabile) |

I dati sono salvati in `data/iniziative.csv` e `data/contributi.csv`. Ogni risposta ha la
colonna `approvata` (default `False`): sulla mappa compaiono solo le righe approvate.
I contributi sono collegati alle iniziative tramite la colonna `iniziativa_id`
(`generale` = contributo non legato a una tappa). Le email non vengono mai pubblicate.

I PDF scaricabili sono in `static/pdf/`: per aggiornarli basta sostituire i file tenendo lo stesso nome.

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
| `CAROVANA_DATA_DIR` | `./data` | cartella dei CSV |

## In produzione

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
