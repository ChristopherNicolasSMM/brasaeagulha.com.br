/* ==========================================================================
   BRASA & AGULHA EDITORIAL — painel administrativo (Catálogo)
   Esta página só é servida a quem tem sessão válida (ver admin/index.php).
   Toda escrita passa pelos endpoints em /api/admin/*.php, que conferem
   a sessão de novo no servidor e exigem o token CSRF em window.BA_CSRF.
   ========================================================================== */

let COLLECTIONS = [];
let AUTHORS = [];
let NOTIFY_COUNTS = {};
let selectedTags = [];
const state = { searchTerm: '' };

async function postJSON(url, data){
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ ...data, csrf: window.BA_CSRF })
  });
  let json = {};
  try { json = await res.json(); } catch (e) { /* resposta vazia */ }
  if(!res.ok){
    throw new Error(json.error || ('Erro ' + res.status));
  }
  return json;
}

async function loadCatalog(){
  const [catalogRes, authorsRes, notifyRes] = await Promise.all([
    fetch('/api/admin/catalogo-full.php', { credentials: 'same-origin' }),
    fetch('/api/authors.php', { credentials: 'same-origin' }),
    fetch('/api/admin/notify-counts.php', { credentials: 'same-origin' })
  ]);
  COLLECTIONS = await catalogRes.json();
  AUTHORS = await authorsRes.json();
  NOTIFY_COUNTS = notifyRes.ok ? await notifyRes.json() : {};
  populateModalSelects();
  renderCollectionsList();
}

/* ---------- Lista compacta ---------- */
function matchesSearch(vol, col, term){
  if(!term) return true;
  const haystack = normalize([
    col.title, vol.volumeLabel, vol.subtitle, vol.isbn,
    vol.author ? vol.author.name : '', (vol.tags || []).join(' ')
  ].join(' '));
  return haystack.includes(normalize(term));
}

function availabilityBadge(vol){
  if(vol.availability === 'coming_soon') return '<span class="availability-badge coming-soon">Em breve</span>';
  if(vol.availability === 'out_of_stock') return '<span class="availability-badge out-of-stock">Sem estoque</span>';
  return '';
}

function volumeRowTemplate(vol, col){
  const p = getPricing(vol);
  return `
    <button type="button" class="volume-row" data-open-volume="${esc(vol.id)}">
      <span class="volume-row-title">
        ${esc(vol.subtitle || vol.volumeLabel)}
        <small>${esc(vol.volumeLabel)}${vol.author ? ' · ' + esc(vol.author.name) : ''}${vol.isbn ? ' · ISBN ' + esc(vol.isbn) : ''}</small>
      </span>
      <span class="volume-row-price">
        ${availabilityBadge(vol)}
        ${formatBRL(p.final)}
        ${p.hasPromo ? `<span class="promo-badge">${esc(p.label || 'Promoção')}</span>` : ''}
      </span>
    </button>
  `;
}

function renderCollectionsList(){
  const term = state.searchTerm;
  const html = COLLECTIONS.map(col => {
    const volumes = (col.volumes || []).filter(v => matchesSearch(v, col, term));
    if(term && volumes.length === 0) return '';
    const rows = volumes.length
      ? volumes.map(v => volumeRowTemplate(v, col)).join('')
      : '<p class="empty-list-hint">Nenhum volume cadastrado nesta coleção ainda.</p>';
    return `
      <div style="${col.active ? '' : 'opacity:.55;'}">
        <div class="admin-collection-group-header" style="--accent:${esc(col.accentColor)}">
          <h3>${esc(col.title)}${col.active ? '' : ' <small style="color:var(--brasa);font-family:var(--font-ui);">(inativa)</small>'}</h3>
          <span class="type-pill">${esc(TYPE_LABELS[col.type] || col.type)}</span>
          <button type="button" class="btn btn-ghost" data-edit-collection="${esc(col.id)}">Editar coleção</button>
          <button type="button" class="btn btn-ghost" data-new-volume="${esc(col.id)}">+ Novo volume</button>
        </div>
        <div class="volume-list">${rows}</div>
      </div>
    `;
  }).join('');
  document.getElementById('adminCollections').innerHTML = html || '<p class="empty-list-hint">Nenhum resultado para essa busca.</p>';
}

