// Selettore dei colori principale e secondario tra i 4 della palette del progetto grafico.
// La scelta resta salvata nel browser di chi la fa (localStorage).
(function () {
  const PALETTE = [
    ['azzurro', 'Azzurro', '#33D1D1'],
    ['verde', 'Verde', '#38C64F'],
    ['giallo', 'Giallo', '#FFE400'],
    ['rosa', 'Rosa', '#DF6CE5']
  ];
  const DEFAULT = { c1: 'rosa', c2: 'giallo' };
  const root = document.documentElement;
  const box = document.getElementById('colori');
  if (!box) return;
  const toggle = box.querySelector('.colori-toggle');
  const panel = box.querySelector('.colori-panel');

  const current = (slot) => root.dataset[slot] || DEFAULT[slot];

  function save() {
    try {
      localStorage.setItem('carovana-colori', JSON.stringify({ c1: current('c1'), c2: current('c2') }));
    } catch (e) { /* storage non disponibile: la scelta vale solo per questa pagina */ }
  }

  function render() {
    box.querySelectorAll('.colori-row').forEach((row) => {
      const slot = row.dataset.slot;
      row.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', b.dataset.name === current(slot)));
    });
  }

  box.querySelectorAll('.colori-row').forEach((row) => {
    const slot = row.dataset.slot;
    PALETTE.forEach(([name, label, hex]) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'colori-btn';
      b.dataset.name = name;
      b.style.setProperty('--sw', hex);
      b.title = label;
      b.setAttribute('aria-label', (slot === 'c1' ? 'Colore principale: ' : 'Colore secondario: ') + label);
      b.addEventListener('click', () => {
        root.dataset[slot] = name;
        save();
        render();
      });
      row.append(b);
    });
  });

  toggle.addEventListener('click', () => {
    const open = panel.hidden;
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', open);
  });
  document.addEventListener('click', (e) => {
    if (!panel.hidden && !box.contains(e.target)) { panel.hidden = true; toggle.setAttribute('aria-expanded', false); }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !panel.hidden) { panel.hidden = true; toggle.setAttribute('aria-expanded', false); toggle.focus(); }
  });
  render();
})();
