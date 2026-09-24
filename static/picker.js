(function () {
  const latEl = document.getElementById('lat'), lngEl = document.getElementById('lng');
  const msg = document.getElementById('geo-msg');
  const map = L.map('picker-map').setView([42.5, 12.5], 5);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '&copy; OpenStreetMap'
  }).addTo(map);
  let marker = null;

  function place(lat, lng, zoom) {
    latEl.value = lat.toFixed(6); lngEl.value = lng.toFixed(6);
    if (!marker) {
      marker = L.marker([lat, lng], { draggable: true }).addTo(map);
      marker.on('dragend', () => { const p = marker.getLatLng(); place(p.lat, p.lng); });
    } else marker.setLatLng([lat, lng]);
    if (zoom) map.setView([lat, lng], zoom);
  }
  if (latEl.value && lngEl.value) place(+latEl.value, +lngEl.value, 14);
  map.on('click', (e) => { place(e.latlng.lat, e.latlng.lng); msg.textContent = ''; });

  const q = document.getElementById('geo-q');
  const city = document.getElementById('f-citta');
  async function search() {
    let text = q.value.trim() || (city && city.value.trim());
    if (!text) { msg.textContent = 'Scrivi un indirizzo o una città.'; return; }
    msg.textContent = 'Cerco…';
    try {
      const r = await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=it&accept-language=it&q=' + encodeURIComponent(text));
      const res = await r.json();
      if (!res.length) { msg.textContent = 'Indirizzo non trovato: prova a scriverlo diversamente o clicca sulla mappa.'; return; }
      place(+res[0].lat, +res[0].lon, 16);
      msg.textContent = 'Trovato: ' + res[0].display_name + '. Se non è il punto giusto, trascina il segnaposto.';
    } catch (e) {
      msg.textContent = 'Ricerca non disponibile, clicca direttamente sulla mappa.';
    }
  }
  document.getElementById('geo-btn').addEventListener('click', search);
  q.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); search(); } });
})();
