<?php
declare(strict_types=1);

/**
 * Inicia a sessão com configurações de cookie mais seguras:
 * - HttpOnly: JavaScript não consegue ler o cookie de sessão.
 * - SameSite=Lax: reduz risco de CSRF vindo de outros sites.
 * - Secure: exige HTTPS para o cookie ser enviado, quando disponível.
 */
function ba_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(BA_SESSION_NAME);
    ini_set('session.use_strict_mode', '1');
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $isHttps,
    ]);
    session_start();
}

function ba_is_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

/** Usado no topo de páginas HTML do admin: redireciona para o login se preciso. */
function ba_require_admin_page(): void
{
    if (!ba_is_logged_in()) {
        header('Location: /admin/login.php');
        exit;
    }
}

/** Usado no topo de endpoints JSON do admin: responde 401 se preciso. */
function ba_require_admin_api(): void
{
    if (!ba_is_logged_in()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Não autenticado.']);
        exit;
    }
}

function ba_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Retorna quantos minutos faltam de bloqueio para o IP atual, ou null
 * se ele não estiver bloqueado.
 */
function ba_login_is_locked(PDO $pdo): ?int
{
    $stmt = $pdo->prepare('SELECT locked_until FROM login_attempts WHERE ip = ?');
    $stmt->execute([ba_client_ip()]);
    $row = $stmt->fetch();
    if (!$row || empty($row['locked_until'])) {
        return null;
    }
    $lockedUntil = strtotime($row['locked_until']);
    if ($lockedUntil > time()) {
        return (int) ceil(($lockedUntil - time()) / 60);
    }
    return null;
}

function ba_login_register_failure(PDO $pdo): void
{
    $ip = ba_client_ip();
    $stmt = $pdo->prepare('SELECT attempts FROM login_attempts WHERE ip = ?');
    $stmt->execute([$ip]);
    $row = $stmt->fetch();
    $attempts = (int) ($row['attempts'] ?? 0) + 1;
    $lockedUntil = null;
    if ($attempts >= BA_LOGIN_MAX_ATTEMPTS) {
        $lockedUntil = date('Y-m-d H:i:s', time() + BA_LOGIN_LOCKOUT_MINUTES * 60);
    }
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (ip, attempts, locked_until) VALUES (?, ?, ?)
         ON CONFLICT(ip) DO UPDATE SET attempts = excluded.attempts, locked_until = excluded.locked_until'
    );
    $stmt->execute([$ip, $attempts, $lockedUntil]);
}

function ba_login_register_success(PDO $pdo): void
{
    $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?');
    $stmt->execute([ba_client_ip()]);
}
