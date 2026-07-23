<?php
declare(strict_types=1);

/**
 * ============================================================
 *  config.php — ARQUIVO DE CONFIGURAÇÃO
 * ============================================================
 *  Este arquivo NÃO deve ficar dentro de public_html.
 *  No servidor, ele fica UM NÍVEL ACIMA da pasta pública, ao lado
 *  dela — nunca acessível por URL.
 *
 *  Estrutura esperada no servidor:
 *
 *    /home/seu-usuario/dominios/seusite.com/
 *    ├── config.php            <- este arquivo
 *    ├── catalogo.sqlite         <- criado automaticamente no 1º acesso
 *    └── public_html/            <- conteúdo da pasta public_html/ deste pacote
 *
 *  Por que o banco SQLite também fica aqui fora: diferente do MySQL
 *  (onde os dados ficam num servidor de banco separado, não alcançável
 *  por HTTP), o SQLite é um arquivo único. Se ele estivesse dentro de
 *  public_html, qualquer pessoa poderia baixá-lo direto pela URL e abrir
 *  offline — expondo todo o catálogo e o hash da senha do admin. Por
 *  isso ele mora aqui, ao lado deste arquivo.
 * ============================================================
 */

// Caminho do banco de dados SQLite (ao lado deste arquivo, fora do public_html)
define('BA_DB_PATH', __DIR__ . '/catalogo.sqlite');

// Nome do cookie de sessão do painel administrativo
define('BA_SESSION_NAME', 'brasa_agulha_admin');

// Após quantas tentativas de login erradas (por IP) bloquear temporariamente
define('BA_LOGIN_MAX_ATTEMPTS', 5);

// Duração do bloqueio, em minutos
define('BA_LOGIN_LOCKOUT_MINUTES', 15);

require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/csrf.php';
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/images.php';