/* ---------- Campo de tags (chips + sugestão) ---------- */
const tagInputEl = document.getElementById('tagInput');
const tagInputField = document.getElementById('tagInputField');
const tagsHidden = document.getElementById('tagsHidden');

function getAllExistingTags(){
  const set = new Set();
  getAllVolumes(COLLECTIONS).forEach(({ vol }) => (vol.tags || []).forEach(t => set.add(t)));
  return Array.from(set).sort((a, b) => a.localeCompare(b, 'pt-BR'));
}

function syncTagsHidden(){
  tagsHidden.value = selectedTags.join(', ');
}

function renderTagChips(){
  tagInputEl.querySelectorAll('.tag-input-chip').forEach(el => el.remove());
  selectedTags.forEach(tag => {
    const chip = document.createElement('span');
    chip.className = 'tag-input-chip';
    chip.innerHTML = `${esc(tag)} <button type="button" aria-label="Remover ${esc(tag)}">×</button>`;
    chip.querySelector('button').addEventListener('click', () => {
      selectedTags = selectedTags.filter(t => t !== tag);
      renderTagChips();
      syncTagsHidden();
    });
    tagInputEl.insertBefore(chip, tagInputField);
  });
  syncTagsHidden();
}

function addTag(rawTag){
  const tag = rawTag.trim();
  if(tag === '' || selectedTags.some(t => t.toLowerCase() === tag.toLowerCase())) return;
  selectedTags.push(tag);
  renderTagChips();
}

function closeTagSuggestions(){
  const box = tagInputEl.querySelector('.tag-input-suggestions');
  if(box) box.remove();
}

function showTagSuggestions(query){
  closeTagSuggestions();
  const all = getAllExistingTags().filter(t =>
    !selectedTags.some(sel => sel.toLowerCase() === t.toLowerCase()) &&
    (!query || t.toLowerCase().includes(query.toLowerCase()))
  );
  if(all.length === 0) return;
  const box = document.createElement('div');
  box.className = 'tag-input-suggestions';
  box.innerHTML = all.slice(0, 8).map(t => `<button type="button" class="tag-input-suggestion">${esc(t)}</button>`).join('');
  box.querySelectorAll('.tag-input-suggestion').forEach(btn => {
    btn.addEventListener('mousedown', (e) => {
      e.preventDefault();
      addTag(btn.textContent);
      tagInputField.value = '';
      closeTagSuggestions();
      tagInputField.focus();
    });
  });
  tagInputEl.appendChild(box);
}

tagInputField.addEventListener('input', () => showTagSuggestions(tagInputField.value));
tagInputField.addEventListener('focus', () => showTagSuggestions(tagInputField.value));
tagInputField.addEventListener('blur', () => setTimeout(closeTagSuggestions, 150));
tagInputField.addEventListener('keydown', (e) => {
  if(e.key === 'Enter' || e.key === ','){
    e.preventDefault();
    addTag(tagInputField.value.replace(/,$/, ''));
    tagInputField.value = '';
    showTagSuggestions('');
  } else if(e.key === 'Backspace' && tagInputField.value === '' && selectedTags.length){
    selectedTags.pop();
    renderTagChips();
  }
});

/* ---------- Imagens do volume ---------- */
function renderVolumeImages(images){
  const grid = document.getElementById('volumeImagesGrid');
  if(!images.length){
    grid.innerHTML = '<p class="empty-list-hint">Nenhuma imagem ainda — sem foto, o site mostra o selo ᛟ.</p>';
    return;
  }
  grid.innerHTML = images.map(img => `
    <div class="volume-image-item${img.isPrimary ? ' is-primary' : ''}" data-image-id="${img.id}">
      ${img.isPrimary ? '<span class="primary-tag">Principal</span>' : ''}
      <img src="${esc(img.url)}" alt="">
      <div class="image-actions">
        ${!img.isPrimary ? `<button type="button" data-action="set-primary-image" data-image-id="${img.id}">Tornar principal</button>` : ''}
        <button type="button" data-action="delete-image" data-image-id="${img.id}">Excluir</button>
      </div>
    </div>
  `).join('');
}

