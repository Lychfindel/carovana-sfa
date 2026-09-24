"""Carovana per i diritti dell'abitare - Social Forum dell'Abitare.

Piccolo sito Flask: mappa delle iniziative, form "proponi", form "contribuisci"
e pagina admin nascosta per approvare/modificare i dati salvati in CSV.
"""
import csv
import os
import random
import secrets
import threading
import uuid
from datetime import datetime
from functools import wraps
from pathlib import Path

from flask import (Flask, abort, jsonify, redirect, render_template, request,
                   send_file, session, url_for)

BASE_DIR = Path(__file__).resolve().parent
DATA_DIR = Path(os.environ.get("CAROVANA_DATA_DIR", BASE_DIR / "data"))
DATA_DIR.mkdir(parents=True, exist_ok=True)

# Percorso "nascosto" della pagina admin e password: impostali con variabili d'ambiente.
ADMIN_PATH = os.environ.get("ADMIN_PATH", "gestione")
ADMIN_PASSWORD = os.environ.get("ADMIN_PASSWORD", "cambiami")


def _secret_key():
    if os.environ.get("SECRET_KEY"):
        return os.environ["SECRET_KEY"]
    key_file = DATA_DIR / ".secret_key"
    if not key_file.exists():
        key_file.write_text(secrets.token_hex(32))
    return key_file.read_text().strip()


app = Flask(__name__)
app.config.update(
    SECRET_KEY=_secret_key(),
    SESSION_COOKIE_HTTPONLY=True,
    SESSION_COOKIE_SAMESITE="Lax",
    MAX_CONTENT_LENGTH=1024 * 1024,
)

# ---------------------------------------------------------------------------
# Definizione dei form. Il template li rende automaticamente e le colonne dei
# CSV vengono ricavate da qui: per aggiungere/togliere una domanda basta
# modificare queste liste.
#   public=True  -> il campo compare sul sito (una volta approvato)
# ---------------------------------------------------------------------------
FORM_INIZIATIVA = [
    {"name": "chi", "label": "Chi?", "type": "text", "required": True, "public": True,
     "help": "L'associazione/osservatorio/gruppo che organizza l'iniziativa",
     "placeholder": "OCIO - Osservatorio CIvicO sulla casa e la residenzialità"},
    {"name": "email", "label": "Email", "type": "email", "required": True, "public": False,
     "help": "Un'email che possiamo usare per contattarti",
     "placeholder": "osservatorio@ocio-venezia.it"},
    {"name": "data", "label": "Data", "type": "datetime-local", "required": True, "public": True,
     "help": "Quando si terrà l'iniziativa"},
    {"name": "citta", "label": "Città", "type": "text", "required": True, "public": True,
     "help": "La città in cui vorrai organizzare l'iniziativa", "placeholder": "Venezia"},
    {"name": "dove", "label": "Dove?", "type": "map", "required": True, "public": True,
     "help": "Segna sulla mappa l'indirizzo dove verrà effettuata l'iniziativa "
             "(è sufficiente un indirizzo approssimativo, tutte le informazioni "
             "potranno poi essere modificate!)"},
    {"name": "titolo", "label": "Titolo", "type": "text", "required": True, "public": True,
     "help": "Il titolo dell'iniziativa"},
    {"name": "descrizione", "label": "Breve descrizione", "type": "textarea", "required": True,
     "public": True, "help": "Una breve descrizione dell'evento",
     "placeholder": "Descrivi l'iniziativa sia nei contenuti che nella tipologia. Non c'è "
                    "nessun vincolo sulla forma: si può organizzare un generico incontro, un "
                    "dibattito, una tavola rotonda o un qualsiasi altro tipo di evento."},
    {"name": "link", "label": "Link all'evento", "type": "url", "required": False, "public": True,
     "help": "Se vuoi qua puoi inserire un link all'evento",
     "placeholder": "https://sfa.ocio-venezia.it/"},
]

