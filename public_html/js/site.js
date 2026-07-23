/* ==========================================================================
   BRASA & AGULHA EDITORIAL — site público
   Os dados do catálogo agora vêm de /api/catalogo.php (banco de dados),
   não mais de um array fixo no código. A edição de preços/promoções
   acontece no painel administrativo (/admin/), protegido por login.
   ========================================================================== */

const SITE_CONFIG = {
  contactEmail: 'contato@brasaeagulha.com.br',
  socials: [
    { label: 'Instagram', href: '#' },
    { label: 'Facebook', href: '#' },
    { label: 'Pinterest', href: '#' }
  ]
};

let COLLECTIONS = [];

const state = {
  searchTerm: '',
  activeTag: null,
  activeCollection: null,
  contactContext: null
};

/* ---------- Navegação lateral ---------- */
function renderSidebarCollectionsNav(){
  const el = document.getElementById('collectionsNav');
  el.innerHTML = COLLECTIONS.map(col => `
    <a href="#colecao-${esc(col.id)}" class="collection-nav-link" data-collection-link="${esc(col.id)}">${esc(col.title)}</a>
  `).join('');
}

function renderTagCloud(){
  const freq = {};
  getAllVolumes(COLLECTIONS).forEach(({ vol }) => (vol.tags || []).forEach(t => { freq[t] = (freq[t] || 0) + 1; }));
  const tags = Object.keys(freq);
  const el = document.getElementById('tagCloud');
  if(!tags.length){ el.innerHTML = '<p class="form-hint">Nenhum tema cadastrado ainda.</p>'; return; }
  const max = Math.max(...tags.map(t => freq[t]));
  el.innerHTML = tags.sort().map(t => {
    const size = 0.72 + (freq[t] / max) * 0.5;
    const active = state.activeTag === t ? ' active' : '';
    return `<button type="button" class="tag-pill${active}" style="font-size:${size.toFixed(2)}rem" data-action="toggle-tag" data-tag="${esc(t)}">${esc(t)}</button>`;
  }).join('');
}

function updateActiveNav(hash){
  document.querySelectorAll('.nav-link').forEach(a => {
    a.classList.toggle('active', ('#' + a.dataset.route) === hash || (a.dataset.route === 'catalogo' && hash.startsWith('#colecao-')));
  });
  document.querySelectorAll('.collection-nav-link').forEach(a => {
    a.classList.toggle('active', hash === '#colecao-' + a.dataset.collectionLink);
  });
}

/* ---------- Home ---------- */
function renderHome(){
  const preview = document.getElementById('collectionsPreview');
  preview.innerHTML = COLLECTIONS.map(col => `
    <div class="collection-preview-card" style="--accent:${esc(col.accentColor)}">
      <span class="type-pill">${esc(TYPE_LABELS[col.type] || col.type)}</span>
      <h3>${esc(col.title)}</h3>
      <p>${esc(col.description || '')}</p>
      <a href="#colecao-${esc(col.id)}" class="btn btn-ghost">Conhecer esta coleção</a>
    </div>
  `).join('');

  const promoWrap = document.getElementById('promoStrip');
  const promoVols = getAllVolumes(COLLECTIONS).filter(({ vol }) => getPricing(vol).hasPromo);
  if(!promoVols.length){
    promoWrap.hidden = true;
  } else {
    promoWrap.hidden = false;
    promoWrap.innerHTML = `
      <h2>Promoções em andamento</h2>
      <div class="promo-items">
        ${promoVols.map(({ vol, col }) => {
          const p = getPricing(vol);
          return `
          <a class="promo-item" href="#livro-${esc(vol.id)}">
            <span class="promo-sigil" aria-hidden="true">${esc(TYPE_SIGILS[col.type] || 'ᛟ')}</span>
            <span class="promo-info">
              <strong>${esc(col.title)} — ${esc(vol.volumeLabel)}</strong>
              ${esc(vol.promotion.label || 'Promoção ativa')} · ${formatBRL(p.final)}
            </span>
          </a>`;
        }).join('')}
      </div>
    `;
  }
}