document.getElementById('imageUploadInput').addEventListener('change', async (e) => {
  const file = e.target.files[0];
  const volumeId = e.target.dataset.volumeId;
  if(!file || !volumeId) return;
  const status = document.getElementById('imageUploadStatus');
  status.textContent = 'Enviando...';
  try {
    const fd = new FormData();
    fd.append('image', file);
    fd.append('volume_id', volumeId);
    fd.append('csrf', window.BA_CSRF);
    const res = await fetch('/api/admin/upload-volume-image.php', { method: 'POST', credentials: 'same-origin', body: fd });
    const json = await res.json();
    if(!res.ok) throw new Error(json.error || ('Erro ' + res.status));
    status.textContent = 'Enviada ✓';
    setTimeout(() => status.textContent = '', 2000);
    const found = findVolumeIn(COLLECTIONS, volumeId);
    if(found){
      found.vol.images = found.vol.images || [];
      if(json.isPrimary) found.vol.images.forEach(img => img.isPrimary = false);
      found.vol.images.push({ id: json.id, url: json.url, isPrimary: json.isPrimary });
      renderVolumeImages(found.vol.images);
    }
  } catch (err){
    status.textContent = 'Erro: ' + err.message;
  }
  e.target.value = '';
});

document.getElementById('volumeImagesGrid').addEventListener('click', async (e) => {
  const setPrimaryBtn = e.target.closest('[data-action="set-primary-image"]');
  const deleteBtn = e.target.closest('[data-action="delete-image"]');
  const volumeId = volumeForm.elements['id'].value;
  const found = findVolumeIn(COLLECTIONS, volumeId);

  if(setPrimaryBtn){
    try {
      await postJSON('/api/admin/set-primary-image.php', { image_id: setPrimaryBtn.dataset.imageId });
      if(found){
        found.vol.images.forEach(img => img.isPrimary = (String(img.id) === setPrimaryBtn.dataset.imageId));
        renderVolumeImages(found.vol.images);
      }
    } catch (err){ alert('Não foi possível: ' + err.message); }
  }

  if(deleteBtn){
    if(!confirm('Excluir esta imagem?')) return;
    try {
      await postJSON('/api/admin/delete-volume-image.php', { image_id: deleteBtn.dataset.imageId });
      if(found){
        found.vol.images = found.vol.images.filter(img => String(img.id) !== deleteBtn.dataset.imageId);
        if(found.vol.images.length && !found.vol.images.some(img => img.isPrimary)){
          found.vol.images[0].isPrimary = true;
        }
        renderVolumeImages(found.vol.images);
      }
    } catch (err){ alert('Não foi possível: ' + err.message); }
  }
});

/* ---------- Modal de volume (criação e edição) ---------- */
const volumeModal = document.getElementById('volumeModal');
const volumeForm = document.getElementById('volumeForm');

function populateModalSelects(){
  const colSelect = volumeForm.elements['collectionId'];
  colSelect.innerHTML = COLLECTIONS.map(c => `<option value="${esc(c.id)}">${esc(c.title)}${c.active ? '' : ' (inativa)'}</option>`).join('');

  const authorSelect = volumeForm.elements['authorId'];
  authorSelect.innerHTML = '<option value="">— Sem autor definido —</option>' +
    AUTHORS.map(a => `<option value="${esc(a.id)}">${esc(a.name)}</option>`).join('');
}