# NB: il Google Form di riferimento non è pubblico; questi campi sono una
# proposta da allineare alle domande reali.
FORM_CONTRIBUTO = [
    {"name": "iniziativa_id", "label": "A quale iniziativa si riferisce?", "type": "iniziativa",
     "required": True, "public": False,
     "help": "Scegli l'iniziativa della Carovana in cui è nato questo contributo"},
    {"name": "chi", "label": "Chi?", "type": "text", "required": True, "public": True,
     "help": "Il tuo nome o quello della realtà che invia il contributo"},
    {"name": "email", "label": "Email", "type": "email", "required": True, "public": False,
     "help": "Un'email che possiamo usare per contattarti"},
    {"name": "parte", "label": "A quale parte della proposta di legge si riferisce?",
     "type": "select", "required": True, "public": True,
     "help": "I 10 punti del volantino della proposta",
     "options": ["Proposta nel suo complesso",
                 "1. La casa come diritto garantito dallo Stato",
                 "2. Più case popolari, meglio mantenute",
                 "3. Affitti sociali a prezzi sostenibili",
                 "4. Regolazione affitti",
                 "5. Transizione ecologica equa",
                 "6. Stop alle speculazioni",
                 "7. Politiche abitative e rigenerazione urbana",
                 "8. Mappatura e recupero degli immobili inutilizzati",
                 "9. Riforma della fiscalità immobiliare",
                 "10. Rafforzamento della Pubblica Amministrazione",
                 "Altro"]},
    {"name": "tipo", "label": "Tipo di contributo", "type": "radio", "required": True, "public": True,
     "options": ["Modifica a una proposta esistente", "Nuova proposta / integrazione",
                 "Critica o osservazione", "Esperienza dal territorio"]},
    {"name": "contributo", "label": "Il contributo", "type": "textarea", "required": True,
     "public": True, "help": "Cosa è emerso dalla discussione? Cosa manca, cosa cambieresti?"},
]

META_START = ["id", "inviato_il"]
META_END = ["approvata"]


def _columns(form):
    cols = []
    for f in form:
        if f["type"] == "map":
            cols += ["lat", "lng"]
        else:
            cols.append(f["name"])
    return META_START + cols + META_END


TABLES = {
    "iniziative": {"file": DATA_DIR / "iniziative.csv", "form": FORM_INIZIATIVA},
    "contributi": {"file": DATA_DIR / "contributi.csv", "form": FORM_CONTRIBUTO},
}
for t in TABLES.values():
    t["columns"] = _columns(t["form"])
    t["public"] = [c for c in _columns([f for f in t["form"] if f["public"]])
                   if c not in META_START + META_END]

# ---------------------------------------------------------------------------
# CSV
# ---------------------------------------------------------------------------
_lock = threading.Lock()


def read_rows(table):
    t = TABLES[table]
    if not t["file"].exists():
        return []
    with open(t["file"], newline="", encoding="utf-8") as fh:
        rows = list(csv.DictReader(fh))
    # garantisce tutte le colonne anche se il CSV è stato creato con meno campi
    return [{c: (r.get(c) or "") for c in t["columns"]} for r in rows]


def write_rows(table, rows):
    t = TABLES[table]
    tmp = t["file"].with_suffix(".tmp")
    with open(tmp, "w", newline="", encoding="utf-8") as fh:
        w = csv.DictWriter(fh, fieldnames=t["columns"], extrasaction="ignore")
        w.writeheader()
        for r in rows:
            w.writerow({c: r.get(c, "") for c in t["columns"]})
    os.replace(tmp, t["file"])


def append_row(table, row):
    with _lock:
        rows = read_rows(table)
        rows.append(row)
        write_rows(table, rows)


def is_true(v):
    return str(v).strip().lower() in ("true", "1", "si", "sì", "yes", "vero")


def approved_iniziative():
    out = []
    for r in read_rows("iniziative"):
        if not is_true(r["approvata"]):
            continue
        try:
            float(r["lat"]), float(r["lng"])
        except ValueError:
            continue
        out.append(r)
    return sorted(out, key=lambda r: r["data"])


# ---------------------------------------------------------------------------
# Form handling
# ---------------------------------------------------------------------------
def new_captcha():
    a, b = random.randint(1, 9), random.randint(1, 9)
    session["captcha"] = a + b
    return f"{a} + {b} ="


def validate(form, data):
    row, errors = {}, {}
    for f in form:
        if f["type"] == "map":
            lat, lng = data.get("lat", "").strip(), data.get("lng", "").strip()
            try:
                if not (-90 <= float(lat) <= 90 and -180 <= float(lng) <= 180):
                    raise ValueError
                row["lat"], row["lng"] = f"{float(lat):.6f}", f"{float(lng):.6f}"
            except ValueError:
                errors[f["name"]] = "Segna un punto sulla mappa"
            continue
        v = data.get(f["name"], "").strip()
        if f["required"] and not v:
            errors[f["name"]] = "Campo obbligatorio"
        elif v and f["type"] == "email" and ("@" not in v or "." not in v.split("@")[-1]):
            errors[f["name"]] = "Email non valida"
        elif v and f["type"] == "url" and not v.startswith(("http://", "https://")):
            v = "https://" + v
        elif v and f["type"] in ("select", "radio") and v not in f["options"]:
            errors[f["name"]] = "Scelta non valida"
        elif v and f["type"] == "datetime-local":
            try:
                datetime.strptime(v, "%Y-%m-%dT%H:%M")
            except ValueError:
                errors[f["name"]] = "Data non valida"
        elif f["type"] == "iniziativa" and v and v != "generale" \
                and v not in {r["id"] for r in approved_iniziative()}:
            errors[f["name"]] = "Iniziativa non trovata"
        row[f["name"]] = v[:5000]
    return row, errors


