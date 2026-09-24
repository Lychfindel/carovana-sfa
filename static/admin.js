(function () {
  const A = window.ADMIN;
  const $ = (id) => document.getElementById(id);
  const el = (tag, cls, text) => {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  };
  const LABELS = { id: 'ID', inviato_il: 'Inviato il', approvata: 'Approvata', lat: 'Latitudine', lng: 'Longitudine', iniziativa_id: 'Iniziativa' };
  const data = { iniziative: [], contributi: [] };
  const dirty = { iniziative: false, contributi: false };
  let table = 'iniziative', open = null;

  const ok = (r) => String(r.approvata).toLowerCase() === 'true';
  const label = (t, c) => (A.schema[t].fields[c] && A.schema[t].fields[c].label) || LABELS[c] || c;
  const iniName = (id) => {
    if (id === 'generale') return 'Contributo generale';
    const i = data.iniziative.find((x) => x.id === id);
    return i ? (i.citta + ' – ' + i.titolo) : (id ? '⚠ iniziativa non trovata (' + id + ')' : '—');
  };

  function status(t, err) { $('status').textContent = t; $('status').className = 'status' + (err ? ' err' : ''); }
  function markDirty() {
    dirty[table] = true; $('save').disabled = false;
    status('Ci sono modifiche non salvate.');
  }
  function counts() {
    ['iniziative', 'contributi'].forEach((t) => {
      const n = data[t].filter((r) => !ok(r)).length;
      $('c-' + t).textContent = n ? n + ' da approvare' : '';
    });
  }

  function summary(r) {
    if (table === 'iniziative') return { title: r.titolo || '(senza titolo)', meta: [r.data.replace('T', ' '), r.citta, r.chi].filter(Boolean).join(' · ') };
    return { title: (r.contributo || '(vuoto)').slice(0, 110), meta: [iniName(r.iniziativa_id), r.chi, r.parte].filter(Boolean).join(' · ') };
  }

  function render() {
    const box = $('rows'); box.innerHTML = '';
    const st = $('f-state').value, q = $('f-q').value.toLowerCase();
    const rows = data[table].filter((r) => (st === 'all' || (st === 'ok') === ok(r)) &&
      (!q || Object.values(r).join(' ').toLowerCase().includes(q)));
    if (!rows.length) box.append(el('p', 'empty', 'Nessuna riga.'));
    rows.slice().reverse().forEach((r) => {
      const card = el('div', 'row-card' + (ok(r) ? ' is-ok' : ''));
      const head = el('div', 'row-head');
      const tog = el('label', 'switch');
      const cb = el('input'); cb.type = 'checkbox'; cb.checked = ok(r);
      cb.addEventListener('change', () => { r.approvata = cb.checked ? 'True' : 'False'; card.classList.toggle('is-ok', cb.checked); markDirty(); counts(); });
      tog.append(cb, el('span', null, 'Approvata'));
      const s = summary(r);
      const txt = el('button', 'row-txt');
      txt.append(el('strong', null, s.title), el('span', 'meta', s.meta));
      txt.addEventListener('click', () => { open = open === r ? null : r; render(); });
      head.append(tog, txt);
      card.append(head);
      if (open === r) card.append(editor(r));
      box.append(card);
    });
    counts();
  }

  function editor(r) {
    const f = el('div', 'editor');
    const cols = A.schema[table].columns.filter((c) => c !== 'approvata');
    cols.forEach((c) => {
      const def = A.schema[table].fields[c] || {};
      const w = el('label', 'ed-field' + (def.type === 'textarea' ? ' wide' : ''));
      w.append(el('span', null, label(table, c)));
      let inp;
      if (def.type === 'textarea') { inp = el('textarea'); inp.rows = 5; }
      else if (c === 'iniziativa_id') {
        inp = el('select');
        const opts = [['', '—'], ['generale', 'Contributo generale']].concat(
          data.iniziative.map((i) => [i.id, (i.data || '').slice(0, 10) + ' · ' + i.citta + ' – ' + i.titolo + (ok(i) ? '' : ' (non approvata)')]));
        if (r[c] && !opts.some((o) => o[0] === r[c])) opts.push([r[c], iniName(r[c])]);
        opts.forEach(([v, t]) => { const o = el('option', null, t); o.value = v; inp.append(o); });
      } else if (def.type === 'select' || def.type === 'radio') {
        inp = el('select');
        const opts = [''].concat(def.options);
        if (r[c] && !opts.includes(r[c])) opts.push(r[c]);
        opts.forEach((v) => { const o = el('option', null, v || '—'); o.value = v; inp.append(o); });
      } else {
        inp = el('input');
        inp.type = def.type === 'datetime-local' ? 'datetime-local' : 'text';
      }
      inp.value = r[c] || '';
      if (c === 'id' || c === 'inviato_il') inp.readOnly = true;
      inp.addEventListener('input', () => { r[c] = inp.value; markDirty(); if (c === 'lat' || c === 'lng') moveMarker(); });
      inp.addEventListener('change', () => { r[c] = inp.value; markDirty(); });
      inp.dataset.col = c;
      w.append(inp); f.append(w);
    });

    let mini = null, mk = null;
    function moveMarker() {
      const lat = parseFloat(r.lat), lng = parseFloat(r.lng);
      if (mk && !isNaN(lat) && !isNaN(lng)) mk.setLatLng([lat, lng]);
    }
    if (table === 'iniziative') {
      const m = el('div', 'ed-map');
      f.append(m);
      setTimeout(() => {
        const lat = parseFloat(r.lat) || 42.5, lng = parseFloat(r.lng) || 12.5;
        mini = L.map(m).setView([lat, lng], r.lat ? 13 : 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(mini);
        mk = L.marker([lat, lng], { draggable: true }).addTo(mini);
        const upd = (p) => {
          r.lat = p.lat.toFixed(6); r.lng = p.lng.toFixed(6);
          f.querySelector('[data-col=lat]').value = r.lat; f.querySelector('[data-col=lng]').value = r.lng;
          mk.setLatLng(p); markDirty();
        };
        mk.on('dragend', () => upd(mk.getLatLng()));
        mini.on('click', (e) => upd(e.latlng));
      }, 0);
      f.append(el('p', 'help wide', 'Trascina il segnaposto o clicca sulla mappa per correggere la posizione.'));
    }

    const actions = el('div', 'ed-actions wide');
    const del = el('button', 'btn btn-small btn-danger', 'Elimina riga');
    del.addEventListener('click', () => {
      let warn = 'Eliminare definitivamente questa riga?';
      if (table === 'iniziative') {
        const n = data.contributi.filter((c) => c.iniziativa_id === r.id).length;
        if (n) warn += '\nAttenzione: ' + n + ' contributi sono collegati a questa iniziativa.';
      }
      if (!confirm(warn)) return;
      data[table] = data[table].filter((x) => x !== r); open = null; markDirty(); render();
    });
    const close = el('button', 'btn btn-small btn-ghost', 'Chiudi');
    close.addEventListener('click', () => { open = null; render(); });
    actions.append(del, close);
    f.append(actions);
    return f;
  }

  async function load(t) {
    const r = await fetch(A.api.replace('__T__', t), { credentials: 'same-origin' });
    if (r.status === 403) { location.reload(); return; }
    data[t] = (await r.json()).rows;
  }

  async function save() {
    $('save').disabled = true; status('Salvataggio…');
    try {
      for (const t of ['iniziative', 'contributi']) {
        if (!dirty[t]) continue;
        const r = await fetch(A.api.replace('__T__', t), {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-CSRF': A.csrf },
          body: JSON.stringify({ rows: data[t] })
        });
        if (!r.ok) throw new Error(r.status);
        data[t] = (await r.json()).rows; dirty[t] = false;
      }
      open = null; render();
      status('Salvato ✓');
    } catch (e) {
      $('save').disabled = false;
      status('Errore nel salvataggio (' + e.message + '). Se la sessione è scaduta ricarica la pagina.', true);
    }
  }

  document.querySelectorAll('.admin-tabs .tab').forEach((b) => b.addEventListener('click', () => {
    table = b.dataset.table; open = null;
    document.querySelectorAll('.admin-tabs .tab').forEach((x) => x.setAttribute('aria-selected', x === b));
    $('dl').href = A.csv.replace('__T__', table);
    render();
  }));
  $('f-state').addEventListener('change', render);
  $('f-q').addEventListener('input', render);
  $('save').addEventListener('click', save);
  $('dl').addEventListener('click', (e) => { if (dirty[table] && !confirm('Il CSV scaricato non include le modifiche non salvate. Continuare?')) e.preventDefault(); });
  $('add').addEventListener('click', () => {
    const r = {}; A.schema[table].columns.forEach((c) => (r[c] = ''));
    r.approvata = 'False';
    data[table].push(r); open = r; $('f-state').value = 'all'; $('f-q').value = ''; markDirty(); render();
  });
  window.addEventListener('beforeunload', (e) => { if (dirty.iniziative || dirty.contributi) { e.preventDefault(); e.returnValue = ''; } });

  $('dl').href = A.csv.replace('__T__', table);
  Promise.all([load('iniziative'), load('contributi')]).then(render);
})();
