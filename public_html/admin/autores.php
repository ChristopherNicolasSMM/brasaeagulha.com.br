<?php
declare(strict_types=1);
require __DIR__ . '/../../config.php';
ba_start_session();
ba_require_admin_page();

$csrf = ba_csrf_token();
$username = $_SESSION['admin_username'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Autores · Painel administrativo · Brasa &amp; Agulha</title>
<link rel="icon" href="/img/favicon-32.png">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Crimson+Text:ital,wght@0,600;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/site.css">
<style>
  body{ padding: 0; }
  .admin-shell{ max-width: 760px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
  .admin-header{ display:flex; align-items:center; justify-content:space-between; gap:1em; flex-wrap:wrap; margin-bottom:1rem; }
  .admin-header-brand{ display:flex; align-items:center; gap:.8em; }
  .admin-header-brand img{ width:46px; height:46px; border-radius:50%; }
  .admin-header-brand strong{ font-family:var(--font-display); font-size:1.1rem; }
  .admin-header-brand span{ display:block; font-family:var(--font-ui); font-size:.8rem; color:var(--snow-muted); }
  .admin-header-actions{ display:flex; gap:.7em; flex-wrap:wrap; }
  .admin-tabs{ display:flex; gap:.6em; margin-bottom:1.8rem; flex-wrap:wrap; }
  .admin-tabs a{ font-family:var(--font-ui); font-size:.88rem; padding:.5em 1em; border-radius:999px; border:1px solid rgba(212,175,55,.3); color:var(--snow-muted); }
  .admin-tabs a.active{ background:var(--gold-ember); color:#14212b; border-color:var(--gold-ember); font-weight:600; }
  .book-count{ font-family:var(--font-ui); font-size:.78rem; color:var(--gold-soft); white-space:nowrap; }
</style>
</head>
<body>
<div class="admin-shell">
  <div class="admin-header">
    <div class="admin-header-brand">
      <img src="/img/logo.png" alt="">
      <div>
        <strong>Autores</strong>
        <span>Conectado como <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </div>
    <div class="admin-header-actions">
      <a class="btn btn-ghost" href="/">Ver site</a>
      <a class="btn btn-ghost" href="/admin/logout.php">Sair</a>
    </div>
  </div>

  <div class="admin-tabs">
    <a href="/admin/">Catálogo</a>
    <a href="/admin/autores.php" class="active">Autores</a>
    <a href="/admin/tags.php">Tags</a>
    <a href="/admin/cartao.php">Cartão de visita</a>
    <a href="/admin/paginas.php">Páginas</a>
  </div>

  <p class="view-subtitle">Cada autor ganha uma página pública em <code>/autor/{id}</code>. Livros são vinculados na tela de Catálogo.</p>

  <div class="admin-toolbar">
    <button type="button" class="btn btn-primary" id="newAuthorBtn">+ Novo autor</button>
  </div>

  <label class="admin-search-field">
    <input type="search" id="authorSearch" placeholder="Buscar por nome…">
  </label>

  <div id="authorList" class="volume-list"></div>
</div>

<dialog id="authorModal" class="admin-modal">
  <form id="authorForm" method="dialog">
    <div class="admin-modal-header">
      <h2 id="authorModalTitle">Novo autor</h2>
      <button type="button" class="admin-modal-close" data-action="close-author-modal" aria-label="Fechar">×</button>
    </div>
    <div class="admin-modal-body">
      <input type="hidden" name="id" value="">
      <div class="admin-field-row">
        <label style="flex-basis:100%">Nome <input type="text" name="name" required></label>
      </div>
      <div class="admin-field-row">
        <label style="flex-basis:100%">Bio <textarea name="bio" rows="4"></textarea></label>
      </div>
      <div class="admin-field-row">
        <label style="flex-basis:100%">Foto (URL) <input type="url" name="photo_url"></label>
      </div>
    </div>
    <div class="admin-modal-footer">
      <button type="button" class="btn btn-danger" id="deleteAuthorBtn" hidden>Excluir autor</button>
      <div style="display:flex;gap:.7em;align-items:center;margin-left:auto;">
        <span id="authorModalStatus" class="modal-status"></span>
        <button type="submit" class="btn btn-primary">Salvar</button>
      </div>
    </div>
  </form>
</dialog>

<script>
window.BA_CSRF = <?= json_encode($csrf) ?>;

let AUTHORS = [];
let BOOK_COUNTS = {};
let searchTerm = '';

async function postJSON(url, data){
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ ...data, csrf: window.BA_CSRF })
  });
  let json = {};
  try { json = await res.json(); } catch(e) {}
  if (!res.ok) throw new Error(json.error || ('Erro ' + res.status));
  return json;
}

