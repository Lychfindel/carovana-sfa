FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    TZ=Europe/Rome \
    CAROVANA_DATA_DIR=/data

# utente non-root con UID/GID configurabili, così i file in ./data restano del proprietario sul server
ARG UID=1000
ARG GID=1000
RUN apt-get update && apt-get install -y --no-install-recommends tzdata \
    && rm -rf /var/lib/apt/lists/* \
    && groupadd -g "$GID" app && useradd -u "$UID" -g app -m -s /usr/sbin/nologin app \
    && mkdir -p /data && chown app:app /data

WORKDIR /app
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt
COPY app.py .
COPY templates templates
COPY static static

USER app
EXPOSE 8000
VOLUME /data

# un solo worker: la scrittura dei CSV è protetta da un lock interno al processo
CMD ["gunicorn", "-w", "1", "--threads", "4", "-b", "0.0.0.0:8000", "--access-logfile", "-", "app:app"]
