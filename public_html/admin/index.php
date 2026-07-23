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
<title>Painel administrativo · Brasa &amp; Agulha</title>
<link rel="icon" href="/img/favicon-32.png">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700&family=Crimson+Text:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/site.css">
<style>
  body{ padding: 0; }
  .admin-shell{ max-width: 1040px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
  .admin-header{ display:flex; align-items:center; justify-content:space-between; gap:1em; flex-wrap:wrap; margin-bottom:1.8rem; }
  .admin-header-brand{ display:flex; align-items:center; gap:.8em; }
  .admin-header-brand img{ width:46px; height:46px; border-radius:50%; }
  .admin-header-brand strong{ font-family:var(--font-display); font-size:1.1rem; }
  .admin-header-brand span{ display:block; font-family:var(--font-ui); font-size:.8rem; color:var(--snow-muted); }
  .admin-header-actions{ display:flex; gap:.7em; flex-wrap:wrap; }
  .admin-tabs{ display:flex; gap:.6em; margin-bottom:1.8rem; flex-wrap:wrap; }
  .admin-tabs a{ font-family:var(--font-ui); font-size:.88rem; padding:.5em 1em; border-radius:999px; border:1px solid rgba(212,175,55,.3); color:var(--snow-muted); }
  .admin-tabs a.active{ background:var(--gold-ember); color:#14212b; border-color:var(--gold-ember); font-weight:600; }
</style>
</head>
<body>
<div class="admin-shell">
  <div class="admin-header">
    <div class="admin-header-brand">
      <img src="/img/logo.png" alt="">
      <div>
        <strong>Painel administrativo</strong>
        <span>Conectado como <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </div>
    <div class="admin-header-actions">
      <a class="btn btn-ghost" href="/">Ver site</a>
      <a class="btn btn-ghost" href="/admin/change-password.php">Trocar senha</a>
      <a class="btn btn-ghost" href="/admin/logout.php">Sair</a>
    </div>
  </div>

  <div class="admin-tabs">
    <a href="/admin/" class="active">Catálogo</a>
    <a href="/admin/autores.php">Autores</a>
    <a href="/admin/tags.php">Tags</a>
    <a href="/admin/interesse.php">Interesse</a>
    <a href="/admin/cartao.php">Cartão de visita</a>
    <a href="/admin/paginas.php">Páginas</a>
  </div>

  <p class="view-subtitle">Ajuste preços, promoções e o acervo. As alterações são salvas direto no banco de dados — não é preciso exportar nada para elas valerem para todo mundo que visita o site.</p>

  <div class="admin-toolbar">
    <button id="exportBtn" class="btn btn-ghost" type="button">Exportar catálogo atual (.json)</button>
    <label class="btn btn-ghost file-btn">
      Importar configuração (.json)
      <input type="file" id="importInput" accept="application/json" hidden>
    </label>
  </div>

  <label class="admin-search-field">
    <input type="search" id="volumeSearch" placeholder="Buscar por título, ISBN, autor ou tema…">
  </label>

  <div id="adminCollections" class="admin-collections"></div>

  <div class="section-divider" aria-hidden="true">✵</div>

  <div class="admin-new-collection">
    <h2>Adicionar nova coleção</h2>
    <form id="newCollectionForm" class="inline-form">
      <label>Título
        <input type="text" name="title" required>
      </label>
      <label>Tipo
        <select name="type" id="newCollectionType">
          <option value="livro">Livro</option>
          <option value="monografia">Monografia</option>
          <option value="liturgico">Litúrgico</option>
          <option value="apostila">Apostila</option>
        </select>
      </label>
      <label>Cor de destaque
        <input type="color" name="accentColor" id="newCollectionColor" value="#d4af37">
      </label>
      <label>Descrição breve
        <input type="text" name="description">
      </label>
      <button type="submit" class="btn btn-primary">Adicionar coleção</button>
    </form>
  </div>
</div>

<dialog id="volumeModal" class="admin-modal">
  <form id="volumeForm" method="dialog">
    <div class="admin-modal-header">
      <h2 id="volumeModalTitle">Novo volume</h2>
      <button type="button" class="admin-modal-close" data-action="close-volume-modal" aria-label="Fechar">×</button>
    </div>
    <div class="admin-modal-body">
      <input type="hidden" name="id" value="">

      <fieldset class="modal-fieldset">
        <legend>Identificação</legend>
        <div class="admin-field-row">
          <label>Coleção
            <select name="collectionId" required></select>
          </label>
          <label>Autor
            <select name="authorId"></select>
          </label>
        </div>
        <div class="admin-field-row">
          <label>Rótulo do volume <input type="text" name="volumeLabel" placeholder="Volume IV" required></label>
          <label>Subtítulo <input type="text" name="subtitle" required></label>
        </div>
      </fieldset>

      <fieldset class="modal-fieldset">
        <legend>Descrição e temas</legend>
        <label style="display:flex;flex-direction:column;gap:.35em;margin-bottom:1em;">Descrição
          <textarea name="description" rows="3"></textarea>
        </label>
        <label style="display:flex;flex-direction:column;gap:.35em;">Tags
          <div class="tag-input" id="tagInput">
            <input type="text" class="tag-input-field" id="tagInputField" placeholder="Digite e pressione Enter…" autocomplete="off">
          </div>
          <input type="hidden" name="tags" id="tagsHidden" value="">
        </label>
      </fieldset>

      <fieldset class="modal-fieldset">
        <legend>Preço e promoção</legend>
        <div class="admin-field-row">
          <label>Preço (R$) <input type="number" step="0.01" min="0" name="price" required></label>
          <label class="checkbox-label"><input type="checkbox" name="promoActive"> Promoção ativa</label>
        </div>
        <div class="admin-field-row">
          <label>Tipo de desconto
            <select name="promoType">
              <option value="percent">% desconto</option>
              <option value="fixed">R$ desconto</option>
            </select>
          </label>
          <label>Valor <input type="number" step="0.01" min="0" name="promoValue" value="0"></label>
          <label>Rótulo da promoção <input type="text" name="promoLabel"></label>
        </div>
        <div class="admin-field-row">
          <label>Início <input type="date" name="promoStartDate"></label>
          <label>Fim <input type="date" name="promoEndDate"></label>
        </div>
      </fieldset>

      <fieldset class="modal-fieldset">
        <legend>Disponibilidade</legend>
        <div class="admin-field-row">
          <label>Status
            <select name="availability">
              <option value="available">Disponível</option>
              <option value="coming_soon">Em breve</option>
              <option value="out_of_stock">Sem estoque</option>
            </select>
          </label>
          <p class="modal-status" id="notifyCountInfo" style="align-self:flex-end;"></p>
        </div>
      </fieldset>

      <fieldset class="modal-fieldset">
        <legend>Metadados</legend>
        <div class="admin-field-row">
          <label>ISBN <input type="text" name="isbn"></label>
          <label>Idioma <input type="text" name="language" value="Português (Brasil)"></label>
        </div>
        <div class="admin-field-row">
          <label>Páginas <input type="number" min="0" step="1" name="pageCount"></label>
          <label>Data de publicação <input type="date" name="publicationDate"></label>
        </div>
      </fieldset>

      <fieldset class="modal-fieldset" id="imagesFieldset" hidden>
        <legend>Imagens</legend>
        <div id="volumeImagesGrid" class="volume-images-grid"></div>
        <label class="btn btn-ghost file-btn" style="margin-top:.8em;">
          + Adicionar imagem
          <input type="file" id="imageUploadInput" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
        </label>
        <p class="modal-status" id="imageUploadStatus"></p>
      </fieldset>
    </div>
    <div class="admin-modal-footer">
      <button type="button" class="btn btn-danger" id="deleteVolumeBtn" hidden>Excluir volume</button>
      <div style="display:flex;gap:.7em;align-items:center;margin-left:auto;">
        <span id="volumeModalStatus" class="modal-status"></span>
        <button type="submit" class="btn btn-primary" id="saveVolumeBtn">Salvar</button>
      </div>
    </div>
  </form>
</dialog>

<dialog id="collectionModal" class="admin-modal">
  <form id="collectionEditForm" method="dialog">
    <div class="admin-modal-header">
      <h2>Editar coleção</h2>
      <button type="button" class="admin-modal-close" data-action="close-collection-modal" aria-label="Fechar">×</button>
    </div>
    <div class="admin-modal-body">
      <input type="hidden" name="id" value="">
      <div class="admin-field-row">
        <label style="flex-basis:100%">Título <input type="text" name="title" required></label>
      </div>
      <div class="admin-field-row">
        <label>Tipo
          <select name="type">
            <option value="livro">Livro</option>
            <option value="monografia">Monografia</option>
            <option value="liturgico">Litúrgico</option>
            <option value="apostila">Apostila</option>
          </select>
        </label>
        <label>Cor de destaque <input type="color" name="accentColor"></label>
      </div>
      <div class="admin-field-row">
        <label style="flex-basis:100%">Descrição <textarea name="description" rows="2"></textarea></label>
      </div>
      <div class="admin-field-row">
        <label class="checkbox-label"><input type="checkbox" name="active" checked> Ativa (some do site e do admin de novos volumes se desmarcada — os volumes que já tem continuam existindo)</label>
      </div>
    </div>
    <div class="admin-modal-footer">
      <span id="collectionModalStatus" class="modal-status"></span>
      <button type="submit" class="btn btn-primary" style="margin-left:auto;">Salvar</button>
    </div>
  </form>
</dialog>

<script>window.BA_CSRF = <?= json_encode($csrf) ?>;</script>
<script src="/js/common.js"></script>
<script src="/js/admin.js"></script>
</body>
</html>
