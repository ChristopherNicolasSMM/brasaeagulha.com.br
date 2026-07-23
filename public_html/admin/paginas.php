<?php
declare(strict_types=1);
require __DIR__ . '/../../config.php';
ba_start_session();
ba_require_admin_page();

$username = $_SESSION['admin_username'] ?? 'admin';
$settings = ba_get_settings();
$siteUrl = rtrim($settings['site_url'] ?? '', '/');

$pdo = ba_db();
$authors = $pdo->query('SELECT id, name FROM authors ORDER BY sort_order, name')->fetchAll();

function full(string $siteUrl, string $path): string
{
    return $siteUrl !== '' ? $siteUrl . $path : $path;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Páginas · Painel administrativo · Brasa &amp; Agulha</title>
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
  .link-group{ margin-bottom:1.8rem; }
  .link-group h2{ font-size:1rem; margin-bottom:.7em; }
  .link-row{
    display:flex; align-items:center; gap:1em; justify-content:space-between;
    background:rgba(255,255,255,.03); border:1px solid rgba(212,175,55,.18); border-radius:8px;
    padding:.7em 1em; margin-bottom:.5em; font-family:var(--font-ui); font-size:.9rem;
  }
  .link-row-info strong{ display:block; }
  .link-row-info code{ color:var(--snow-muted); font-size:.82rem; }
  .link-row .btn{ padding:.4em .9em; font-size:.8rem; white-space:nowrap; }
</style>
</head>
<body>
<div class="admin-shell">
  <div class="admin-header">
    <div class="admin-header-brand">
      <img src="/img/logo.png" alt="">
      <div>
        <strong>Páginas</strong>
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
    <a href="/admin/cartao.php">Cartão de visita</a>
    <a href="/admin/paginas.php" class="active">Páginas</a>
  </div>

  <p class="view-subtitle">Links que não aparecem em nenhum menu do site — úteis para colar num QR Code, numa bio de rede social ou compartilhar direto. Clique em "Copiar" para pegar o endereço completo.</p>

  <div class="link-group">
    <h2>Públicas, fora do menu principal</h2>
    <div class="link-row" data-url="<?= htmlspecialchars(full($siteUrl, '/cartao'), ENT_QUOTES, 'UTF-8') ?>">
      <div class="link-row-info"><strong>Cartão de visita digital</strong><code>/cartao</code></div>
      <button type="button" class="btn btn-ghost" data-action="copy-link">Copiar</button>
    </div>
    <div class="link-row" data-url="<?= htmlspecialchars(full($siteUrl, '/vcard'), ENT_QUOTES, 'UTF-8') ?>">
      <div class="link-row-info"><strong>vCard (salvar contato)</strong><code>/vcard</code></div>
      <button type="button" class="btn btn-ghost" data-action="copy-link">Copiar</button>
    </div>
    <?php foreach ($authors as $a): ?>
    <div class="link-row" data-url="<?= htmlspecialchars(full($siteUrl, '/autor/' . $a['id']), ENT_QUOTES, 'UTF-8') ?>">
      <div class="link-row-info"><strong>Página do autor — <?= htmlspecialchars($a['name'], ENT_QUOTES, 'UTF-8') ?></strong><code>/autor/<?= htmlspecialchars($a['id'], ENT_QUOTES, 'UTF-8') ?></code></div>
      <button type="button" class="btn btn-ghost" data-action="copy-link">Copiar</button>
    </div>
    <?php endforeach; ?>
    <?php if (!$authors): ?>
      <p class="empty-list-hint">Nenhum autor cadastrado ainda — as páginas de autor aparecem aqui automaticamente.</p>
    <?php endif; ?>
  </div>

  <div class="link-group">
    <h2>Administrativas (exigem login)</h2>
    <?php
    $adminPages = [
        ['/admin/', 'Catálogo (livros e coleções)'],
        ['/admin/autores.php', 'Autores'],
        ['/admin/cartao.php', 'Configurações do cartão de visita'],
        ['/admin/paginas.php', 'Esta página'],
        ['/admin/change-password.php', 'Trocar senha'],
    ];
    foreach ($adminPages as [$path, $label]):
    ?>
    <div class="link-row" data-url="<?= htmlspecialchars(full($siteUrl, $path), ENT_QUOTES, 'UTF-8') ?>">
      <div class="link-row-info"><strong><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></strong><code><?= htmlspecialchars($path, ENT_QUOTES, 'UTF-8') ?></code></div>
      <button type="button" class="btn btn-ghost" data-action="copy-link">Copiar</button>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
document.querySelectorAll('[data-action="copy-link"]').forEach(btn => {
  btn.addEventListener('click', async () => {
    const url = btn.closest('.link-row').dataset.url;
    try {
      await navigator.clipboard.writeText(url);
      const original = btn.textContent;
      btn.textContent = 'Copiado ✓';
      setTimeout(() => { btn.textContent = original; }, 1800);
    } catch (e) {
      alert(url);
    }
  });
});
</script>
</body>
</html>
