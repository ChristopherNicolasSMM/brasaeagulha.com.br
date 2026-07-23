<?php
declare(strict_types=1);
require __DIR__ . '/../../config.php';
ba_start_session();

$pdo = ba_db();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $locked = ba_login_is_locked($pdo);
    if ($locked !== null) {
        $error = "Muitas tentativas incorretas. Tente novamente em {$locked} minuto(s).";
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $stmt = $pdo->prepare('SELECT id, password_hash FROM admin_users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            ba_login_register_success($pdo);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $username;
            header('Location: /admin/');
            exit;
        }

        ba_login_register_failure($pdo);
        $error = 'Usuário ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Entrar — Painel Administrativo · Brasa &amp; Agulha</title>
<link rel="icon" href="/img/favicon-32.png">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Crimson+Text:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/site.css">
<style>
  body{ display:flex; align-items:center; justify-content:center; min-height:100vh; padding:1.5rem; }
  .login-box{ width:100%; max-width:380px; background:rgba(255,255,255,.04); border:1px solid rgba(212,175,55,.25); border-radius:14px; padding:2rem; }
  .login-box img{ width:64px; height:64px; border-radius:50%; display:block; margin:0 auto 1rem; }
  .login-box h1{ font-size:1.3rem; text-align:center; }
  .login-error{ background:rgba(154,52,18,.15); border:1px solid rgba(154,52,18,.4); color:#f5efe3; padding:.7em 1em; border-radius:8px; font-size:.9rem; margin-bottom:1em; }
  .login-box label{ display:flex; flex-direction:column; gap:.4em; margin-bottom:1em; font-family:var(--font-ui); font-size:.9rem; color:var(--snow-muted); }
  .login-box input{ font-family:var(--font-body); font-size:1rem; color:var(--snow); background:rgba(255,255,255,.05); border:1px solid rgba(212,175,55,.3); border-radius:8px; padding:.6em .8em; }
</style>
</head>
<body>
  <div class="login-box">
    <img src="/img/logo.png" alt="">
    <h1>Painel administrativo</h1>
    <?php if ($error): ?><p class="login-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post" novalidate>
      <label>Usuário <input type="text" name="username" autocomplete="username" required autofocus></label>
      <label>Senha <input type="password" name="password" autocomplete="current-password" required></label>
      <button type="submit" class="btn btn-primary" style="width:100%">Entrar</button>
    </form>
  </div>
</body>
</html>