function openVolumeModal(volumeId, defaultCollectionId){
  const found = volumeId ? findVolumeIn(COLLECTIONS, volumeId) : null;
  volumeForm.reset();
  document.getElementById('volumeModalStatus').textContent = '';
  closeTagSuggestions();

  if(found){
    const { vol, col } = found;
    const promo = vol.promotion || {};
    document.getElementById('volumeModalTitle').textContent = 'Editar volume';
    document.getElementById('deleteVolumeBtn').hidden = false;
    document.getElementById('deleteVolumeBtn').dataset.volumeId = vol.id;
    volumeForm.elements['id'].value = vol.id;
    volumeForm.elements['collectionId'].value = col.id;
    volumeForm.elements['authorId'].value = vol.author ? vol.author.id : '';
    volumeForm.elements['volumeLabel'].value = vol.volumeLabel || '';
    volumeForm.elements['subtitle'].value = vol.subtitle || '';
    volumeForm.elements['description'].value = vol.description || '';
    selectedTags = [...(vol.tags || [])];
    renderTagChips();
    volumeForm.elements['price'].value = vol.price;
    volumeForm.elements['promoActive'].checked = !!promo.active;
    volumeForm.elements['promoType'].value = promo.type || 'percent';
    volumeForm.elements['promoValue'].value = promo.value || 0;
    volumeForm.elements['promoLabel'].value = promo.label || '';
    volumeForm.elements['promoStartDate'].value = promo.startDate || '';
    volumeForm.elements['promoEndDate'].value = promo.endDate || '';
    volumeForm.elements['isbn'].value = vol.isbn || '';
    volumeForm.elements['language'].value = vol.language || 'Português (Brasil)';
    volumeForm.elements['pageCount'].value = vol.pageCount ?? '';
    volumeForm.elements['publicationDate'].value = vol.publicationDate || '';
    volumeForm.elements['availability'].value = vol.availability || 'available';
    const n = NOTIFY_COUNTS[vol.id] || 0;
    document.getElementById('notifyCountInfo').textContent = n > 0
      ? `${n} pessoa${n === 1 ? '' : 's'} aguardando aviso de estoque`
      : '';
    document.getElementById('imagesFieldset').hidden = false;
    document.getElementById('imageUploadInput').dataset.volumeId = vol.id;
    renderVolumeImages(vol.images || []);
  } else {
    document.getElementById('volumeModalTitle').textContent = 'Novo volume';
    document.getElementById('deleteVolumeBtn').hidden = true;
    volumeForm.elements['id'].value = '';
    if(defaultCollectionId) volumeForm.elements['collectionId'].value = defaultCollectionId;
    volumeForm.elements['language'].value = 'Português (Brasil)';
    selectedTags = [];
    renderTagChips();
    document.getElementById('notifyCountInfo').textContent = '';
    document.getElementById('imagesFieldset').hidden = true;
  }

  volumeModal.showModal();
}

volumeForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const status = document.getElementById('volumeModalStatus');
  const fd = new FormData(volumeForm);
  status.textContent = 'Salvando...';
  try {
    await postJSON('/api/admin/save-volume.php', {
      id: fd.get('id'),
      collectionId: fd.get('collectionId'),
      authorId: fd.get('authorId'),
      volumeLabel: fd.get('volumeLabel'),
      subtitle: fd.get('subtitle'),
      description: fd.get('description'),
      tags: fd.get('tags'),
      price: fd.get('price'),
      isbn: fd.get('isbn'),
      language: fd.get('language'),
      pageCount: fd.get('pageCount'),
      publicationDate: fd.get('publicationDate'),
      availability: fd.get('availability'),
      promotion: {
        active: fd.get('promoActive') === 'on',
        type: fd.get('promoType'),
        value: fd.get('promoValue'),
        label: fd.get('promoLabel'),
        startDate: fd.get('promoStartDate'),
        endDate: fd.get('promoEndDate')
      }
    });
    volumeModal.close();
    await loadCatalog();
  } catch (err){
    status.textContent = 'Erro: ' + err.message;
  }
});

document.getElementById('deleteVolumeBtn').addEventListener('click', async (e) => {
  const id = e.target.dataset.volumeId;
  if(!confirm('Excluir este volume? Essa ação não pode ser desfeita.')) return;
  try {
    await postJSON('/api/admin/delete-volume.php', { id });
    volumeModal.close();
    await loadCatalog();
  } catch (err){
    document.getElementById('volumeModalStatus').textContent = 'Erro: ' + err.message;
  }
});

document.querySelector('[data-action="close-volume-modal"]').addEventListener('click', () => volumeModal.close());