/* ---------- Cartão de livro ---------- */
function cardTemplate(vol, col){
  const p = getPricing(vol);
  const accent = col.accentColor || '#d4af37';
  return `
    <article class="card" style="--accent:${esc(accent)}">
      <div class="card-cover">
        <span class="card-type-pill type-pill">${esc(TYPE_LABELS[col.type] || col.type)}</span>
        <span class="cover-sigil-wrap"><span class="cover-sigil" aria-hidden="true">${esc(TYPE_SIGILS[col.type] || 'ᛟ')}</span></span>
      </div>
      <div class="card-body">
        <p class="card-collection">${esc(col.title)}</p>
        <h3 class="card-title">${esc(vol.subtitle || vol.volumeLabel)}</h3>
        <p class="card-volume">${esc(vol.volumeLabel)}${vol.author ? ` · <a href="/autor/${esc(vol.author.id)}" class="card-author-link">${esc(vol.author.name)}</a>` : ''}</p>
        <div class="card-tags">
          ${(vol.tags || []).slice(0, 4).map(t => `<button type="button" class="tag-pill" data-action="toggle-tag" data-tag="${esc(t)}">${esc(t)}</button>`).join('')}
        </div>
        <div class="price-row">
          ${p.hasPromo ? `<span class="price-original">${formatBRL(p.original)}</span>` : ''}
          <span class="price-final">${formatBRL(p.final)}</span>
          ${p.hasPromo ? `<span class="promo-badge">${esc(p.label || 'Promoção')}</span>` : ''}
        </div>
        <div class="card-actions">
          <a class="btn btn-primary" href="#livro-${esc(vol.id)}">Ver detalhes</a>
          <button type="button" class="btn btn-ghost" data-action="contact-book" data-volume="${esc(vol.id)}">Perguntar sobre esta obra</button>
        </div>
      </div>
    </article>
  `;
}

/* ---------- Catálogo ---------- */
function renderCatalog(){
  const title = document.getElementById('catalogTitle');
  const subtitle = document.getElementById('catalogSubtitle');
  const activeCol = state.activeCollection ? COLLECTIONS.find(c => c.id === state.activeCollection) : null;

  title.textContent = activeCol ? activeCol.title : 'Catálogo completo';
  subtitle.textContent = activeCol ? (activeCol.description || '') : 'Todos os títulos da Brasa & Agulha Editorial, em um só lugar.';

  let entries = getAllVolumes(COLLECTIONS);
  if(activeCol) entries = entries.filter(e => e.col.id === activeCol.id);
  if(state.activeTag) entries = entries.filter(e => (e.vol.tags || []).includes(state.activeTag));
  if(state.searchTerm){
    const term = normalize(state.searchTerm);
    entries = entries.filter(({ vol, col }) => {
      const haystack = normalize([col.title, vol.volumeLabel, vol.subtitle, vol.description, (vol.tags || []).join(' ')].join(' '));
      return haystack.includes(term);
    });
  }

  renderActiveFilters();

  const grid = document.getElementById('catalogGrid');
  const empty = document.getElementById('catalogEmpty');
  if(!entries.length){
    grid.innerHTML = '';
    empty.hidden = false;
  } else {
    empty.hidden = true;
    grid.innerHTML = entries.map(({ vol, col }) => cardTemplate(vol, col)).join('');
  }
}

function renderActiveFilters(){
  const wrap = document.getElementById('activeFilters');
  const chips = [];
  if(state.activeCollection){
    const col = COLLECTIONS.find(c => c.id === state.activeCollection);
    if(col) chips.push({ label: 'Coleção: ' + col.title, action: 'clear-collection' });
  }
  if(state.activeTag){
    chips.push({ label: 'Tema: ' + state.activeTag, action: 'clear-tag' });
  }
  if(state.searchTerm){
    chips.push({ label: 'Busca: "' + state.searchTerm + '"', action: 'clear-search' });
  }
  wrap.innerHTML = chips.map(c => `
    <span class="filter-chip">${esc(c.label)} <button type="button" data-action="${c.action}" aria-label="Remover filtro">×</button></span>
  `).join('');
}

