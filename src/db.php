<?php
declare(strict_types=1);

/**
 * Retorna a conexão PDO com o banco SQLite, criando o esquema e os
 * dados iniciais do catálogo automaticamente na primeira vez que o
 * arquivo de banco não existir. Isso significa que não é preciso rodar
 * nenhum "instalador" separado para o catálogo — basta subir os arquivos.
 *
 * A conta de administrador NÃO é criada aqui de propósito: ela só é
 * criada quando você mesmo visita /setup.php e escolhe usuário/senha.
 * Assim nenhuma senha passa por nenhum lugar além do seu navegador.
 */
function ba_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $pdo = new PDO('sqlite:' . BA_DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    // Roda em TODA requisição, não só quando o banco é novo — cada CREATE usa
    // IF NOT EXISTS, então é seguro repetir. Isso permite adicionar tabelas
    // novas em fases futuras sem exigir banco zerado nem apagar dados já
    // cadastrados (livros, clientes, pedidos etc. continuam intactos).
    ba_ensure_schema($pdo);
    ba_ensure_seed($pdo);

    return $pdo;
}

/**
 * Marcadores de "isso já rodou uma vez" — usados para ações que não podem
 * ser repetidas em toda requisição (diferente de CREATE TABLE IF NOT EXISTS,
 * que é seguro repetir). Sem isso, por exemplo, apagar um autor ou trocar
 * o autor de um volume seria desfeito automaticamente na requisição seguinte.
 */
function ba_migration_done(PDO $pdo, string $key): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM site_settings WHERE key = ?');
    $stmt->execute(['_migration_' . $key]);
    return (bool) $stmt->fetch();
}

function ba_mark_migration_done(PDO $pdo, string $key): void
{
    $stmt = $pdo->prepare('INSERT INTO site_settings (key, value) VALUES (?, \'1\') ON CONFLICT(key) DO NOTHING');
    $stmt->execute(['_migration_' . $key]);
}

