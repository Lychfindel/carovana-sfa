// Anteprima e controlli delle foto scelte nel form "contribuisci"
(function () {
  const input = document.querySelector('input[type=file][name=foto]');
  if (!input) return;
  const box = document.getElementById(input.id + '-previews');
  const drop = input.closest('.file-drop');
  const max = +input.dataset.max, maxMb = +input.dataset.maxMb;
  const el = (tag, cls, text) => {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  };

  function render() {
    box.innerHTML = '';
    const files = [...input.files];
    const msgs = [];
    if (files.length > max) msgs.push('Puoi caricare al massimo ' + max + ' foto: togline ' + (files.length - max) + '.');
    files.forEach((f) => {
      const card = el('figure', 'file-preview');
      const img = el('img'); img.alt = ''; img.src = URL.createObjectURL(f);
      img.onload = () => URL.revokeObjectURL(img.src);
      card.append(img, el('figcaption', null, f.name));
      if (f.size > maxMb * 1024 * 1024) { card.classList.add('too-big'); msgs.push('«' + f.name + '» supera i ' + maxMb + ' MB.'); }
      box.append(card);
    });
    msgs.forEach((m) => box.append(el('p', 'error', m)));
  }
  input.addEventListener('change', render);
  ['dragenter', 'dragover'].forEach((ev) => drop.addEventListener(ev, () => drop.classList.add('is-over')));
  ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, () => drop.classList.remove('is-over')));
})();
