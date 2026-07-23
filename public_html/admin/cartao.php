<?php
declare(strict_types=1);
require __DIR__ . '/../../config.php';
ba_start_session();
ba_require_admin_page();

$csrf = ba_csrf_token();
$username = $_SESSION['admin_username'] ?? 'admin';
$settings = ba_get_settings();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Cartão de visita · Painel administrativo · Brasa &amp; Agulha</title>
<link rel="icon" href="/img/favicon-32.png">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Crimson+Text:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/site.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
  .settings-form{ display:flex; flex-direction:column; gap:1.4rem; }
  .settings-group{ background:rgba(255,255,255,.03); border:1px solid rgba(212,175,55,.2); border-radius:var(--radius-lg); padding:1.2rem 1.3rem; }
  .settings-group h2{ font-size:1.05rem; margin-bottom:1em; }
  .settings-field{ display:flex; flex-direction:column; gap:.35em; margin-bottom:1em; }
  .settings-field:last-child{ margin-bottom:0; }
  .settings-field label{ font-family:var(--font-ui); font-size:.82rem; color:var(--snow-muted); }
  .settings-field input, .settings-field textarea{
    font-family:var(--font-body); font-size:.98rem; color:var(--snow);
    background:rgba(255,255,255,.05); border:1px solid rgba(212,175,55,.28);
    border-radius:8px; padding:.55em .75em;
  }
  .settings-hint{ font-size:.78rem; color:var(--snow-muted); }
  .save-bar{ position:sticky; bottom:0; background:rgba(18,40,58,.92); backdrop-filter:blur(6px); padding:1em 0; display:flex; align-items:center; gap:1em; }
  #saveStatus{ font-family:var(--font-ui); font-size:.85rem; color:var(--snow-muted); }
  .qr-box{ display:flex; align-items:center; gap:1.4em; flex-wrap:wrap; }
  #qrcode{ background:#fff; padding:10px; border-radius:8px; line-height:0; }
