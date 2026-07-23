<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$pdo = ba_db();

$author = null;
$books = [];

if ($slug !== '') {
    $stmt = $pdo->prepare('SELECT * FROM authors WHERE id = ?');
    $stmt->execute([$slug]);
    $author = $stmt->fetch();

    if ($author) {
        $stmt = $pdo->prepare(
            'SELECT v.*, c.title AS collection_title, c.type AS collection_type, c.accent_color
             FROM volumes v JOIN collections c ON c.id = v.collection_id
             WHERE v.author_id = ?
             ORDER BY c.sort_order, v.sort_order'
        );
        $stmt->execute([$slug]);
        $books = $stmt->fetchAll();
    }
}

$typeLabels = ['livro' => 'Livro', 'monografia' => 'Monografia', 'liturgico' => 'Litúrgico'];
$typeSigils = ['livro' => 'ᛟ', 'monografia' => 'ᚠ', 'liturgico' => 'ᚨ'];

function h(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#12283a">
<title><?= $author ? h($author['name']) . ' — ' : '' ?>Brasa &amp; Agulha Editorial</title>
<link rel="icon" type="image/png" sizes="32x32" href="/img/favicon-32.png">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Crimson+Text:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/site.css">
<style>
  body{ padding: 0; }
  .author-shell{ max-width: 760px; margin: 0 auto; padding: 2.5rem 1.3rem 4rem; }
  .author-header{ display:flex; gap:1.4rem; align-items:center; margin-bottom:1.6rem; flex-wrap:wrap; }
  .author-photo{ width:110px; height:110px; border-radius:50%; object-fit:cover; box-shadow:0 0 0 3px rgba(212,175,55,.35); }
  .author-photo-fallback{ width:110px; height:110px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:linear-gradient(155deg,#d4af37,#12283a); font-family:var(--font-display); font-size:2.4rem; color:#fff; box-shadow:0 0 0 3px rgba(212,175,55,.35); }
  .author-name{ font-size:1.6rem; margin-bottom:.2em; }
  .author-books{ margin-top:2rem; display:flex; flex-direction:column; gap:.9rem; }
  .author-book{ display:flex; gap:1em; align-items:center; background:rgba(255,255,255,.03); border:1px solid rgba(212,175,55,.2); border-radius:var(--radius); padding:.9em 1.1em; }
  .author-book .sigil{ font-family:var(--font-display); font-size:1.4rem; color:var(--gold-ember); width:1.4em; text-align:center; }
  .author-book .meta{ font-family:var(--font-ui); font-size:.78rem; color:var(--snow-muted); }
  .not-found{ text-align:center; padding:3rem 1rem; color:var(--snow-muted); }
</style>
</head>
<body>
<div class="author-shell">
  <p><a href="/" style="font-family:var(--font-ui);font-size:.85rem;">← Voltar ao site</a></p>

  <?php if (!$author): ?>
    <div class="not-found">
      <h1>Autor não encontrado</h1>
      <p>Não encontramos essa página de autor. <a href="/#catalogo">Ver o catálogo completo</a>.</p>
    </div>
  <?php else: ?>
    <div class="author-header">
      <?php if (!empty($author['photo_url'])): ?>
        <img class="author-photo" src="<?= h($author['photo_url']) ?>" alt="<?= h($author['name']) ?>">
      <?php else: ?>
        <div class="author-photo-fallback" aria-hidden="true"><?= h(mb_substr($author['name'], 0, 1)) ?></div>
      <?php endif; ?>
      <div>
        <h1 class="author-name"><?= h($author['name']) ?></h1>
        <p class="view-subtitle" style="margin:0;"><?= count($books) ?> obra<?= count($books) === 1 ? '' : 's' ?> no acervo</p>
      </div>
    </div>

    <?php if (!empty($author['bio'])): ?>
      <p class="book-detail-desc"><?= nl2br(h($author['bio'])) ?></p>
    <?php endif; ?>

    <div class="section-divider" aria-hidden="true">✵</div>

    <div class="author-books">
      <?php foreach ($books as $b): ?>
        <a class="author-book" href="/#livro-<?= h($b['id']) ?>">
          <span class="sigil" aria-hidden="true"><?= $typeSigils[$b['collection_type']] ?? 'ᛟ' ?></span>
          <span>
            <strong><?= h($b['subtitle'] ?: $b['volume_label']) ?></strong><br>
            <span class="meta"><?= h($b['collection_title']) ?> · <?= h($b['volume_label']) ?> · <?= h($typeLabels[$b['collection_type']] ?? $b['collection_type']) ?></span>
          </span>
        </a>
      <?php endforeach; ?>
      <?php if (!$books): ?>
        <p class="form-hint">Nenhuma obra vinculada a este autor ainda.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
