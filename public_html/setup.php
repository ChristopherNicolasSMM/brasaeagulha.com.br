<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

$pdo = ba_db();
$adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
$alreadyDone = $adminCount > 0;

$error = null;
$success = null;

if (!$alreadyDone && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['confirm'] ?? '');

    if ($username === '' || strlen($username) < 3) {
        $error = 'Escolha um usuário com pelo menos 3 caracteres.';
    } elseif (strlen($password) < 10) {
        $error = 'A senha precisa ter pelo menos 10 caracteres.';
    } elseif ($password !== $confirm) {
        $error = 'A confirmação de senha não confere.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
        $stmt->execute([$username, $hash]);
        $alreadyDone = true;
        $success = 'Administrador criado com sucesso! Você já pode entrar em <a href="/admin/login.php">/admin/login.php</a>. Por segurança, apague este arquivo (setup.php) do servidor agora.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Configuração inicial · Brasa &amp; Agulha</title>
<link rel="icon" href="/img/favicon-32.png">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Crimson+Text:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/site.css">
<style>
  body{ display:flex; align-items:center; justify-content:center; min-height:100vh; padding:1.5rem; }
  .setup-box{ width:100%; max-width:420px; background:rgba(255,255,255,.04); border:1px solid rgba(212,175,55,.25); border-radius:14px; padding:2rem; }
  .setup-box img{ width:64px; height:64px; border-radius:50%; display:block; margin:0 auto 1rem; }
  .setup-box h1{ font-size:1.25rem; text-align:center; }
  .setup-note{ color: var(--snow-muted); font-size:.92rem; text-align:center; margin-bottom:1.4em; }
  .setup-error{ background:rgba(154,52,18,.15); border:1px solid rgba(154,52,18,.4); color:#f5efe3; padding:.7em 1em; border-radius:8px; font-size:.9rem; margin-bottom:1em; }
  .setup-success{ background:rgba(74,93,35,.2); border:1px solid rgba(74,93,35,.6); color:#f5efe3; padding:.9em 1em; border-radius:8px; font-size:.92rem; }
  .setup-box label{ display:flex; flex-direction:column; gap:.4em; margin-bottom:1em; font-family:var(--font-ui); font-size:.9rem; color:var(--snow-muted); }
  .setup-box input{ font-family:var(--font-body); font-size:1rem; color:var(--snow); background:rgba(255,255,255,.05); border:1px solid rgba(212,175,55,.3); border-radius:8px; padding:.6em .8em; }
</style>
</head>
<body>
  <div class="setup-box">
    <img src="/img/logo.png" alt="">
    <h1>Configuração inicial</h1>
    <?php if ($alreadyDone): ?>
      <?php if ($success): ?>
        <p class="setup-success"><?= $success ?></p>
      <?php else: ?>
        <p class="setup-note">A configuração já foi concluída anteriormente. Se precisar trocar a senha, use a página <a href="/admin/change-password.php">Trocar senha</a> depois de entrar, ou <a href="/admin/login.php">entre no painel</a>.</p>
      <?php endif; ?>
    <?php else: ?>
      <p class="setup-note">Esta página só funciona uma vez. Escolha o usuário e a senha do painel administrativo.</p>
      <?php if ($error): ?><p class="setup-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
      <form method="post" novalidate>
        <label>Usuário <input type="text" name="username" required autofocus minlength="3"></label>
        <label>Senha <input type="password" name="password" required minlength="10"></label>
        <label>Confirmar senha <input type="password" name="confirm" required minlength="10"></label>
        <button type="submit" class="btn btn-primary" style="width:100%">Criar administrador</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>