/* ---------- Detalhe do livro ---------- */
function renderBookDetail(volId){
  const found = findVolumeIn(COLLECTIONS, volId);
  const content = document.getElementById('bookDetailContent');
  if(!found){
    content.innerHTML = `<p>Título não encontrado. <a href="#catalogo">Voltar ao catálogo</a></p>`;
    return;
  }
  const { vol, col } = found;
  const p = getPricing(vol);
  content.innerHTML = `
    <a href="#colecao-${esc(col.id)}" class="back-link">← Voltar para ${esc(col.title)}</a>
    <div class="book-detail">
      <div class="book-detail-cover" style="--accent:${esc(col.accentColor)}">
        <span class="cover-sigil-wrap"><span class="cover-sigil" aria-hidden="true">${esc(TYPE_SIGILS[col.type] || 'ᛟ')}</span></span>
      </div>
      <div>
        <span class="type-pill">${esc(TYPE_LABELS[col.type] || col.type)}</span>
        <h1 class="book-detail-title">${esc(vol.subtitle || vol.volumeLabel)}</h1>
        <p class="book-detail-volume">${esc(col.title)} · ${esc(vol.volumeLabel)}</p>
        <p class="book-detail-desc">${esc(vol.description || '')}</p>
        ${vol.author ? `<p class="book-detail-author">por <a href="/autor/${esc(vol.author.id)}">${esc(vol.author.name)}</a></p>` : ''}
        <div class="book-detail-tags">
          ${(vol.tags || []).map(t => `<button type="button" class="tag-pill" data-action="toggle-tag" data-tag="${esc(t)}">${esc(t)}</button>`).join('')}
        </div>
        <dl class="book-detail-meta-list">
          ${vol.isbn ? `<dt>ISBN</dt><dd>${esc(vol.isbn)}</dd>` : ''}
          ${vol.language ? `<dt>Idioma</dt><dd>${esc(vol.language)}</dd>` : ''}
          ${vol.pageCount ? `<dt>Páginas</dt><dd>${esc(vol.pageCount)}</dd>` : ''}
          ${vol.publicationDate ? `<dt>Publicação</dt><dd>${esc(formatDateBR(vol.publicationDate))}</dd>` : ''}
        </dl>
        <div class="book-detail-price">
          ${p.hasPromo ? `<span class="price-original">${formatBRL(p.original)}</span>` : ''}
          <span class="price-final">${formatBRL(p.final)}</span>
          ${p.hasPromo ? `<span class="promo-badge">${esc(p.label || 'Promoção')}</span>` : ''}
        </div>
        <div class="book-detail-actions">
          <button type="button" class="btn btn-primary" data-action="contact-book" data-volume="${esc(vol.id)}">Entrar em contato sobre esta obra</button>
          <a class="btn btn-ghost" href="#catalogo">Voltar ao catálogo</a>
        </div>
      </div>
    </div>
  `;
}

/* ---------- Contato ---------- */
function renderContact(){
  document.getElementById('contactEmailDisplay').textContent = SITE_CONFIG.contactEmail;

  const banner = document.getElementById('contactContextBanner');
  const subjectInput = document.getElementById('contactSubject');
  const messageInput = document.getElementById('contactMessage');

  if(state.contactContext){
    const ctx = state.contactContext;
    banner.hidden = false;
    banner.innerHTML = `<span>Você está entrando em contato sobre: <strong>${esc(ctx.collectionTitle)} — ${esc(ctx.volumeLabel)}</strong></span>
      <button type="button" data-action="clear-contact-context">Remover referência</button>`;
    subjectInput.value = `Interesse em: ${ctx.collectionTitle} — ${ctx.volumeLabel}`;
    if(!messageInput.dataset.userEdited){
      messageInput.value = `Olá! Gostaria de saber mais sobre "${ctx.collectionTitle} — ${ctx.volumeLabel}".`;
    }
  } else {
    banner.hidden = true;
    if(!subjectInput.dataset.userEdited) subjectInput.value = '';
  }

  const list = document.getElementById('socialList');
  list.innerHTML = SITE_CONFIG.socials.map(s => `<li><a href="${esc(s.href)}">${esc(s.label)}</a></li>`).join('');
}

/* ---------- Roteador ---------- */
function show(id){
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  document.getElementById(id).classList.add('active');
}
function router(){
  const hash = location.hash || '#home';
  updateActiveNav(hash);

  if(hash.startsWith('#livro-')){
    renderBookDetail(hash.replace('#livro-', ''));
    show('view-book');
  } else if(hash === '#catalogo'){
    state.activeCollection = null;
    renderCatalog();
    show('view-catalog');
  } else if(hash.startsWith('#colecao-')){
    state.activeCollection = hash.replace('#colecao-', '');
    renderCatalog();
    show('view-catalog');
  } else if(hash === '#contato'){
    renderContact();
    show('view-contact');
  } else {
    renderHome();
    show('view-home');
  }
  window.scrollTo({ top: 0, behavior: 'auto' });
  closeSidebar();
}

