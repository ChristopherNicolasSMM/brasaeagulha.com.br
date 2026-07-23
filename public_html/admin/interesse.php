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
<title>Interesse em estoque · Painel administrativo · Brasa &amp; Agulha</title>
<link rel="icon" href="/img/favicon-32.png">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Crimson+Text:ital,wght@0,600;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/site.css">
<style>
  body{ padding: 0; }
  .admin-shell{ max-width: 900px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
  .admin-header{ display:flex; align-items:center; justify-content:space-between; gap:1em; flex-wrap:wrap; margin-bottom:1rem; }
  .admin-header-brand{ display:flex; align-items:center; gap:.8em; }
  .admin-header-brand img{ width:46px; height:46px; border-radius:50%; }
  .admin-header-brand strong{ font-family:var(--font-display); font-size:1.1rem; }
  .admin-header-brand span{ display:block; font-family:var(--font-ui); font-size:.8rem; color:var(--snow-muted); }
  .admin-header-actions{ display:flex; gap:.7em; flex-wrap:wrap; }
  .admin-tabs{ display:flex; gap:.6em; margin-bottom:1.8rem; flex-wrap:wrap; }
  .admin-tabs a{ font-family:var(--font-ui); font-size:.88rem; padding:.5em 1em; border-radius:999px; border:1px solid rgba(212,175,55,.3); color:var(--snow-muted); }
  .admin-tabs a.active{ background:var(--gold-ember); color:#14212b; border-color:var(--gold-ember); font-weight:600; }

  .interest-filters{ display:flex; gap:1em; flex-wrap:wrap; margin-bottom:1.2rem; align-items:flex-end; }
  .interest-filters label{ display:flex; flex-direction:column; gap:.3em; font-family:var(--font-ui); font-size:.78rem; color:var(--snow-muted); text-transform:uppercase; letter-spacing:.04em; }
  .interest-filters select{ font-family:var(--font-body); color:var(--snow); background:rgba(255,255,255,.05); border:1px solid rgba(212,175,55,.28); border-radius:8px; padding:.5em .7em; min-width:220px; }

  .interest-bulkbar{ display:flex; gap:.8em; align-items:center; margin-bottom:1rem; font-family:var(--font-ui); font-size:.85rem; color:var(--snow-muted); flex-wrap:wrap; }

  .interest-table{ width:100%; border-collapse:collapse; font-family:var(--font-ui); font-size:.88rem; }
  .interest-table th{ text-align:left; padding:.6em .5em; color:var(--snow-muted); font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid rgba(212,175,55,.2); }
  .interest-table td{ padding:.65em .5em; border-bottom:1px solid rgba(255,255,255,.06); vertical-align:top; }
  .interest-table tr.sent{ opacity:.55; }
  .interest-status{ font-size:.72rem; font-weight:700; padding:.2em .55em; border-radius:5px; white-space:nowrap; }
  .interest-status.pending{ background:rgba(154,52,18,.2); color:#e8a486; border:1px solid rgba(154,52,18,.5); }
  .interest-status.sent{ background:rgba(74,93,35,.25); color:#c3d99b; border:1px solid rgba(74,93,35,.6); }
  .interest-table a{ color: var(--gold-soft); }
</style>
</head>
<body>
<div class="admin-shell">
  <div class="admin-header">
    <div class="admin-header-brand">
      <img src="/img/logo.png" alt="">
      <div>
        <strong>Interesse em estoque</strong>
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
    <a href="/admin/tags.php">Tags</a>
    <a href="/admin/interesse.php" class="active">Interesse</a>
    <a href="/admin/cartao.php">Cartão de visita</a>
    <a href="/admin/paginas.php">Páginas</a>
  </div>

  <p class="view-subtitle">Quem pediu aviso quando um título "sem estoque" voltar — e-mail, WhatsApp e, se informado, aniversário pra campanha.</p>

  <div class="interest-filters">
    <label>Livro
      <select id="filterVolume"><option value="">Todos</option></select>
    </label>
    <label>Campanha
      <select id="filterCampaign">
        <option value="">Todos</option>
        <option value="pending">Pendente</option>
        <option value="sent">Já enviada</option>
      </select>
    </label>
    <button type="button" class="btn btn-ghost" id="exportCsvBtn">Exportar CSV</button>
  </div>

  <div class="interest-bulkbar">
    <label class="checkbox-label" style="flex-direction:row;align-items:center;gap:.5em;">
      <input type="checkbox" id="selectAllCheckbox"> Selecionar tudo
    </label>
    <button type="button" class="btn btn-ghost" id="markSentBtn">Marcar selecionados como enviado</button>
    <button type="button" class="btn btn-ghost" id="markPendingBtn">Marcar selecionados como pendente</button>
    <span id="bulkStatus" class="modal-status"></span>
  </div>

  <div style="overflow-x:auto;">
    <table class="interest-table">
      <thead>
        <tr>
          <th></th>
          <th>Livro</th>
          <th>Contato</th>
          <th>Aniversário</th>
          <th>Pedido em</th>
          <th>Campanha</th>
        </tr>
      </thead>
      <tbody id="interestBody"></tbody>
    </table>
    <p class="empty-list-hint" id="interestEmpty" hidden>Nenhum registro de interesse ainda.</p>
  </div>
</div>

<script>
window.BA_CSRF = <?= json_encode($csrf) ?>;
let INTEREST = [];

function esc(str){
  return String(str ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
}
function formatDateBR(iso){
  if(!iso) return '';
  const d = iso.split(/[ T]/)[0];
  const parts = d.split('-');
  return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : iso;
}
async function postJSON(url, data){
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    body: JSON.stringify({ ...data, csrf: window.BA_CSRF })
  });
  let json = {};
  try { json = await res.json(); } catch(e) {}
  if(!res.ok) throw new Error(json.error || ('Erro ' + res.status));
  return json;
}

async function loadInterest(){
  const res = await fetch('/api/admin/stock-interest-list.php', { credentials: 'same-origin' });
  INTEREST = await res.json();
  populateVolumeFilter();
  renderTable();
}

function populateVolumeFilter(){
  const sel = document.getElementById('filterVolume');
  const seen = new Map();
  INTEREST.forEach(i => seen.set(i.volumeId, i.volumeLabel));
  const current = sel.value;
  sel.innerHTML = '<option value="">Todos</option>' +
    Array.from(seen.entries()).map(([id, label]) => `<option value="${esc(id)}">${esc(label)}</option>`).join('');
  sel.value = current;
}

function getFiltered(){
  const volumeFilter = document.getElementById('filterVolume').value;
  const campaignFilter = document.getElementById('filterCampaign').value;
  return INTEREST.filter(i => {
    if(volumeFilter && i.volumeId !== volumeFilter) return false;
    if(campaignFilter === 'pending' && i.campaignSent) return false;
    if(campaignFilter === 'sent' && !i.campaignSent) return false;
    return true;
  });
}

function renderTable(){
  const filtered = getFiltered();
  const tbody = document.getElementById('interestBody');
  const empty = document.getElementById('interestEmpty');
  if(!filtered.length){
    tbody.innerHTML = '';
    empty.hidden = false;
    return;
  }
  empty.hidden = true;
  tbody.innerHTML = filtered.map(i => `
    <tr class="${i.campaignSent ? 'sent' : ''}" data-id="${i.id}">
      <td><input type="checkbox" class="row-checkbox" value="${i.id}"></td>
      <td>${esc(i.volumeLabel)}</td>
      <td>
        ${esc(i.email)}<br>
        <a href="https://wa.me/${esc(i.whatsapp.replace(/\D/g,''))}" target="_blank" rel="noopener">${esc(i.whatsapp)}</a>
      </td>
      <td>${i.birthday ? formatDateBR(i.birthday) : '—'}</td>
      <td>${formatDateBR(i.createdAt)}</td>
      <td><span class="interest-status ${i.campaignSent ? 'sent' : 'pending'}">${i.campaignSent ? 'Enviada' : 'Pendente'}</span></td>
    </tr>
  `).join('');
  document.getElementById('selectAllCheckbox').checked = false;
}

document.getElementById('filterVolume').addEventListener('change', renderTable);
document.getElementById('filterCampaign').addEventListener('change', renderTable);

document.getElementById('selectAllCheckbox').addEventListener('change', (e) => {
  document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = e.target.checked);
});

function getSelectedIds(){
  return Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => parseInt(cb.value, 10));
}

async function bulkMark(sent){
  const ids = getSelectedIds();
  const status = document.getElementById('bulkStatus');
  if(!ids.length){ status.textContent = 'Selecione ao menos um registro.'; return; }
  status.textContent = 'Salvando...';
  try {
    await postJSON('/api/admin/mark-campaign-sent.php', { ids, sent });
    await loadInterest();
    status.textContent = 'Atualizado ✓';
    setTimeout(() => status.textContent = '', 2000);
  } catch (err){
    status.textContent = 'Erro: ' + err.message;
  }
}
document.getElementById('markSentBtn').addEventListener('click', () => bulkMark(true));
document.getElementById('markPendingBtn').addEventListener('click', () => bulkMark(false));

document.getElementById('exportCsvBtn').addEventListener('click', () => {
  const filtered = getFiltered();
  const header = ['Livro', 'E-mail', 'WhatsApp', 'Aniversário', 'Pedido em', 'Campanha enviada'];
  const lines = [header.join(';')];
  filtered.forEach(i => {
    lines.push([i.volumeLabel, i.email, i.whatsapp, i.birthday || '', i.createdAt, i.campaignSent ? 'sim' : 'não']
      .map(v => `"${String(v).replace(/"/g, '""')}"`).join(';'));
  });
  const blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'interesse-estoque.csv';
  a.click();
  URL.revokeObjectURL(url);
});

loadInterest();
</script>
</body>
</html>
