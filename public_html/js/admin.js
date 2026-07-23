/* ==========================================================================
   BRASA & AGULHA EDITORIAL — painel administrativo (Catálogo)
   Esta página só é servida a quem tem sessão válida (ver admin/index.php).
   Toda escrita passa pelos endpoints em /api/admin/*.php, que conferem
   a sessão de novo no servidor e exigem o token CSRF em window.BA_CSRF.
   ========================================================================== */

let COLLECTIONS = [];
let AUTHORS = [];
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
  const [catalogRes, authorsRes] = await Promise.all([
    fetch('/api/catalogo.php', { credentials: 'same-origin' }),
    fetch('/api/authors.php', { credentials: 'same-origin' })
  ]);
  COLLECTIONS = await catalogRes.json();
  AUTHORS = await authorsRes.json();
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

function volumeRowTemplate(vol, col){
  const p = getPricing(vol);
  return `
    <button type="button" class="volume-row" data-open-volume="${esc(vol.id)}">
      <span class="volume-row-title">
        ${esc(vol.subtitle || vol.volumeLabel)}
        <small>${esc(vol.volumeLabel)}${vol.author ? ' · ' + esc(vol.author.name) : ''}${vol.isbn ? ' · ISBN ' + esc(vol.isbn) : ''}</small>
      </span>
      <span class="volume-row-price">
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
      <div>
        <div class="admin-collection-group-header" style="--accent:${esc(col.accentColor)}">
          <h3>${esc(col.title)}</h3>
          <span class="type-pill">${esc(TYPE_LABELS[col.type] || col.type)}</span>
          <button type="button" class="btn btn-ghost" data-new-volume="${esc(col.id)}">+ Novo volume</button>
        </div>
        <div class="volume-list">${rows}</div>
      </div>
    `;
  }).join('');
  document.getElementById('adminCollections').innerHTML = html || '<p class="empty-list-hint">Nenhum resultado para essa busca.</p>';
}

/* ---------- Modal de volume (criação e edição) ---------- */
const volumeModal = document.getElementById('volumeModal');
const volumeForm = document.getElementById('volumeForm');

function populateModalSelects(){
  const colSelect = volumeForm.elements['collectionId'];
  colSelect.innerHTML = COLLECTIONS.map(c => `<option value="${esc(c.id)}">${esc(c.title)}</option>`).join('');

  const authorSelect = volumeForm.elements['authorId'];
  authorSelect.innerHTML = '<option value="">— Sem autor definido —</option>' +
    AUTHORS.map(a => `<option value="${esc(a.id)}">${esc(a.name)}</option>`).join('');
}

function openVolumeModal(volumeId, defaultCollectionId){
  const found = volumeId ? findVolumeIn(COLLECTIONS, volumeId) : null;
  volumeForm.reset();
  document.getElementById('volumeModalStatus').textContent = '';

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
    volumeForm.elements['tags'].value = (vol.tags || []).join(', ');
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
  } else {
    document.getElementById('volumeModalTitle').textContent = 'Novo volume';
    document.getElementById('deleteVolumeBtn').hidden = true;
    volumeForm.elements['id'].value = '';
    if(defaultCollectionId) volumeForm.elements['collectionId'].value = defaultCollectionId;
    volumeForm.elements['language'].value = 'Português (Brasil)';
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

/* ---------- Eventos: abrir modal, buscar, coleção, exportar/importar ---------- */
document.getElementById('adminCollections').addEventListener('click', (e) => {
  const openBtn = e.target.closest('[data-open-volume]');
  if(openBtn){ openVolumeModal(openBtn.dataset.openVolume, null); return; }
  const newBtn = e.target.closest('[data-new-volume]');
  if(newBtn){ openVolumeModal(null, newBtn.dataset.newVolume); }
});

document.getElementById('volumeSearch').addEventListener('input', (e) => {
  state.searchTerm = e.target.value.trim();
  renderCollectionsList();
});

document.getElementById('newCollectionForm').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  try {
    await postJSON('/api/admin/add-collection.php', {
      title: fd.get('title'),
      type: fd.get('type'),
      description: fd.get('description')
    });
    e.target.reset();
    await loadCatalog();
  } catch (err){
    alert('Não foi possível adicionar a coleção: ' + err.message);
  }
});

document.getElementById('exportBtn').addEventListener('click', async () => {
  const res = await fetch('/api/catalogo.php', { credentials: 'same-origin' });
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
