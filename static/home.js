(function () {
  const ITALY = [[36.4, 6.6], [47.1, 18.6]];
  const map = L.map('map', { zoomSnap: 0.5 }).fitBounds(ITALY);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(map);

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

  function markerStyle(i, active) {
    const past = isPast(i);
    return {
      radius: active ? 11 : 8,
      color: '#fff', weight: active ? 3 : 2,
      fillColor: past ? '#9a9a9a' : '#d7263d',
      fillOpacity: past ? 0.85 : 0.95
    };
  }

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
      b.addEventListener('mouseenter', () => markers[i.id] && markers[i.id].setStyle(markerStyle(i, true)));
      b.addEventListener('mouseleave', () => markers[i.id] && selected !== i.id && markers[i.id].setStyle(markerStyle(i, false)));
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
    if (selected && markers[selected]) {
      const prev = items.find((x) => x.id === selected);
      markers[selected].setStyle(markerStyle(prev, false));
    }
    selected = id;
    markers[id].setStyle(markerStyle(i, true)).bringToFront();
    renderDetail(i);
    $('view-list').hidden = true; $('view-detail').hidden = false;
    $('sidebar').scrollTop = 0;
    if (fly) map.flyTo([i.lat, i.lng], Math.max(map.getZoom(), 9), { duration: 0.6 });
    if (history.replaceState) history.replaceState(null, '', '#' + id);
    if (window.matchMedia('(max-width: 800px)').matches) $('sidebar').scrollIntoView({ behavior: 'smooth' });
  }

  function back() {
    if (selected && markers[selected]) markers[selected].setStyle(markerStyle(items.find((x) => x.id === selected), false));
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
      const m = L.circleMarker([i.lat, i.lng], markerStyle(i, false)).addTo(map);
      m.bindTooltip(i.citta + ' – ' + i.titolo, { direction: 'top', offset: [0, -8] });
      m.on('click', () => select(i.id, false));
      markers[i.id] = m;
    });
    // i pallini rossi sopra i grigi
    items.filter((i) => !isPast(i)).forEach((i) => markers[i.id].bringToFront());
    if (!items.some((i) => !isPast(i)) && items.length) {
      filter = 'past';
      document.querySelectorAll('.tab').forEach((x) => x.setAttribute('aria-selected', x.dataset.filter === 'past'));
    }
    renderList();
    const hash = location.hash.slice(1);
    if (hash && markers[hash]) select(hash, true);
  }).catch(() => {
    $('empty').hidden = false;
    $('empty').textContent = 'Impossibile caricare le iniziative. Riprova più tardi.';
  });
})();