/* ---------- Sidebar mobile ---------- */
function openSidebar(){
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('sidebarOverlay').classList.add('show');
  document.getElementById('sidebarToggle').setAttribute('aria-expanded', 'true');
}
function closeSidebar(){
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('show');
  document.getElementById('sidebarToggle').setAttribute('aria-expanded', 'false');
}

/* ---------- Carregamento inicial do catálogo (via API) ---------- */
async function loadCatalog(){
  const grid = document.getElementById('catalogGrid');
  try {
    const res = await fetch('/api/catalogo.php');
    if(!res.ok) throw new Error('Resposta ' + res.status);
    COLLECTIONS = await res.json();
  } catch (err){
    console.error('Falha ao carregar catálogo:', err);
    grid.innerHTML = '<p class="empty-state">Não foi possível carregar o catálogo agora. Tente recarregar a página em instantes.</p>';
  }
  renderSidebarCollectionsNav();
  renderTagCloud();
  router();
}

/* ---------- Eventos ---------- */
document.addEventListener('click', e => {
  const actionEl = e.target.closest('[data-action]');
  if(!actionEl) return;
  const action = actionEl.dataset.action;

  if(action === 'toggle-tag'){
    const tag = actionEl.dataset.tag;
    state.activeTag = (state.activeTag === tag) ? null : tag;
    if(location.hash !== '#catalogo' && !location.hash.startsWith('#colecao-')){
      location.hash = '#catalogo';
    } else {
      renderCatalog();
    }
    renderTagCloud();
  }
  if(action === 'clear-tag'){ state.activeTag = null; renderCatalog(); renderTagCloud(); }
  if(action === 'clear-collection'){ state.activeCollection = null; location.hash = '#catalogo'; }
  if(action === 'clear-search'){
    state.searchTerm = '';
    document.getElementById('searchInput').value = '';
    renderCatalog();
  }
  if(action === 'contact-book'){
    const found = findVolumeIn(COLLECTIONS, actionEl.dataset.volume);
    if(found){
      state.contactContext = {
        volumeId: found.vol.id,
        volumeLabel: found.vol.volumeLabel,
        collectionTitle: found.col.title
      };
      document.getElementById('contactMessage').removeAttribute('data-user-edited');
      document.getElementById('contactSubject').removeAttribute('data-user-edited');
    }
    location.hash = '#contato';
    renderContact();
  }
  if(action === 'clear-contact-context'){
    state.contactContext = null;
    renderContact();
  }
});

window.addEventListener('hashchange', router);

document.getElementById('sidebarToggle').addEventListener('click', () => {
  const sidebar = document.getElementById('sidebar');
  sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
});
document.getElementById('sidebarOverlay').addEventListener('click', closeSidebar);

document.getElementById('searchForm').addEventListener('submit', e => {
  e.preventDefault();
  state.searchTerm = document.getElementById('searchInput').value.trim();
  if(location.hash !== '#catalogo' && !location.hash.startsWith('#colecao-')){
    location.hash = '#catalogo';
  } else {
    renderCatalog();
  }
});
document.getElementById('searchInput').addEventListener('input', e => {
  state.searchTerm = e.target.value.trim();
  if(location.hash === '#catalogo' || location.hash.startsWith('#colecao-')){
    renderCatalog();
  }
});

document.getElementById('contactForm').addEventListener('submit', e => {
  e.preventDefault();
  const form = e.target;
  const name = form.name.value.trim();
  const email = form.email.value.trim();
  const subject = form.subject.value.trim();
  const message = form.message.value.trim();
  const body = 'Nome: ' + name + '\nE-mail: ' + email + '\n\n' + message;
  window.location.href = 'mailto:' + SITE_CONFIG.contactEmail + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
});
document.getElementById('contactSubject').addEventListener('input', e => e.target.setAttribute('data-user-edited', '1'));
document.getElementById('contactMessage').addEventListener('input', e => e.target.setAttribute('data-user-edited', '1'));

/* ---------- Inicialização ---------- */
document.getElementById('footerYear').textContent = new Date().getFullYear();
loadCatalog();