</style>
</head>
<body>
<div class="admin-shell">
  <div class="admin-header">
    <div class="admin-header-brand">
      <img src="/img/logo.png" alt="">
      <div>
        <strong>Cartão de visita</strong>
        <span>Conectado como <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </div>
    <div class="admin-header-actions">
      <a class="btn btn-ghost" href="/cartao">Ver cartão</a>
      <a class="btn btn-ghost" href="/admin/logout.php">Sair</a>
    </div>
  </div>

  <div class="admin-tabs">
    <a href="/admin/">Catálogo</a>
    <a href="/admin/autores.php">Autores</a>
    <a href="/admin/cartao.php" class="active">Cartão de visita</a>
    <a href="/admin/paginas.php">Páginas</a>
  </div>

  <p class="view-subtitle">Estes dados alimentam a página <code>/cartao</code> e o vCard dinâmico (<code>/vcard</code>). Mudou o telefone ou o Instagram? Muda só aqui.</p>

  <form class="settings-form" id="settingsForm">

    <div class="settings-group">
      <h2>Identidade</h2>
      <div class="settings-field"><label>Nome da editora (aparece como nome do contato)</label><input type="text" name="org_name" value="<?= htmlspecialchars($settings['org_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="settings-field"><label>Nome do responsável</label><input type="text" name="editor_name" value="<?= htmlspecialchars($settings['editor_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="settings-field"><label>Cargo do responsável</label><input type="text" name="editor_title" value="<?= htmlspecialchars($settings['editor_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="settings-field"><label>Nota / descrição curta</label><textarea name="note" rows="2"><?= htmlspecialchars($settings['note'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></div>
    </div>

    <div class="settings-group">
      <h2>Contato</h2>
      <div class="settings-field"><label>Telefone (com DDI, ex: +5516981509474)</label><input type="text" name="phone" value="<?= htmlspecialchars($settings['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="settings-field"><label>E-mail</label><input type="email" name="email" value="<?= htmlspecialchars($settings['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="settings-field"><label>Endereço (linha única)</label><input type="text" name="address_line" value="<?= htmlspecialchars($settings['address_line'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="settings-field"><label>Site (URL completa)</label><input type="url" name="site_url" value="<?= htmlspecialchars($settings['site_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="settings-field"><label>Foto (URL — usada no PHOTO do vCard)</label><input type="url" name="photo_url" value="<?= htmlspecialchars($settings['photo_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><span class="settings-hint">Sem foto pessoal ainda? Deixe apontando pra logo por enquanto.</span></div>
      <div class="settings-field"><label>Logo (URL — usada no LOGO do vCard)</label><input type="url" name="logo_url" value="<?= htmlspecialchars($settings['logo_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
    </div>

    <div class="settings-group">
      <h2>WhatsApp</h2>
      <div class="settings-field"><label>Número (só dígitos, com DDI e DDD, ex: 5516981509474)</label><input type="text" name="whatsapp_number" value="<?= htmlspecialchars($settings['whatsapp_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="settings-field"><label>Mensagem inicial sugerida</label><textarea name="whatsapp_message" rows="2"><?= htmlspecialchars($settings['whatsapp_message'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></div>
    </div>

    <div class="settings-group">
      <h2>PIX</h2>
      <div class="settings-field"><label>Chave PIX</label><input type="text" name="pix_key" value="<?= htmlspecialchars($settings['pix_key'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="settings-field">
        <label>Tipo de chave</label>
        <select name="pix_key_type" style="font-family:var(--font-body);font-size:.98rem;color:var(--snow);background:rgba(255,255,255,.05);border:1px solid rgba(212,175,55,.28);border-radius:8px;padding:.55em .75em;">
          <?php foreach (['telefone'=>'Telefone','email'=>'E-mail','cpf'=>'CPF','cnpj'=>'CNPJ','aleatoria'=>'Chave aleatória'] as $val=>$label): ?>
            <option value="<?= $val ?>" <?= ($settings['pix_key_type'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="settings-group">
      <h2>Links do cartão</h2>
      <div class="settings-field"><label>Instagram (URL)</label><input type="url" name="instagram_url" value="<?= htmlspecialchars($settings['instagram_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="settings-field"><label>YouTube (URL)</label><input type="url" name="youtube_url" value="<?= htmlspecialchars($settings['youtube_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="settings-field"><label>Localização (link do Google Maps)</label><input type="url" name="location_maps_url" value="<?= htmlspecialchars($settings['location_maps_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="settings-field"><label>Catálogo (URL)</label><input type="text" name="catalogo_url" value="<?= htmlspecialchars($settings['catalogo_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="settings-field"><label>Loja (URL — por enquanto pode apontar pro catálogo)</label><input type="text" name="loja_url" value="<?= htmlspecialchars($settings['loja_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="settings-field"><label>Rótulo do botão especial</label><input type="text" name="runomante_label" value="<?= htmlspecialchars($settings['runomante_label'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
      <div class="settings-field"><label>URL do botão especial</label><input type="text" name="runomante_url" value="<?= htmlspecialchars($settings['runomante_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><span class="settings-hint">Ainda sem destino definido — o botão só aparece no cartão quando este campo tiver uma URL.</span></div>
      <div class="settings-field"><label>Site oficial (URL)</label><input type="text" name="site_oficial_url" value="<?= htmlspecialchars($settings['site_oficial_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
    </div>

    <div class="save-bar">
      <button type="submit" class="btn btn-primary">Salvar alterações</button>
      <span id="saveStatus"></span>
    </div>
  </form>

  <div class="section-divider" aria-hidden="true">✵</div>

  <div class="settings-group">
    <h2>QR Code para impressão</h2>
    <div class="qr-box">
      <div id="qrcode"></div>
      <div>
        <p class="settings-hint">Gerado direto no navegador, aponta para <code><?= htmlspecialchars(rtrim($settings['site_url'] ?? '', '/'), ENT_QUOTES, 'UTF-8') ?>/cartao</code>.</p>
        <button type="button" class="btn btn-ghost" id="downloadQr">Baixar PNG</button>
      </div>
    </div>
  </div>
</div>

<script>
window.BA_CSRF = <?= json_encode($csrf) ?>;

document.getElementById('settingsForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const status = document.getElementById('saveStatus');
  const fd = new FormData(e.target);
  const values = {};
  for (const [key, val] of fd.entries()) values[key] = val;

  status.textContent = 'Salvando...';
  try {
    const res = await fetch('/api/admin/update-settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ values, csrf: window.BA_CSRF })
    });
    const json = await res.json();
    if (!res.ok) throw new Error(json.error || ('Erro ' + res.status));
    status.textContent = 'Salvo ✓';
    setTimeout(() => { status.textContent = ''; }, 3000);
  } catch (err) {
    status.textContent = 'Erro: ' + err.message;
  }
});

const qrTarget = <?= json_encode(rtrim($settings['site_url'] ?? '', '/') . '/cartao') ?>;
new QRCode(document.getElementById('qrcode'), {
  text: qrTarget,
  width: 180,
  height: 180,
  colorDark: '#12283a',
  colorLight: '#ffffff'
});

document.getElementById('downloadQr').addEventListener('click', () => {
  const canvas = document.querySelector('#qrcode canvas');
  if (!canvas) return;
  const a = document.createElement('a');
  a.href = canvas.toDataURL('image/png');
  a.download = 'brasa-agulha-qrcode.png';
  a.click();
});
</script>
</body>
</html>
