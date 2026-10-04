"""Carovana per i diritti dell'abitare - Social Forum dell'Abitare.

Piccolo sito Flask: mappa delle iniziative, form "proponi", form "contribuisci"
e pagina admin nascosta per approvare/modificare i dati salvati in CSV.
"""
import csv
import io
import os
import random
import re
import secrets
import shutil
import smtplib
import ssl
import threading
import uuid
import warnings
from datetime import datetime
from email.message import EmailMessage
from email.utils import formataddr, make_msgid, parseaddr
from functools import wraps
from pathlib import Path

from flask import (Flask, abort, jsonify, redirect, render_template, request,
                   send_file, send_from_directory, session, url_for)
from PIL import Image, ImageOps, UnidentifiedImageError

BASE_DIR = Path(__file__).resolve().parent
DATA_DIR = Path(os.environ.get("CAROVANA_DATA_DIR", BASE_DIR / "data"))
DATA_DIR.mkdir(parents=True, exist_ok=True)

# Percorso "nascosto" della pagina admin e password: impostali con variabili d'ambiente.
ADMIN_PATH = os.environ.get("ADMIN_PATH", "gestione")
ADMIN_PASSWORD = os.environ.get("ADMIN_PASSWORD", "cambiami")

# Slogan della Carovana, mostrato nel menu, nel titolo della home e nei metadati delle pagine
SLOGAN = os.environ.get("SLOGAN") or "Per vivere non per speculare"


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
    SESSION_COOKIE_SECURE=os.environ.get("COOKIE_SECURE") == "1",
    MAX_CONTENT_LENGTH=64 * 1024 * 1024,  # le foto dei contributi
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

