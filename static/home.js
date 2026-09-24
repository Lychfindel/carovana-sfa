(function () {
  // Vista nazionale: regioni disegnate nei colori SFA. Zoomando su una città
  // compaiono gradualmente le strade (tile) e le regioni sfumano.
  const ITALY = L.latLngBounds([[35.4, 6.3], [47.2, 18.8]]);
  const FADE = [7, 9.5]; // zoom in cui le tile passano da invisibili a piene
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

  map.createPane('regions').style.zIndex = 350; // sopra le tile, sotto i pallini
  map.getPane('regions').style.pointerEvents = 'none';
  const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, opacity: 0,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> · Confini <a href="https://github.com/openpolis/geojson-italy">ISTAT/openpolis</a>'
  }).addTo(map);
  let regions = null;
  fetch(window.REGIONI_URL).then((r) => r.json()).then((geo) => {
    regions = L.geoJSON(geo, { pane: 'regions', interactive: false, style: regionStyle }).addTo(map);
  });

  function fade() {
    const z = map.getZoom();
    return Math.min(1, Math.max(0, (z - FADE[0]) / (FADE[1] - FADE[0])));
  }
  function regionStyle() {
    const f = fade();
    return { color: '#b8a9e0', weight: 1.2, opacity: 1 - f * 0.6, fillColor: '#fdf6df', fillOpacity: 1 - f };
  }
  map.on('zoom', () => {
    const f = fade();
    tiles.setOpacity(f);
    map.getContainer().classList.toggle('show-tiles', f > 0);
    if (regions) regions.setStyle(regionStyle);
  });

  // Pallini (divIcon) raggruppati quando sono vicini
  const cluster = L.markerClusterGroup({
    maxClusterRadius: 28, showCoverageOnHover: false, spiderfyOnMaxZoom: true,
    iconCreateFunction: (c) => {
      const kids = c.getAllChildMarkers();
      const next = kids.some((m) => !m.options.past);
      return L.divIcon({
        html: '<span>' + kids.length + '</span>',
        className: 'pin-cluster' + (next ? '' : ' is-past'),
        iconSize: [30, 30]
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

    const sec = el('section', 'contribs');
    sec.append(el('h3', null, 'Contributi alla proposta' + (i.contributi.length ? ' (' + i.contributi.length + ')' : '')));
    if (!i.contributi.length) {
      sec.append(el('p', 'muted', isPast(i)
        ? 'Nessun contributo ancora pubblicato per questa iniziativa.'
        : 'I contributi emersi da questa iniziativa verranno pubblicati qui.'));
    }
    i.contributi.forEach((c) => {
      const card = el('div', 'contrib');
      const tags = el('div', 'tags');
      [c.parte, c.tipo].filter(Boolean).forEach((t) => tags.append(el('span', 'tag', t)));
      card.append(tags);
      (c.contributo || '').split(/\n{2,}/).forEach((p) => card.append(el('p', null, p)));
      if (c.chi) card.append(el('p', 'by', '— ' + c.chi));
      sec.append(card);
    });
    const add = el('a', 'add-contrib', '+ Aggiungi un contributo da questa iniziativa');
    add.href = window.CONTRIBUISCI_URL + '?iniziativa=' + encodeURIComponent(i.id);
    sec.append(add);
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

  fetch('api/iniziative').then((r) => r.json()).then((data) => {
    items = data.iniziative;
    items.forEach((i) => {
      const m = L.marker([i.lat, i.lng], {
        icon: pinIcon(i), past: isPast(i), riseOnHover: true,
        zIndexOffset: isPast(i) ? 0 : 500, // i pallini rossi sopra i grigi
        alt: i.citta + ' – ' + i.titolo, keyboard: true
      });
      m.bindTooltip(i.citta + ' – ' + i.titolo, { direction: 'top', offset: [0, -10] });
      m.on('click', () => select(i.id, false));
      markers[i.id] = m;
    });
    cluster.addLayers(Object.values(markers));
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