function ba_ensure_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS collections (
        id TEXT PRIMARY KEY,
        title TEXT NOT NULL,
        type TEXT NOT NULL,
        description TEXT DEFAULT \'\',
        accent_color TEXT DEFAULT \'#d4af37\',
        sort_order INTEGER DEFAULT 0
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS volumes (
        id TEXT PRIMARY KEY,
        collection_id TEXT NOT NULL REFERENCES collections(id) ON DELETE CASCADE,
        volume_label TEXT DEFAULT \'\',
        subtitle TEXT DEFAULT \'\',
        description TEXT DEFAULT \'\',
        price REAL NOT NULL DEFAULT 0,
        promo_active INTEGER DEFAULT 0,
        promo_type TEXT DEFAULT \'percent\',
        promo_value REAL DEFAULT 0,
        promo_label TEXT DEFAULT \'\',
        promo_start_date TEXT DEFAULT \'\',
        promo_end_date TEXT DEFAULT \'\',
        sort_order INTEGER DEFAULT 0
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS volume_tags (
        volume_id TEXT NOT NULL REFERENCES volumes(id) ON DELETE CASCADE,
        tag TEXT NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (
        ip TEXT PRIMARY KEY,
        attempts INTEGER DEFAULT 0,
        locked_until TEXT
    )');

    // Fase 1 — dados do cartão de visita / vCard, editáveis pelo admin
    $pdo->exec('CREATE TABLE IF NOT EXISTS site_settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT \'\'
    )');

    // Fase 2 — autores, com página pública própria
    $pdo->exec('CREATE TABLE IF NOT EXISTS authors (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        bio TEXT DEFAULT \'\',
        photo_url TEXT DEFAULT \'\',
        sort_order INTEGER DEFAULT 0
    )');

    // Fase 4 — registro de tags (permite CRUD/inativação central, em vez de
    // string solta). volume_tags.tag continua guardando o texto (mantém
    // compatibilidade com todo o código que já lê vol.tags como strings);
    // tag_id é a referência "de verdade" pra edição/inativação em um só lugar.
    $pdo->exec('CREATE TABLE IF NOT EXISTS tags (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        active INTEGER DEFAULT 1,
        sort_order INTEGER DEFAULT 0
    )');
    ba_ensure_column($pdo, 'volume_tags', 'tag_id', "TEXT REFERENCES tags(id) ON DELETE SET NULL");

    // Fase 4 — coleções e volumes podem ser inativados sem apagar
    // (inativação em vez de exclusão — preserva histórico/dados ligados).
    ba_ensure_column($pdo, 'collections', 'active', "INTEGER DEFAULT 1");

    // Fase 4 — disponibilidade do volume e interesse em "avise-me"
    ba_ensure_column($pdo, 'volumes', 'availability', "TEXT DEFAULT 'available'");
    $pdo->exec('CREATE TABLE IF NOT EXISTS stock_interest (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        volume_id TEXT NOT NULL REFERENCES volumes(id) ON DELETE CASCADE,
        email TEXT NOT NULL,
        whatsapp TEXT DEFAULT \'\',
        birthday TEXT DEFAULT \'\',
        campaign_sent INTEGER DEFAULT 0,
        campaign_sent_at TEXT DEFAULT \'\',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(volume_id, email)
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_stock_interest_volume ON stock_interest(volume_id)');

    // Colunas novas em volumes (SQLite não tem "ADD COLUMN IF NOT EXISTS"
    // nativo, por isso o helper confere antes de tentar adicionar).
    ba_ensure_column($pdo, 'volumes', 'isbn', "TEXT DEFAULT ''");
    ba_ensure_column($pdo, 'volumes', 'language', "TEXT DEFAULT 'Português (Brasil)'");
    ba_ensure_column($pdo, 'volumes', 'page_count', "INTEGER");
    ba_ensure_column($pdo, 'volumes', 'publication_date', "TEXT DEFAULT ''");
    ba_ensure_column($pdo, 'volumes', 'author_id', "TEXT REFERENCES authors(id) ON DELETE SET NULL");

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_volumes_collection ON volumes(collection_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_volume_tags_volume ON volume_tags(volume_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_volumes_author ON volumes(author_id)');
}

/**
 * Adiciona uma coluna a uma tabela só se ela ainda não existir — torna
 * ALTER TABLE seguro para rodar em toda requisição, em bancos novos e
 * em bancos que já têm dados reais de produção.
 */
function ba_ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $existing = $pdo->query("PRAGMA table_info({$table})")->fetchAll();
    foreach ($existing as $col) {
        if ($col['name'] === $column) {
            return;
        }
    }
    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
}

/**
 * Chaves e valores padrão do cartão de visita. Só grava a chave se ela
 * ainda não existir — assim, editar pelo admin nunca é sobrescrito por
 * este seed em requisições futuras.
 */
function ba_ensure_seed(PDO $pdo): void
{
    $collectionCount = (int) $pdo->query('SELECT COUNT(*) FROM collections')->fetchColumn();
    if ($collectionCount === 0) {
        require_once __DIR__ . '/seed-data.php';
        ba_seed_catalog($pdo);
    }

    $defaults = [
        'org_name' => 'Brasa & Agulha Editorial',
        'editor_name' => 'Christopher N. S. M. Mauricio',
        'editor_title' => 'Editor Responsável',
        'note' => 'Editora especializada em obras de espiritualidade, cultura, filosofia, pesquisa e edições de alta qualidade.',
        'phone' => '+5516981509474',
        'email' => 'contato@brasaeagulha.com.br',
        'address_line' => 'Caixa Postal, Ribeirão Preto, SP, 14030-780, Brasil',
        'site_url' => 'https://brasaeagulha.com.br',
        'photo_url' => 'https://brasaeagulha.com.br/img/logo.png',
        'logo_url' => 'https://brasaeagulha.com.br/img/logo.png',
        'whatsapp_number' => '5516981509474',
        'whatsapp_message' => 'Olá! Vim pelo cartão digital da Brasa & Agulha e gostaria de falar com vocês.',
        'pix_key' => '',
        'pix_key_type' => 'telefone',
        'instagram_url' => '',
        'youtube_url' => '',
        'location_maps_url' => '',
        'catalogo_url' => '/#catalogo',
        'loja_url' => '/#catalogo',
        'runomante_label' => 'O Runomante',
        'runomante_url' => '',
        'site_oficial_url' => '/',
    ];

    $check = $pdo->prepare('SELECT 1 FROM site_settings WHERE key = ?');
    $insert = $pdo->prepare('INSERT INTO site_settings (key, value) VALUES (?, ?)');
    foreach ($defaults as $key => $value) {
        $check->execute([$key]);
        if (!$check->fetch()) {
            $insert->execute([$key, $value]);
        }
    }

    ba_ensure_authors($pdo);
    ba_ensure_tags_migration($pdo);
}

/**
 * Semeia o autor e liga os volumes das coleções correspondentes a ele.
 * Roda só UMA VEZ (controlado pelo marcador de migração) — depois disso,
 * é 100% edição sua: se você trocar o autor de um volume, isso nunca mais
 * é desfeito automaticamente.
 */
function ba_ensure_authors(PDO $pdo): void
{
    if (ba_migration_done($pdo, 'authors_seed_v1')) {
        return;
    }

    $authors = [
        [
            'id' => 'christopher-n-s-m-mauricio',
            'name' => 'Christopher N. S. M. Mauricio',
            'bio' => 'Pesquisador independente e Editor Responsável pela Brasa & Agulha Editorial. Assina as obras de caráter técnico e ritual da linha editorial, sempre distinguindo fonte primária, interpretação acadêmica de terceiros e elaboração autoral própria.',
            'photo_url' => '/autores/christopher-n-s-m-mauricio.png',
        ],
    ];

    $checkAuthor = $pdo->prepare('SELECT 1 FROM authors WHERE id = ?');
    $insertAuthor = $pdo->prepare('INSERT INTO authors (id, name, bio, photo_url, sort_order) VALUES (?, ?, ?, ?, ?)');
    foreach ($authors as $i => $a) {
        $checkAuthor->execute([$a['id']]);
        if (!$checkAuthor->fetch()) {
            $insertAuthor->execute([$a['id'], $a['name'], $a['bio'], $a['photo_url'], $i]);
        }
    }

    // Mapa coleção -> autor padrão, só usado pra preencher volumes que
    // ainda não têm author_id (NULL ou vazio) nesta migração única.
    $collectionAuthorMap = [
        'historias-ventos'   => 'christopher-n-s-m-mauricio',
        'compendio-futhark'  => 'christopher-n-s-m-mauricio',
        'breviario-nordico'  => 'christopher-n-s-m-mauricio',
        'linguagem-da-chama' => 'christopher-n-s-m-mauricio',
    ];

    $update = $pdo->prepare('UPDATE volumes SET author_id = ? WHERE collection_id = ? AND (author_id IS NULL OR author_id = \'\')');
    foreach ($collectionAuthorMap as $collectionId => $authorId) {
        $update->execute([$authorId, $collectionId]);
    }

    ba_mark_migration_done($pdo, 'authors_seed_v1');
}

/** Gera um slug simples (minúsculas, sem acento, hífens) a partir de um texto. */
/**
 * Migração única: registra na tabela `tags` toda tag que já existia em
 * `volume_tags` como texto solto, e liga `volume_tags.tag_id` a ela.
 */
function ba_ensure_tags_migration(PDO $pdo): void
{
    if (ba_migration_done($pdo, 'tags_registry_v1')) {
        return;
    }

    $distinct = $pdo->query('SELECT DISTINCT tag FROM volume_tags WHERE tag_id IS NULL')->fetchAll();
    foreach ($distinct as $row) {
        $tagId = ba_find_or_create_tag($pdo, $row['tag']);
        $upd = $pdo->prepare('UPDATE volume_tags SET tag_id = ? WHERE tag = ? AND tag_id IS NULL');
        $upd->execute([$tagId, $row['tag']]);
    }

    ba_mark_migration_done($pdo, 'tags_registry_v1');
}

/** Acha o id de uma tag pelo nome (sem diferenciar maiúsculas), criando se não existir. */
function ba_find_or_create_tag(PDO $pdo, string $name): string
{
    $name = trim($name);
    $find = $pdo->prepare('SELECT id FROM tags WHERE LOWER(name) = LOWER(?)');
    $find->execute([$name]);
    $existing = $find->fetch();
    if ($existing) {
        return $existing['id'];
    }

    $id = ba_slugify($name);
    $orig = $id;
    $n = 2;
    $check = $pdo->prepare('SELECT 1 FROM tags WHERE id = ?');
    while (true) {
        $check->execute([$id]);
        if (!$check->fetch()) {
            break;
        }
        $id = $orig . '-' . $n;
        $n++;
    }

    $countStmt = $pdo->query('SELECT COUNT(*) FROM tags');
    $sortOrder = (int) $countStmt->fetchColumn();
    $ins = $pdo->prepare('INSERT INTO tags (id, name, active, sort_order) VALUES (?, ?, 1, ?)');
    $ins->execute([$id, $name, $sortOrder]);

    return $id;
}

function ba_slugify(string $str): string
{
    $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $str);
    $str = $transliterated !== false ? $transliterated : $str;
    $str = strtolower($str);
    $str = preg_replace('/[^a-z0-9]+/', '-', $str) ?? '';
    $str = trim($str, '-');
    return $str !== '' ? $str : ('item-' . time());
}

/** Retorna todas as configurações do cartão como um mapa chave => valor. */
function ba_get_settings(): array
{
    $pdo = ba_db();
    $rows = $pdo->query('SELECT key, value FROM site_settings')->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $out[$row['key']] = $row['value'];
    }
    return $out;
}

function ba_get_setting(string $key, string $default = ''): string
{
    $all = ba_get_settings();
    return $all[$key] ?? $default;
}