/* ---------- Modal de coleção (edição) ---------- */
const collectionModal = document.getElementById('collectionModal');
const collectionEditForm = document.getElementById('collectionEditForm');

function openCollectionModal(collectionId){
  const col = COLLECTIONS.find(c => c.id === collectionId);
  if(!col) return;
  collectionEditForm.reset();
  document.getElementById('collectionModalStatus').textContent = '';
  collectionEditForm.elements['id'].value = col.id;
  collectionEditForm.elements['title'].value = col.title;
  collectionEditForm.elements['type'].value = col.type;
  collectionEditForm.elements['accentColor'].value = col.accentColor;
  collectionEditForm.elements['description'].value = col.description || '';
  collectionEditForm.elements['active'].checked = col.active !== false;
  collectionModal.showModal();
}

collectionEditForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const status = document.getElementById('collectionModalStatus');
  const fd = new FormData(collectionEditForm);
  status.textContent = 'Salvando...';
  try {
    await postJSON('/api/admin/update-collection.php', {
      id: fd.get('id'),
      title: fd.get('title'),
      type: fd.get('type'),
      accentColor: fd.get('accentColor'),
      description: fd.get('description'),
      active: fd.get('active') === 'on'
    });
    collectionModal.close();
    await loadCatalog();
  } catch (err){
    status.textContent = 'Erro: ' + err.message;
  }
});

document.querySelector('[data-action="close-collection-modal"]').addEventListener('click', () => collectionModal.close());

/* ---------- Eventos: abrir modal, buscar, coleção, exportar/importar ---------- */
document.getElementById('adminCollections').addEventListener('click', (e) => {
  const openBtn = e.target.closest('[data-open-volume]');
  if(openBtn){ openVolumeModal(openBtn.dataset.openVolume, null); return; }
  const newBtn = e.target.closest('[data-new-volume]');
  if(newBtn){ openVolumeModal(null, newBtn.dataset.newVolume); return; }
  const editColBtn = e.target.closest('[data-edit-collection]');
  if(editColBtn){ openCollectionModal(editColBtn.dataset.editCollection); }
});

document.getElementById('volumeSearch').addEventListener('input', (e) => {
  state.searchTerm = e.target.value.trim();
  renderCollectionsList();
});

const TYPE_DEFAULT_COLORS = { livro: '#d4af37', monografia: '#a29c8f', liturgico: '#4d7ea8', apostila: '#4be78c' };
let collectionColorTouched = false;
document.getElementById('newCollectionColor').addEventListener('input', () => { collectionColorTouched = true; });
document.getElementById('newCollectionType').addEventListener('change', (e) => {
  if(!collectionColorTouched){
    document.getElementById('newCollectionColor').value = TYPE_DEFAULT_COLORS[e.target.value] || '#d4af37';
  }
});

document.getElementById('newCollectionForm').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  try {
    await postJSON('/api/admin/add-collection.php', {
      title: fd.get('title'),
      type: fd.get('type'),
      description: fd.get('description'),
      accentColor: fd.get('accentColor')
    });
    e.target.reset();
    collectionColorTouched = false;
    await loadCatalog();
  } catch (err){
    alert('Não foi possível adicionar a coleção: ' + err.message);
  }
});

document.getElementById('exportBtn').addEventListener('click', async () => {
  const res = await fetch('/api/admin/catalogo-full.php', { credentials: 'same-origin' });
  const data = await res.json();
  const blob = new Blob([JSON.stringify({ collections: data }, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'brasa-agulha-catalogo.json';
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
});

document.getElementById('importInput').addEventListener('change', async e => {
  const file = e.target.files[0];
  if(!file) return;
  try {
    const text = await file.text();
    const parsed = JSON.parse(text);
    const collections = Array.isArray(parsed) ? parsed : parsed.collections;
    if(!Array.isArray(collections)) throw new Error('O arquivo precisa conter uma lista de coleções.');
    await postJSON('/api/admin/import.php', { collections });
    await loadCatalog();
    alert('Configuração importada com sucesso.');
  } catch (err){
    alert('Não foi possível importar: ' + err.message);
  }
  e.target.value = '';
});

/* ---------- Inicialização ---------- */
loadCatalog();