function esc(str){
  return String(str ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
}

async function loadAuthors(){
  const [authorsRes, catalogRes] = await Promise.all([
    fetch('/api/authors.php', { credentials: 'same-origin' }),
    fetch('/api/catalogo.php', { credentials: 'same-origin' })
  ]);
  AUTHORS = await authorsRes.json();
  const collections = await catalogRes.json();
  BOOK_COUNTS = {};
  collections.forEach(col => (col.volumes || []).forEach(v => {
    if (v.author) BOOK_COUNTS[v.author.id] = (BOOK_COUNTS[v.author.id] || 0) + 1;
  }));
  renderAuthorList();
}

function renderAuthorList(){
  const term = searchTerm.toLowerCase();
  const filtered = AUTHORS.filter(a => !term || a.name.toLowerCase().includes(term));
  const list = document.getElementById('authorList');
  if (!filtered.length){
    list.innerHTML = '<p class="empty-list-hint">Nenhum autor encontrado.</p>';
    return;
  }
  list.innerHTML = filtered.map(a => {
    const n = BOOK_COUNTS[a.id] || 0;
    return `
      <button type="button" class="volume-row" data-open-author="${esc(a.id)}">
        <span class="volume-row-title">${esc(a.name)}<small>/autor/${esc(a.id)}</small></span>
        <span class="book-count">${n} obra${n === 1 ? '' : 's'}</span>
      </button>
    `;
  }).join('');
}

const authorModal = document.getElementById('authorModal');
const authorForm = document.getElementById('authorForm');

function openAuthorModal(id){
  authorForm.reset();
  document.getElementById('authorModalStatus').textContent = '';
  const author = id ? AUTHORS.find(a => a.id === id) : null;

  if (author){
    document.getElementById('authorModalTitle').textContent = 'Editar autor';
    document.getElementById('deleteAuthorBtn').hidden = false;
    document.getElementById('deleteAuthorBtn').dataset.authorId = author.id;
    authorForm.elements['id'].value = author.id;
    authorForm.elements['name'].value = author.name;
    authorForm.elements['bio'].value = author.bio || '';
    authorForm.elements['photo_url'].value = author.photo_url || '';
  } else {
    document.getElementById('authorModalTitle').textContent = 'Novo autor';
    document.getElementById('deleteAuthorBtn').hidden = true;
    authorForm.elements['id'].value = '';
  }
  authorModal.showModal();
}

authorForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const status = document.getElementById('authorModalStatus');
  const fd = new FormData(authorForm);
  status.textContent = 'Salvando...';
  try {
    await postJSON('/api/admin/save-author.php', {
      id: fd.get('id'),
      name: fd.get('name'),
      bio: fd.get('bio'),
      photo_url: fd.get('photo_url')
    });
    authorModal.close();
    await loadAuthors();
  } catch (err) {
    status.textContent = 'Erro: ' + err.message;
  }
});

document.getElementById('deleteAuthorBtn').addEventListener('click', async (e) => {
  const id = e.target.dataset.authorId;
  if (!confirm('Excluir este autor? Os livros dele ficam sem autor, mas não são apagados.')) return;
  try {
    await postJSON('/api/admin/delete-author.php', { id });
    authorModal.close();
    await loadAuthors();
  } catch (err) {
    document.getElementById('authorModalStatus').textContent = 'Erro: ' + err.message;
  }
});

document.querySelector('[data-action="close-author-modal"]').addEventListener('click', () => authorModal.close());
document.getElementById('newAuthorBtn').addEventListener('click', () => openAuthorModal(null));
document.getElementById('authorList').addEventListener('click', (e) => {
  const btn = e.target.closest('[data-open-author]');
  if (btn) openAuthorModal(btn.dataset.openAuthor);
});
document.getElementById('authorSearch').addEventListener('input', (e) => {
  searchTerm = e.target.value.trim();
  renderAuthorList();
});

loadAuthors();
</script>
</body>
</html>
