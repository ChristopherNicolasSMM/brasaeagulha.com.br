<?php
declare(strict_types=1);

/**
 * Gera (ou reaproveita) um token CSRF para a sessão atual.
 * Deve ser chamado depois de ba_start_session().
 */
function ba_csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Confere se o token recebido bate com o da sessão, usando comparação
 * resistente a timing attack (hash_equals).
 */
function ba_csrf_verify(?string $token): bool
{
    return !empty($_SESSION['csrf']) && !empty($token) && hash_equals($_SESSION['csrf'], $token);
}