FORM_CONTRIBUTO = [
    {"name": "iniziativa_id", "label": "A quale iniziativa si riferisce?", "type": "iniziativa",
     "required": True, "public": False,
     "help": "Scegli l'iniziativa della Carovana che hai organizzato"},
    {"name": "email", "label": "Email", "type": "email", "required": True, "public": False,
     "help": "La stessa email che hai usato per proporre l'iniziativa"},
    {"name": "info", "label": "Informazioni aggiuntive sull'iniziativa", "type": "textarea",
     "required": False, "public": True,
     "help": "Com'è andata? Chi ha partecipato, quante persone, cosa è successo…"},
    {"name": "proposte", "label": "Proposte, osservazioni, suggerimenti", "type": "textarea",
     "required": False, "public": True,
     "help": "Cosa è emerso sulla proposta di legge? Cosa manca, cosa cambieresti?"},
    {"name": "foto", "label": "Foto", "type": "foto", "required": False, "public": True,
     "help": "Fino a 6 foto dell'iniziativa (JPG, PNG o WebP, massimo 10 MB ciascuna)"},
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
# Foto dei contributi: data/foto/<id contributo>/<n>.jpg (+ <n>_t.jpg miniatura)
# ---------------------------------------------------------------------------
FOTO_DIR = DATA_DIR / "foto"
FOTO_MAX, FOTO_MAX_BYTES = 6, 10 * 1024 * 1024
Image.MAX_IMAGE_PIXELS = 60_000_000
warnings.simplefilter("error", Image.DecompressionBombWarning)
RE_ID = re.compile(r"^[0-9a-f]{8}$")
RE_FOTO = re.compile(r"^\d{1,2}(_t)?\.jpg$")


def foto_list(value):
    return [n for n in (value or "").split(";") if RE_FOTO.match(n) and not n.endswith("_t.jpg")]


def save_photos(files, cid):
    """Ricodifica le foto caricate in JPEG (toglie i metadati EXIF, es. la posizione GPS).
    Restituisce (nomi, errore)."""
    files = [f for f in files if f and f.filename]
    if not files:
        return [], None
    if len(files) > FOTO_MAX:
        return [], f"Puoi caricare al massimo {FOTO_MAX} foto"
    folder = FOTO_DIR / cid
    folder.mkdir(parents=True, exist_ok=True)
    names = []
    try:
        for n, f in enumerate(files, 1):
            data = f.read(FOTO_MAX_BYTES + 1)
            if len(data) > FOTO_MAX_BYTES:
                raise ValueError(f"«{f.filename}» supera i 10 MB")
            try:
                img = Image.open(io.BytesIO(data))
                if img.format not in ("JPEG", "MPO", "PNG", "WEBP"):
                    raise ValueError
                img = ImageOps.exif_transpose(img)  # raddrizza le foto scattate col telefono
                img.load()
            except (ValueError, UnidentifiedImageError, OSError, SyntaxError,
                    Image.DecompressionBombError, Image.DecompressionBombWarning):
                raise ValueError(f"«{f.filename}» non è una foto JPG, PNG o WebP valida")
            if img.mode in ("RGBA", "LA", "P"):
                img = img.convert("RGBA")
                bg = Image.new("RGB", img.size, "white")
                bg.paste(img, mask=img.getchannel("A"))
                img = bg
            else:
                img = img.convert("RGB")
            for suffix, size, quality in (("", 2000, 85), ("_t", 480, 80)):
                copy = img.copy()
                copy.thumbnail((size, size))
                copy.save(folder / f"{n}{suffix}.jpg", "JPEG", quality=quality, optimize=True)
            names.append(f"{n}.jpg")
    except ValueError as e:
        shutil.rmtree(folder, ignore_errors=True)
        return [], str(e)
    return names, None


def delete_photos(cid, keep=()):
    folder = FOTO_DIR / cid
    if not RE_ID.match(cid) or not folder.is_dir():
        return
    keep = set(keep) | {n.replace(".jpg", "_t.jpg") for n in keep}
    for p in folder.iterdir():
        if p.name not in keep:
            p.unlink()
    if not any(folder.iterdir()):
        folder.rmdir()


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
        if f["type"] == "foto":
            continue  # gestite a parte da save_photos
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
        elif f["type"] == "iniziativa" and v \
                and v not in {r["id"] for r in approved_iniziative()}:
            errors[f["name"]] = "Iniziativa non trovata"
        row[f["name"]] = v[:5000]
    return row, errors


def check_email_iniziativa(row, errors):
    """Antispam del form contributi: l'email deve essere quella usata per proporre l'iniziativa."""
    if errors.get("iniziativa_id") or errors.get("email"):
        return
    ini = next((r for r in read_rows("iniziative") if r["id"] == row["iniziativa_id"]), None)
    if not ini or ini["email"].strip().lower() != row["email"].strip().lower():
        errors["email"] = "L'email non corrisponde a quella usata per proporre questa iniziativa"


def check_contributo(row, errors):
    check_email_iniziativa(row, errors)
    # nessun campo del contenuto è obbligatorio, ma un contributo vuoto non ha senso
    if not row.get("info") and not row.get("proposte") \
            and not any(f.filename for f in request.files.getlist("foto")):
        errors["proposte"] = "Scrivi qualcosa o aggiungi almeno una foto"


def handle_form(table, template, captcha=True, check=None, **ctx):
    form = TABLES[table]["form"]
    values, errors, sent = {}, {}, False
    if request.method == "POST":
        values = request.form.to_dict()
        if values.get("sito_web"):  # honeypot anti-spam: i bot lo compilano
            sent = True
        else:
            row, errors = validate(form, values)
            if captcha:
                try:
                    if int(values.get("captcha", "")) != session.get("captcha"):
                        raise ValueError
                except ValueError:
                    errors["captcha"] = "Risultato sbagliato, riprova"
            if check:
                check(row, errors)
            row["id"] = uuid.uuid4().hex[:8]
            if not errors and any(f["type"] == "foto" for f in form):
                names, err = save_photos(request.files.getlist("foto"), row["id"])
                if err:
                    errors["foto"] = err
                row["foto"] = ";".join(names)
            if not errors:
                row.update(inviato_il=datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                           approvata="False")
                append_row(table, row)
                notify_new(table, row)
                sent = True
    has_files = any(f.filename for f in request.files.getlist("foto")) if request.method == "POST" else False
    return render_template(template, fields=form, values=values, errors=errors, sent=sent,
                           captcha=new_captcha() if captcha else None,
                           files_lost=bool(errors) and has_files, **ctx)


# ---------------------------------------------------------------------------
# Email (SMTP): conferme a chi invia, avvisi all'admin, avviso di approvazione.
# Se SMTP_HOST non è impostato il sito funziona uguale, senza inviare email.
# ---------------------------------------------------------------------------
MAIL = {
    "host": os.environ.get("SMTP_HOST", ""),
    "port": int(os.environ.get("SMTP_PORT") or 587),
    "user": os.environ.get("SMTP_USER", ""),
    "password": os.environ.get("SMTP_PASSWORD", ""),
    # starttls (porta 587), ssl (porta 465) oppure none
    "security": (os.environ.get("SMTP_SECURITY") or "").lower()
                or ("ssl" if os.environ.get("SMTP_PORT") == "465" else "starttls"),
    "from": os.environ.get("MAIL_FROM", ""),
    "admin": [a.strip() for a in os.environ.get("ADMIN_EMAIL", "").split(",") if a.strip()],
}
MAIL_FIRMA = "Social Forum dell'Abitare – Carovana per i diritti dell'abitare"


def mail_enabled():
    return bool(MAIL["host"] and MAIL["from"])


def site_url():
    """Indirizzo pubblico del sito per i link nelle email (dietro un proxy va impostato SITE_URL)."""
    return (os.environ.get("SITE_URL") or request.host_url).rstrip("/")


def _deliver(msg):
    try:
        ctx = ssl.create_default_context()
        if MAIL["security"] == "ssl":
            smtp = smtplib.SMTP_SSL(MAIL["host"], MAIL["port"], timeout=20, context=ctx)
        else:
            smtp = smtplib.SMTP(MAIL["host"], MAIL["port"], timeout=20)
        with smtp:
            if MAIL["security"] == "starttls":
                smtp.starttls(context=ctx)
            if MAIL["user"]:
                smtp.login(MAIL["user"], MAIL["password"])
            smtp.send_message(msg)
        app.logger.info("Email inviata a %s: %s", msg["To"], msg["Subject"])
    except Exception:  # un problema con la posta non deve mai bloccare il sito
        app.logger.exception("Invio email non riuscito a %s: %s", msg["To"], msg["Subject"])


def send_mail(to, subject, body, reply_to=None):
    """Invia in background, così un server SMTP lento non rallenta la risposta."""
    to = [a for a in ([to] if isinstance(to, str) else to) if a and "@" in a]
    if not mail_enabled() or not to:
        return False
    msg = EmailMessage()
    msg["From"] = MAIL["from"]
    msg["To"] = ", ".join(to)
    msg["Subject"] = " ".join(subject.split())  # niente a capo negli header
    msg["Message-ID"] = make_msgid(domain=parseaddr(MAIL["from"])[1].split("@")[-1] or None)
    if reply_to:
        msg["Reply-To"] = ", ".join([reply_to] if isinstance(reply_to, str) else reply_to)
    msg.set_content(body + "\n\n-- \n" + MAIL_FIRMA + "\n")
    threading.Thread(target=_deliver, args=(msg,), daemon=True).start()
    return True


def fmt_data(v):
    try:
        d = datetime.strptime(v, "%Y-%m-%dT%H:%M")
        return d.strftime("%d/%m/%Y, ore %H:%M")
    except ValueError:
        return v


def dettagli(table, row):
    """Tutti i campi del modulo, per la mail all'admin."""
    righe = []
    for f in TABLES[table]["form"]:
        if f["type"] == "map":
            v = f"https://www.openstreetmap.org/?mlat={row['lat']}&mlon={row['lng']}#map=16/{row['lat']}/{row['lng']}"
        elif f["type"] == "foto":
            v = f"{len(foto_list(row.get('foto')))} foto"
        elif f["type"] == "iniziativa":
            ini = find_row("iniziative", row[f["name"]])
            v = f"{ini['titolo']} ({ini['citta']}, {fmt_data(ini['data'])})" if ini else row[f["name"]]
        elif f["type"] == "datetime-local":
            v = fmt_data(row[f["name"]])
        else:
            v = row.get(f["name"], "")
        v = v or "—"
        label = f["label"].rstrip("?")
        righe.append(f"{label}\n{v}\n" if "\n" in v or len(v) > 60 else f"{label}: {v}")
    return "\n".join(righe)


def find_row(table, rid):
    return next((r for r in read_rows(table) if r["id"] == rid), None)


def notify_new(table, row):
    site = site_url()
    admin_link = f"{site}/{ADMIN_PATH}"
    if table == "iniziative":
        send_mail(MAIL["admin"], f"[Carovana] Nuova proposta di iniziativa: {row['titolo']} ({row['citta']})",
                  "È arrivata una nuova proposta di iniziativa da approvare.\n\n"
                  f"{dettagli(table, row)}\n\nPer approvarla: {admin_link}", reply_to=row["email"])
        send_mail(row["email"], "Abbiamo ricevuto la tua proposta di iniziativa per la Carovana",
                  "Ciao,\n\n"
                  f"grazie per aver proposto l'iniziativa «{row['titolo']}», in programma a {row['citta']} il {fmt_data(row['data'])}, "
                  "alla Carovana per i diritti dell'abitare.\n\n"
                  "Abbiamo ricevuto correttamente la tua proposta. La esamineremo e, una volta approvata, "
                  f"sarà pubblicata sulla mappa della Carovana: {site}\n\n"
                  "Ti scriveremo non appena l'iniziativa sarà online. "
                  "Ti invieremo anche il link a cui accedere per condividere le immagini dell'iniziativa e il contributo per la proposta di legge dal basso.\n\n"
                  "Nel frattempo, se vuoi correggere o aggiungere qualche informazione alla proposta, puoi semplicemente rispondere a questa email.",
                  reply_to=MAIL["admin"] or None)
    else:
        ini = find_row("iniziative", row["iniziativa_id"]) or {"titolo": "?", "citta": "?", "data": ""}
        send_mail(MAIL["admin"], f"[Carovana] Nuovo contributo: {ini['titolo']} ({ini['citta']})",
                  "È arrivato un nuovo contributo da approvare.\n\n"
                  f"{dettagli(table, row)}\n\nPer approvarlo: {admin_link}", reply_to=row["email"])
        send_mail(row["email"], "Abbiamo ricevuto il tuo contributo per la Carovana",
                  "Ciao,\n\n"
                  f"grazie per il contributo sull'iniziativa «{ini['titolo']}», svoltasi a {ini['citta']} il {fmt_data(ini['data'])}.\n\n"
                  "Abbiamo ricevuto correttamente il tuo contributo: appena sarà "
                  "approvato, comparirà nella scheda dell'iniziativa sulla mappa della Carovana:\n"
                  f"{site}/#{row['iniziativa_id']}\n\n"
                  "Ti scriveremo non appena il contributo sarà online.\n"
                  "Nel frattempo, se vuoi correggere o aggiungere qualcosa, puoi semplicemente rispondere a questa email.",
                  reply_to=MAIL["admin"] or None)


def notify_approved(table, row):
    site = site_url()
    if table == "iniziative":
        return send_mail(row["email"], f"La tua iniziativa è sulla mappa della Carovana: {row['titolo']}",
                         "Ciao,\n\n"
                         f"l'iniziativa «{row['titolo']}», in programma a {row['citta']} il {fmt_data(row['data'])}, è stata approvata "
                         "ed è ora visibile a tutti sulla mappa della Carovana:\n"
                         f"{site}/#{row['id']}\n\n"
                         "Dopo l'iniziativa raccontaci com'è andata e cosa è emerso sulla proposta di legge: "
                         "proposte, osservazioni, suggerimenti e qualche foto. Puoi farlo da questa pagina, "
                         "usando questa stessa email:\n"
                         f"{site}/contribuisci?iniziativa={row['id']}",
                         reply_to=MAIL["admin"] or None)
    ini = find_row("iniziative", row["iniziativa_id"]) or {"titolo": "?", "citta": "?", "data": ""}
    return send_mail(row["email"], f"Il tuo contributo è stato pubblicato: {ini['titolo']}",
                     "Ciao,\n\n"
                     f"il tuo contributo sull'iniziativa «{ini['titolo']}», svoltasi a {ini['citta']} il {fmt_data(ini['data'])}, è stato approvato "
                     "ed è ora visibile a tutti nella scheda dell'iniziativa sulla mappa della Carovana:\n"
                     f"{site}/#{row['iniziativa_id']}\n\n"
                     "Grazie per aver contribuito alla proposta di legge dal basso sull'abitare!",
                     reply_to=MAIL["admin"] or None)


@app.context_processor
def inject_globals():
    return {"mail_enabled": mail_enabled(), "slogan": SLOGAN}


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
    return handle_form("contributi", "contribuisci.html", captcha=False, check=check_contributo,
                       iniziative=approved_iniziative(),
                       preselect=request.args.get("iniziativa", ""))


def foto_url(cid, name, thumb=False):
    return url_for("foto", cid=cid, name=name.replace(".jpg", "_t.jpg") if thumb else name)


@app.route("/foto/<cid>/<name>")
def foto(cid, name):
    if not RE_ID.match(cid) or not RE_FOTO.match(name):
        abort(404)
    if not session.get("admin"):  # il pubblico vede solo le foto dei contributi approvati
        c = next((r for r in read_rows("contributi") if r["id"] == cid), None)
        if not c or not is_true(c["approvata"]):
            abort(404)
    resp = send_from_directory(FOTO_DIR / cid, name, max_age=3600)
    resp.headers["X-Content-Type-Options"] = "nosniff"
    return resp


@app.route("/api/iniziative")
def api_iniziative():
    ini_cols = TABLES["iniziative"]["public"]
    con_cols = TABLES["contributi"]["public"]
    contributi = {}
    for c in read_rows("contributi"):
        if is_true(c["approvata"]):
            item = {k: c[k] for k in con_cols}
            item["foto"] = [{"url": foto_url(c["id"], n), "thumb": foto_url(c["id"], n, True)}
                            for n in foto_list(c["foto"])]
            contributi.setdefault(c["iniziativa_id"], []).append(item)
    out = []
    for r in approved_iniziative():
        item = {k: r[k] for k in ini_cols}
        item.update(id=r["id"], lat=float(r["lat"]), lng=float(r["lng"]),
                    contributi=contributi.get(r["id"], []))
        out.append(item)
    return jsonify(iniziative=out)


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
    return render_template("admin.html", schema=schema, csrf=session["csrf"],
                           foto_base=url_for("foto", cid="__ID__", name="__N__"))


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
        r["id"] = r["id"] if RE_ID.match(r["id"]) else uuid.uuid4().hex[:8]
        r["approvata"] = "True" if is_true(r["approvata"]) else "False"
        if "foto" in r:
            r["foto"] = ";".join(foto_list(r["foto"]))
        clean.append(r)
    with _lock:
        old_rows = {r["id"]: r for r in read_rows(table)}
        if table == "contributi":  # elimina dal disco le foto tolte o dei contributi eliminati
            keep = {r["id"]: foto_list(r["foto"]) for r in clean}
            for old in old_rows.values():
                delete_photos(old["id"], keep.get(old["id"], ()))
        write_rows(table, clean)
    # avvisa chi ha inviato le righe appena approvate (da non approvata ad approvata)
    notificati = sum(1 for r in clean
                     if is_true(r["approvata"]) and r["id"] in old_rows
                     and not is_true(old_rows[r["id"]]["approvata"]) and notify_approved(table, r))
    return jsonify(ok=True, rows=clean, notificati=notificati)


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
