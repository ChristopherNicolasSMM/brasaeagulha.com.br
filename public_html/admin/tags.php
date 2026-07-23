<?php
declare(strict_types=1);
require __DIR__ . '/../../config.php';
ba_start_session();
ba_require_admin_page();

$csrf = ba_csrf_token();
$username = $_SESSION['admin_username'] ?? 'admin';
$pdo = ba_db();
$tags = $pdo->query('SELECT t.*, (SELECT COUNT(*) FROM volume_tags vt WHERE vt.tag_id = t.id) AS usage_count FROM tags t ORDER BY t.active DESC, t.name COLLATE NOCASE')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Tags · Painel administrativo · Brasa &amp; Agulha</title>
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
  .tag-usage{ font-family:var(--font-ui); font-size:.78rem; color:var(--gold-soft); white-space:nowrap; }
  .volume-row.inactive{ opacity:.5; }
  .volume-row.inactive .volume-row-title small{ color: var(--brasa); }
</style>
</head>
<body>
<div class="admin-shell">
  <div class="admin-header">
    <div class="admin-header-brand">
      <img src="/img/logo.png" alt="">
      <div>
        <strong>Tags</strong>
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
    <a href="/admin/autores.php">Autores</a>
    <a href="/admin/tags.php" class="active">Tags</a>
    <a href="/admin/interesse.php">Interesse</a>
    <a href="/admin/cartao.php">Cartão de visita</a>
    <a href="/admin/paginas.php">Páginas</a>
  </div>

  <p class="view-subtitle">Tags inativadas somem da nuvem de temas e das sugestões de cadastro, mas continuam nos livros que já as usam — nada é apagado.</p>

  <div class="admin-toolbar">
    <button type="button" class="btn btn-primary" id="newTagBtn">+ Nova tag</button>
  </div>

  <label class="admin-search-field">
    <input type="search" id="tagSearch" placeholder="Buscar por nome…">
  </label>

  <div id="tagList" class="volume-list">
    <?php foreach ($tags as $t): ?>
    <button type="button" class="volume-row<?= $t['active'] ? '' : ' inactive' ?>" data-open-tag="<?= htmlspecialchars($t['id'], ENT_QUOTES, 'UTF-8') ?>">
      <span class="volume-row-title">
        <?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8') ?>
        <small><?= $t['active'] ? 'ativa' : 'inativa' ?></small>
      </span>
      <span class="tag-usage"><?= (int) $t['usage_count'] ?> uso<?= ((int) $t['usage_count']) === 1 ? '' : 's' ?></span>
    </button>
    <?php endforeach; ?>
    <?php if (!$tags): ?><p class="empty-list-hint">Nenhuma tag cadastrada ainda.</p><?php endif; ?>
  </div>
</div>

<dialog id="tagModal" class="admin-modal">
  <form id="tagForm" method="dialog">
    <div class="admin-modal-header">
      <h2 id="tagModalTitle">Nova tag</h2>
      <button type="button" class="admin-modal-close" data-action="close-tag-modal" aria-label="Fechar">×</button>
    </div>
    <div class="admin-modal-body">
      <input type="hidden" name="id" value="">
      <div class="admin-field-row">
        <label style="flex-basis:100%">Nome <input type="text" name="name" required></label>
      </div>
      <div class="admin-field-row">
        <label class="checkbox-label"><input type="checkbox" name="active" checked> Ativa (aparece na nuvem de temas e nas sugestões)</label>
      </div>
    </div>
    <div class="admin-modal-footer">
      <span id="tagModalStatus" class="modal-status"></span>
      <button type="submit" class="btn btn-primary" style="margin-left:auto;">Salvar</button>
    </div>
  </form>
</dialog>

<script>
window.BA_CSRF = <?= json_encode($csrf) ?>;
let TAGS = <?= json_encode($tags, JSON_UNESCAPED_UNICODE) ?>;
let tagSearchTerm = '';

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

function renderTagList(){
  const term = tagSearchTerm.toLowerCase();
  const filtered = TAGS.filter(t => !term || t.name.toLowerCase().includes(term));
  const list = document.getElementById('tagList');
  if(!filtered.length){ list.innerHTML = '<p class="empty-list-hint">Nenhuma tag encontrada.</p>'; return; }
  list.innerHTML = filtered.map(t => `
    <button type="button" class="volume-row${t.active ? '' : ' inactive'}" data-open-tag="${esc(t.id)}">
      <span class="volume-row-title">${esc(t.name)}<small>${t.active ? 'ativa' : 'inativa'}</small></span>
      <span class="tag-usage">${t.usage_count} uso${t.usage_count === 1 ? '' : 's'}</span>
    </button>
  `).join('');
}

const tagModal = document.getElementById('tagModal');
const tagForm = document.getElementById('tagForm');

function openTagModal(id){
  tagForm.reset();
  document.getElementById('tagModalStatus').textContent = '';
  const tag = id ? TAGS.find(t => t.id === id) : null;
  if(tag){
    document.getElementById('tagModalTitle').textContent = 'Editar tag';
    tagForm.elements['id'].value = tag.id;
    tagForm.elements['name'].value = tag.name;
    tagForm.elements['active'].checked = !!tag.active;
  } else {
    document.getElementById('tagModalTitle').textContent = 'Nova tag';
    tagForm.elements['id'].value = '';
    tagForm.elements['active'].checked = true;
  }
  tagModal.showModal();
}

tagForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const status = document.getElementById('tagModalStatus');
  const fd = new FormData(tagForm);
  status.textContent = 'Salvando...';
  try {
    const result = await postJSON('/api/admin/save-tag.php', {
      id: fd.get('id'),
      name: fd.get('name'),
      active: fd.get('active') === 'on'
    });
    tagModal.close();
    const res = await fetch('/api/admin/tags-list.php', { credentials: 'same-origin' });
    TAGS = await res.json();
    renderTagList();
  } catch (err){
    status.textContent = 'Erro: ' + err.message;
  }
});

document.querySelector('[data-action="close-tag-modal"]').addEventListener('click', () => tagModal.close());
document.getElementById('newTagBtn').addEventListener('click', () => openTagModal(null));
document.getElementById('tagList').addEventListener('click', (e) => {
  const btn = e.target.closest('[data-open-tag]');
  if(btn) openTagModal(btn.dataset.openTag);
});
document.getElementById('tagSearch').addEventListener('input', (e) => {
  tagSearchTerm = e.target.value.trim();
  renderTagList();
});
</script>
</body>
</html>