def handle_form(table, template, **ctx):
    form = TABLES[table]["form"]
    values, errors, sent = {}, {}, False
    if request.method == "POST":
        values = request.form.to_dict()
        if values.get("sito_web"):  # honeypot anti-spam: i bot lo compilano
            sent = True
        else:
            row, errors = validate(form, values)
            try:
                if int(values.get("captcha", "")) != session.get("captcha"):
                    raise ValueError
            except ValueError:
                errors["captcha"] = "Risultato sbagliato, riprova"
            if not errors:
                row.update(id=uuid.uuid4().hex[:8],
                           inviato_il=datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                           approvata="False")
                append_row(table, row)
                sent = True
    return render_template(template, fields=form, values=values, errors=errors, sent=sent,
                           captcha=new_captcha(), **ctx)


# ---------------------------------------------------------------------------
# Pagine pubbliche
# ---------------------------------------------------------------------------
@app.route("/")
def home():
    return render_template("home.html")


@app.route("/principi")
def principi():
    return render_template("principi.html")


@app.route("/volantino")
def volantino():
    return render_template("volantino.html")


@app.route("/proponi", methods=["GET", "POST"])
def proponi():
    return handle_form("iniziative", "proponi.html")


@app.route("/contribuisci", methods=["GET", "POST"])
def contribuisci():
    return handle_form("contributi", "contribuisci.html", iniziative=approved_iniziative(),
                       preselect=request.args.get("iniziativa", ""))


@app.route("/api/iniziative")
def api_iniziative():
    ini_cols = TABLES["iniziative"]["public"]
    con_cols = TABLES["contributi"]["public"]
    contributi = {}
    for c in read_rows("contributi"):
        if is_true(c["approvata"]):
            contributi.setdefault(c["iniziativa_id"], []).append({k: c[k] for k in con_cols})
    out = []
    for r in approved_iniziative():
        item = {k: r[k] for k in ini_cols}
        item.update(id=r["id"], lat=float(r["lat"]), lng=float(r["lng"]),
                    contributi=contributi.get(r["id"], []))
        out.append(item)
    return jsonify(iniziative=out, contributi_generali=contributi.get("generale", []))


# ---------------------------------------------------------------------------
# Admin
# ---------------------------------------------------------------------------
def admin_required(fn):
    @wraps(fn)
    def wrapper(*a, **kw):
        if not session.get("admin"):
            abort(403)
        return fn(*a, **kw)
    return wrapper


@app.route(f"/{ADMIN_PATH}", methods=["GET", "POST"])
def admin():
    error = None
    if request.method == "POST":
        if secrets.compare_digest(request.form.get("password", ""), ADMIN_PASSWORD):
            session["admin"] = True
            session["csrf"] = secrets.token_hex(16)
            return redirect(url_for("admin"))
        error = "Password errata"
    if not session.get("admin"):
        return render_template("admin_login.html", error=error)
    schema = {name: {"columns": t["columns"],
                     "fields": {f["name"]: f for f in t["form"]}} for name, t in TABLES.items()}
    return render_template("admin.html", schema=schema, csrf=session["csrf"])


@app.route(f"/{ADMIN_PATH}/esci")
def admin_logout():
    session.clear()
    return redirect(url_for("home"))


@app.route(f"/{ADMIN_PATH}/api/<table>", methods=["GET", "POST"])
@admin_required
def admin_api(table):
    if table not in TABLES:
        abort(404)
    if request.method == "GET":
        return jsonify(rows=read_rows(table))
    if request.headers.get("X-CSRF") != session.get("csrf"):
        abort(403)
    rows = request.get_json(force=True).get("rows", [])
    clean = []
    for r in rows:
        r = {c: str(r.get(c, "")) for c in TABLES[table]["columns"]}
        r["id"] = r["id"] or uuid.uuid4().hex[:8]
        r["approvata"] = "True" if is_true(r["approvata"]) else "False"
        clean.append(r)
    with _lock:
        write_rows(table, clean)
    return jsonify(ok=True, rows=clean)


@app.route(f"/{ADMIN_PATH}/csv/<table>")
@admin_required
def admin_csv(table):
    if table not in TABLES:
        abort(404)
    t = TABLES[table]
    if not t["file"].exists():
        write_rows(table, [])
    return send_file(t["file"], as_attachment=True, download_name=f"{table}.csv")


if __name__ == "__main__":
    app.run(debug=os.environ.get("FLASK_DEBUG") == "1", port=int(os.environ.get("PORT", 5000)))
