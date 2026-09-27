(function () {
  // Mappa OpenStreetMap ritagliata sull'Italia: fuori dai confini una maschera copre le tile.
  const ITALY = L.latLngBounds([[35.4, 6.3], [47.2, 18.8]]);
  const map = L.map('map', {
    zoomSnap: 0.25, zoomDelta: 0.5, maxZoom: 18,
    maxBounds: ITALY.pad(0.15), maxBoundsViscosity: 1
  });
  const fitItaly = () => {
    // zoom minimo intero: markercluster non gestisce bene un minZoom frazionario
    const z = Math.floor(map.getBoundsZoom(ITALY, false, [10, 10]));
    map.setMinZoom(z);
    return z;
  };
  map.fitBounds(ITALY, { padding: [10, 10] });
  fitItaly();
  // il contenitore può cambiare dimensione dopo l'avvio (layout, font, rotazione del telefono):
  // senza aggiornare Leaflet i pallini fuori dalla vista "vecchia" non verrebbero disegnati
  let atItaly = true;
  map.on('zoomend', () => { atItaly = map.getZoom() < map.getMinZoom() + 1; });
  new ResizeObserver(() => {
    map.invalidateSize({ pan: false });
    const z = fitItaly();
    if (atItaly) map.fitBounds(ITALY, { padding: [10, 10], animate: false });
    else if (map.getZoom() < z) map.setZoom(z);
  }).observe(map.getContainer());

  const pane = (name, z) => { map.createPane(name).style.zIndex = z; map.getPane(name).style.pointerEvents = 'none'; };
  pane('regions', 350);  // confini regionali tratteggiati
  pane('mask', 360);     // copre tutto ciò che è fuori dall'Italia
  pane('route', 380);    // percorso della Carovana, sotto i pallini

  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, className: 'tiles-osm-italia',
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> · Confini <a href="https://github.com/openpolis/geojson-italy">ISTAT/openpolis</a>'
  }).addTo(map);
  // maschera e confini hanno contorni semplificati: a scala di città sfumano
  // per lasciare la mappa OSM completa (coste, porti, isole minori)
  const FADE = [9, 11];
  const k = () => Math.min(1, Math.max(0, (FADE[1] - map.getZoom()) / (FADE[1] - FADE[0])));
  const regionStyle = () => ({ color: '#7c6ab5', weight: 0.8, opacity: 0.35 * k(), dashArray: '3 3', fillOpacity: 0 });
  const maskStyle = () => ({ color: '#7c6ab5', weight: 1.5, opacity: 0.8 * k(), fillColor: '#e6e0f5', fillOpacity: k() });
  const overlay = (url, name, style) => fetch(url).then((r) => r.json()).then((geo) => {
    const layer = L.geoJSON(geo, { pane: name, interactive: false, style }).addTo(map);
    map.on('zoomend', () => layer.setStyle(style));
  });
  overlay(window.REGIONI_URL, 'regions', regionStyle);
  overlay(window.MASCHERA_URL, 'mask', maskStyle);

  // Percorso della Carovana: le tappe unite in ordine di data con archi tratteggiati
  function arc(a, b) {
    const pa = map.project(a, 6), pb = map.project(b, 6);
    const mid = pa.add(pb).divideBy(2), d = pb.subtract(pa);
    const ctrl = mid.add(L.point(-d.y, d.x).multiplyBy(0.18)); // punto di controllo di lato
    const pts = [];
    for (let t = 0; t <= 1.0001; t += 1 / 24) {
      const x = (1 - t) * (1 - t) * pa.x + 2 * (1 - t) * t * ctrl.x + t * t * pb.x;
      const y = (1 - t) * (1 - t) * pa.y + 2 * (1 - t) * t * ctrl.y + t * t * pb.y;
      pts.push(map.unproject(L.point(x, y), 6));
    }
    return pts;
  }
  function drawRoute(list) {
    if (list.length < 2) return;
    // tappe in ordine di data; due tappe consecutive nella stessa città (entro ~20 km)
    // non hanno bisogno di un arco tra loro
    const fermate = [];
    list.slice().sort((x, y) => (x.data < y.data ? -1 : 1)).forEach((i) => {
      const prev = fermate[fermate.length - 1];
      if (prev && map.distance([prev.lat, prev.lng], [i.lat, i.lng]) < 20000) return;
      fermate.push({ lat: i.lat, lng: i.lng, past: isPast(i) }); // past = la carovana ci è già arrivata
    });
    for (let k = 1; k < fermate.length; k++) {
      const a = fermate[k - 1], b = fermate[k];
      const done = b.past;
      L.polyline(arc([a.lat, a.lng], [b.lat, b.lng]), {
        pane: 'route', interactive: false, smoothFactor: 1,
        color: done ? '#8a8494' : '#d7263d', weight: done ? 2 : 2.5,
        opacity: done ? 0.6 : 0.85, dashArray: done ? '2 6' : '8 7', lineCap: 'round'
      }).addTo(map);
    }
  }

  // Pallini (divIcon) raggruppati quando sono vicini
  const cluster = L.markerClusterGroup({
    maxClusterRadius: 28, showCoverageOnHover: false, spiderfyOnMaxZoom: true,
    iconCreateFunction: (c) => {
      const kids = c.getAllChildMarkers();
      const past = kids.filter((m) => m.options.past).length;
      const state = past === 0 ? '' : past === kids.length ? ' is-past' : ' is-mixed'; // mixed = passati e futuri
      return L.divIcon({
        html: '<span>' + kids.length + '</span>',
        className: 'pin-cluster' + state,
        iconSize: [18, 18] // stessa dimensione dei pallini singoli
      });
    }
  }).addTo(map);
  const pinIcon = (i) => L.divIcon({ className: 'pin' + (isPast(i) ? ' is-past' : ''), html: '<i></i>', iconSize: [18, 18] });
  function setActive(id, on) {
    const m = markers[id];
    const e = m && m.getElement();
    if (e) e.classList.toggle('is-active', on);
    if (m) m.setZIndexOffset(on ? 1000 : (m.options.past ? 0 : 500));
  }

  const $ = (id) => document.getElementById(id);
  const el = (tag, cls, text) => {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  };

  const today = new Date(); today.setHours(0, 0, 0, 0);
  const parseDate = (s) => { const d = new Date(s); return isNaN(d) ? null : d; };
  const isPast = (i) => { const d = parseDate(i.data); return d ? d < today : false; };
  const fmtDate = (s) => {
    const d = parseDate(s);
    if (!d) return s || '';
    const day = d.toLocaleDateString('it-IT', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const hasTime = s.length > 10 && !(d.getHours() === 0 && d.getMinutes() === 0);
    return hasTime ? day + ', ore ' + d.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' }) : day;
  };
  const shortDate = (s) => {
    const d = parseDate(s);
    return d ? { d: d.getDate(), m: d.toLocaleDateString('it-IT', { month: 'short' }).replace('.', '') } : { d: '?', m: '' };
  };
  const tipDate = (s) => { // DD.MM.YY
    const d = parseDate(s);
    const p2 = (n) => String(n).padStart(2, '0');
    return d ? p2(d.getDate()) + '.' + p2(d.getMonth() + 1) + '.' + p2(d.getFullYear() % 100) : '';
  };
  const safeUrl = (u) => /^https?:\/\//i.test(u || '') ? u : null;

  let items = [], markers = {}, filter = 'next', selected = null;

  function renderList() {
    const list = $('list'); list.innerHTML = '';
    const shown = items.filter((i) => (filter === 'past') === isPast(i));
    if (filter === 'past') shown.reverse();
    $('n-next').textContent = items.filter((i) => !isPast(i)).length;
    $('n-past').textContent = items.filter(isPast).length;
    shown.forEach((i) => {
      const li = el('li');
      const b = el('button', 'item' + (isPast(i) ? ' is-past' : ''));
      const sd = shortDate(i.data);
      const cal = el('span', 'cal');
      cal.append(el('b', null, sd.d), el('small', null, sd.m));
      const txt = el('span', 'item-txt');
      txt.append(el('strong', null, i.titolo), el('span', 'meta', i.citta + ' · ' + i.chi));
      b.append(cal, txt);
      b.addEventListener('click', () => select(i.id, true));
      b.addEventListener('mouseenter', () => setActive(i.id, true));
      b.addEventListener('mouseleave', () => selected !== i.id && setActive(i.id, false));
      li.append(b); list.append(li);
    });
    const empty = $('empty');
    empty.hidden = shown.length > 0;
    empty.textContent = filter === 'next'
      ? 'Nessuna iniziativa in programma per ora. Organizzane una tu!'
      : 'Nessuna iniziativa ancora svolta.';
  }

  function renderDetail(i) {
    const a = $('detail'); a.innerHTML = '';
    const badge = el('span', 'badge ' + (isPast(i) ? 'badge-past' : 'badge-next'), isPast(i) ? 'Già svolta' : 'In programma');
    a.append(badge, el('h2', null, i.titolo));
    const dl = el('dl', 'facts');
    [['Quando', fmtDate(i.data)], ['Dove', i.citta], ['Chi', i.chi]].forEach(([k, v]) => {
      if (!v) return;
      dl.append(el('dt', null, k), el('dd', null, v));
    });
    a.append(dl);
    if (i.descrizione) {
      const d = el('div', 'desc');
      i.descrizione.split(/\n{2,}/).forEach((p) => d.append(el('p', null, p)));
      a.append(d);
    }
    const links = el('div', 'detail-ctas');
    const url = safeUrl(i.link);
    if (url) {
      const l = el('a', 'btn btn-small', 'Vai all\'evento ↗'); l.href = url; l.target = '_blank'; l.rel = 'noopener';
      links.append(l);
    }
    const dir = el('a', 'btn btn-small btn-ghost', 'Indicazioni');
    dir.href = 'https://www.openstreetmap.org/directions?to=' + i.lat + '%2C' + i.lng; dir.target = '_blank'; dir.rel = 'noopener';
    links.append(dir);
    a.append(links);

    if (!i.contributi.length) return;
    const sec = el('section', 'contribs');
    sec.append(el('h3', null, 'Contributi alla proposta (' + i.contributi.length + ')'));
    i.contributi.forEach((c) => {
      const card = el('div', 'contrib');
      const para = (label, text) => {
        if (!text) return;
        card.append(el('p', 'contrib-label', label));
        text.split(/\n{2,}/).forEach((p) => card.append(el('p', null, p)));
      };
      para('Com\'è andata', c.info);
      para('Proposte, osservazioni, suggerimenti', c.proposte);
      if (c.foto && c.foto.length) {
        const g = el('div', 'contrib-foto');
        c.foto.forEach((f) => {
          const a = el('a'); a.href = f.url; a.target = '_blank'; a.rel = 'noopener';
          const img = el('img'); img.src = f.thumb; img.alt = 'Foto dell\'iniziativa'; img.loading = 'lazy';
          a.append(img); g.append(a);
        });
        card.append(g);
      }
      sec.append(card);
    });
    a.append(sec);
  }

  function select(id, fly) {
    const i = items.find((x) => x.id === id);
    if (!i) return;
    if (selected) setActive(selected, false);
    selected = id;
    renderDetail(i);
    $('view-list').hidden = true; $('view-detail').hidden = false;
    $('sidebar').scrollTop = 0;
    const highlight = () => setActive(id, true);
    if (fly === 'jump') { // link diretto all'iniziativa: niente animazione al caricamento
      map.setView([i.lat, i.lng], 13, { animate: false });
      cluster.zoomToShowLayer(markers[id], highlight);
    } else if (fly) {
      map.once('moveend', () => cluster.zoomToShowLayer(markers[id], highlight));
      map.flyTo([i.lat, i.lng], Math.max(map.getZoom(), 11), { duration: 0.8 });
    } else {
      highlight();
    }
    if (history.replaceState) history.replaceState(null, '', '#' + id);
    if (window.matchMedia('(max-width: 800px)').matches) $('sidebar').scrollIntoView({ behavior: 'smooth' });
  }

  function back() {
    if (selected) setActive(selected, false);
    selected = null;
    $('view-list').hidden = false; $('view-detail').hidden = true;
    if (history.replaceState) history.replaceState(null, '', location.pathname);
  }
  $('back').addEventListener('click', back);

  document.querySelectorAll('.tab').forEach((t) => t.addEventListener('click', () => {
    filter = t.dataset.filter;
    document.querySelectorAll('.tab').forEach((x) => x.setAttribute('aria-selected', x === t));
    renderList();
  }));

  fetch(window.API_URL || 'api/iniziative').then((r) => r.json()).then((data) => {
    items = data.iniziative;
    items.forEach((i) => {
      const m = L.marker([i.lat, i.lng], {
        icon: pinIcon(i), past: isPast(i), riseOnHover: true,
        zIndexOffset: isPast(i) ? 0 : 500, // i pallini rossi sopra i grigi
        alt: i.citta + ' – ' + i.titolo, keyboard: true
      });
      const tip = el('div', 'pin-tip');
      tip.append(el('strong', null, i.titolo), el('em', null, i.citta + ' - ' + tipDate(i.data)));
      m.bindTooltip(tip, { direction: 'top', offset: [0, -10] });
      m.on('click', () => select(i.id, false));
      markers[i.id] = m;
    });
    cluster.addLayers(Object.values(markers));
    drawRoute(items);
    if (!items.some((i) => !isPast(i)) && items.length) {
      filter = 'past';
      document.querySelectorAll('.tab').forEach((x) => x.setAttribute('aria-selected', x.dataset.filter === 'past'));
    }
    renderList();
    const hash = location.hash.slice(1);
    if (hash && markers[hash]) select(hash, 'jump');
  }).catch(() => {
    $('empty').hidden = false;
    $('empty').textContent = 'Impossibile caricare le iniziative. Riprova più tardi.';
  });
})();
