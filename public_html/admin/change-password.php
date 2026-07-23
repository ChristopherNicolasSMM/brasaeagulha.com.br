<?php
declare(strict_types=1);
require __DIR__ . '/../../config.php';
ba_start_session();
ba_require_admin_page();

$pdo = ba_db();
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ba_csrf_verify($_POST['csrf'] ?? null)) {
        $error = 'Sua sessão expirou. Recarregue a página e tente novamente.';
    } else {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
        $stmt->execute([$_SESSION['admin_id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($current, $row['password_hash'])) {
            $error = 'Senha atual incorreta.';
        } elseif (strlen($new) < 10) {
            $error = 'A nova senha precisa ter pelo menos 10 caracteres.';
        } elseif ($new !== $confirm) {
            $error = 'A confirmação não confere com a nova senha.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
            $upd->execute([$hash, $_SESSION['admin_id']]);
            $success = 'Senha atualizada com sucesso.';
        }
    }
}

$csrf = ba_csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Trocar senha · Painel Administrativo</title>
<link rel="icon" href="/img/favicon-32.png">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Crimson+Text:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/site.css">
<style>
  body{ display:flex; align-items:center; justify-content:center; min-height:100vh; padding:1.5rem; }
  .login-box{ width:100%; max-width:380px; background:rgba(255,255,255,.04); border:1px solid rgba(212,175,55,.25); border-radius:14px; padding:2rem; }
  .login-box h1{ font-size:1.2rem; text-align:center; }
  .login-error{ background:rgba(154,52,18,.15); border:1px solid rgba(154,52,18,.4); color:#f5efe3; padding:.7em 1em; border-radius:8px; font-size:.9rem; margin-bottom:1em; }
  .login-success{ background:rgba(74,93,35,.2); border:1px solid rgba(74,93,35,.6); color:#f5efe3; padding:.7em 1em; border-radius:8px; font-size:.9rem; margin-bottom:1em; }
  .login-box label{ display:flex; flex-direction:column; gap:.4em; margin-bottom:1em; font-family:var(--font-ui); font-size:.9rem; color:var(--snow-muted); }
  .login-box input{ font-family:var(--font-body); font-size:1rem; color:var(--snow); background:rgba(255,255,255,.05); border:1px solid rgba(212,175,55,.3); border-radius:8px; padding:.6em .8em; }
  .back{ display:block; text-align:center; margin-top:1em; font-family:var(--font-ui); font-size:.85rem; }
</style>
</head>
<body>
  <div class="login-box">
    <h1>Trocar senha</h1>
    <?php if ($error): ?><p class="login-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($success): ?><p class="login-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post" novalidate>
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <label>Senha atual <input type="password" name="current_password" autocomplete="current-password" required></label>
      <label>Nova senha <input type="password" name="new_password" autocomplete="new-password" required minlength="10"></label>
      <label>Confirmar nova senha <input type="password" name="confirm_password" autocomplete="new-password" required minlength="10"></label>
      <button type="submit" class="btn btn-primary" style="width:100%">Salvar nova senha</button>
    </form>
    <a class="back" href="/admin/">← Voltar ao painel</a>
  </div>
</body>
</html>
