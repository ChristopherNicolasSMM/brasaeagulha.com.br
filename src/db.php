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
}

/**
 * Semeia os dois autores já conhecidos do projeto e liga os volumes das
 * coleções correspondentes a eles. Roda só UMA VEZ (controlado pelo
 * marcador de migração) — depois disso, é 100% edição sua: se você
 * apagar um desses autores ou trocar o autor de um volume, isso nunca
 * mais é desfeito automaticamente.
 */
function ba_ensure_authors(PDO $pdo): void
{
    if (ba_migration_done($pdo, 'authors_seed_v1')) {
        return;
    }

    $authors = [
        [
            'id' => 'kunnigr-afi',
            'name' => 'Kunnigr Afi (o Avô Sábio)',
            'bio' => 'Persona narrativa da Brasa & Agulha Editorial — a voz que conta, ao pé do fogo, as histórias reunidas em "As Histórias que os Ventos Trazem". Não é um pesquisador creditado, mas o veículo escolhido para transmitir mitologia e saberes nórdicos em tom oral e caloroso.',
            'photo_url' => '',
        ],
        [
            'id' => 'christopher-mauricio',
            'name' => 'Christopher N. S. M. Mauricio',
            'bio' => 'Pesquisador independente e Editor Responsável pela Brasa & Agulha Editorial. Assina as obras de caráter técnico e ritual da linha editorial, sempre distinguindo fonte primária, interpretação acadêmica de terceiros e elaboração autoral própria.',
            'photo_url' => '',
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
        'historias-ventos' => 'kunnigr-afi',
        'compendio-futhark' => 'christopher-mauricio',
        'breviario-nordico' => 'christopher-mauricio',
    ];

    $update = $pdo->prepare('UPDATE volumes SET author_id = ? WHERE collection_id = ? AND (author_id IS NULL OR author_id = \'\')');
    foreach ($collectionAuthorMap as $collectionId => $authorId) {
        $update->execute([$authorId, $collectionId]);
    }

    ba_mark_migration_done($pdo, 'authors_seed_v1');
}

/** Gera um slug simples (minúsculas, sem acento, hífens) a partir de um texto. */
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

function ba_seed_catalog(PDO $pdo): void
{
    $collections = [
        [
            'id' => 'historias-ventos',
            'title' => 'As Histórias que os Ventos Trazem',
            'type' => 'livro',
            'description' => 'Mitologia nórdica recontada à luz do fogo, através da voz de Kunnigr Afi — o Avô Sábio — para quem já sabe ouvir e para quem ainda está aprendendo.',
            'accent_color' => '#d4af37',
            'volumes' => [
                [
                    'id' => 'hv-v1', 'volume_label' => 'Volume I', 'subtitle' => 'As Sementes do Início',
                    'description' => 'Da escuridão de Ginnungagap ao nascimento das Nove Terras — as primeiras histórias que o Avô conta junto ao fogo.',
                    'price' => 79.90,
                    'promo_active' => 1, 'promo_type' => 'percent', 'promo_value' => 15, 'promo_label' => 'Lançamento',
                    'promo_start_date' => '2026-06-01', 'promo_end_date' => '2026-09-01',
                    'tags' => ['cosmogonia', 'Yggdrasil', 'Óðinn'],
                ],
                [
                    'id' => 'hv-v2', 'volume_label' => 'Volume II', 'subtitle' => 'Deuses, Gigantes e Guerreiros',
                    'description' => 'As sagas de Þórr, Loki e os salões de Ásgarðr — coragem, humor e as fissuras que antecipam o fim.',
                    'price' => 79.90,
                    'promo_active' => 0, 'promo_type' => 'percent', 'promo_value' => 0, 'promo_label' => '',
                    'promo_start_date' => '', 'promo_end_date' => '',
                    'tags' => ['Þórr', 'Loki', 'drengskapr'],
                ],
                [
                    'id' => 'hv-v3', 'volume_label' => 'Volume III', 'subtitle' => 'Ragnarök e o Que Vem Depois',
                    'description' => 'O crepúsculo dos deuses e o que os ventos ainda trazem depois da última batalha.',
                    'price' => 84.90,
                    'promo_active' => 0, 'promo_type' => 'percent', 'promo_value' => 0, 'promo_label' => '',
                    'promo_start_date' => '', 'promo_end_date' => '',
                    'tags' => ['Ragnarök', 'profecia'],
                ],
            ],
        ],
        [
            'id' => 'compendio-futhark',
            'title' => 'Compêndio do Futhark Antigo',
            'type' => 'monografia',
            'description' => 'Estudo do Futhark Antigo — as 24 runas, seus significados, correspondências e contexto histórico, com rigor conceitual e linguagem acessível.',
            'accent_color' => '#a29c8f',
            'volumes' => [
                [
                    'id' => 'cf-v1', 'volume_label' => 'Volume I', 'subtitle' => 'A Ætt de Freyr',
                    'description' => 'As primeiras oito runas do Futhark Antigo — origem, fonema e significado simbólico de cada uma.',
                    'price' => 69.90,
                    'promo_active' => 1, 'promo_type' => 'fixed', 'promo_value' => 10, 'promo_label' => 'Semana da Editora',
                    'promo_start_date' => '2026-07-15', 'promo_end_date' => '2026-07-31',
                    'tags' => ['runas', 'Fehu', 'Futhark'],
                ],
                [
                    'id' => 'cf-v2', 'volume_label' => 'Volume II', 'subtitle' => 'A Ætt de Hagal',
                    'description' => 'Da ruptura à renovação: as runas do meio do Futhark e seu papel nas fontes históricas.',
                    'price' => 69.90,
                    'promo_active' => 0, 'promo_type' => 'percent', 'promo_value' => 0, 'promo_label' => '',
                    'promo_start_date' => '', 'promo_end_date' => '',
                    'tags' => ['runas', 'Futhark'],
                ],
                [
                    'id' => 'cf-apendice', 'volume_label' => 'Apêndice', 'subtitle' => 'Tabela Comparada de Pronúncia',
                    'description' => 'Nórdico Antigo × Islandês Moderno — referência de consulta rápida para as 24 runas.',
                    'price' => 29.90,
                    'promo_active' => 0, 'promo_type' => 'percent', 'promo_value' => 0, 'promo_label' => '',
                    'promo_start_date' => '', 'promo_end_date' => '',
                    'tags' => ['referência', 'pronúncia'],
                ],
            ],
        ],
        [
            'id' => 'breviario-nordico',
            'title' => 'Breviário Nórdico',
            'type' => 'liturgico',
            'description' => 'Liturgias de abertura, encerramento e orientação para a consulta das runas — um breviário para uso pessoal, contemplativo e reverente.',
            'accent_color' => '#4d7ea8',
            'volumes' => [
                [
                    'id' => 'bn-v1', 'volume_label' => 'Volume único', 'subtitle' => 'Ritos de Abertura e Encerramento',
                    'description' => 'A Porta do Vé, a preparação do espaço e as fórmulas de fechamento — a estrutura completa de um rito pessoal.',
                    'price' => 59.90,
                    'promo_active' => 0, 'promo_type' => 'percent', 'promo_value' => 0, 'promo_label' => '',
                    'promo_start_date' => '', 'promo_end_date' => '',
                    'tags' => ['ritual', 'Vé'],
                ],
                [
                    'id' => 'bn-v2', 'volume_label' => 'Volume II', 'subtitle' => 'Consulta das Runas',
                    'description' => 'Orientações para a leitura ritual — preparação, lançamento e interpretação, sem promessas, com responsabilidade.',
                    'price' => 64.90,
                    'promo_active' => 1, 'promo_type' => 'percent', 'promo_value' => 10, 'promo_label' => 'Lançamento',
                    'promo_start_date' => '2026-07-01', 'promo_end_date' => '2026-08-15',
                    'tags' => ['runas', 'Nornir'],
                ],
            ],
        ],
    ];

    $insCol = $pdo->prepare('INSERT INTO collections (id, title, type, description, accent_color, sort_order) VALUES (?,?,?,?,?,?)');
    $insVol = $pdo->prepare('INSERT INTO volumes (id, collection_id, volume_label, subtitle, description, price, promo_active, promo_type, promo_value, promo_label, promo_start_date, promo_end_date, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $insTag = $pdo->prepare('INSERT INTO volume_tags (volume_id, tag) VALUES (?, ?)');

    foreach ($collections as $ci => $col) {
        $insCol->execute([$col['id'], $col['title'], $col['type'], $col['description'], $col['accent_color'], $ci]);
        foreach ($col['volumes'] as $vi => $vol) {
            $insVol->execute([
                $vol['id'], $col['id'], $vol['volume_label'], $vol['subtitle'], $vol['description'],
                $vol['price'], $vol['promo_active'], $vol['promo_type'], $vol['promo_value'],
                $vol['promo_label'], $vol['promo_start_date'], $vol['promo_end_date'], $vi,
            ]);
            foreach ($vol['tags'] as $tag) {
                $insTag->execute([$vol['id'], $tag]);
            }
        }
    }
}
