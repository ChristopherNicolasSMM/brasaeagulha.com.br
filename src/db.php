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

/**
 * Dados iniciais do catálogo — atualizados a partir dos manuscritos reais
 * (não mais placeholders): "As Histórias que os Ventos Trazem" tem 4
 * volumes confirmados pela ficha catalográfica de cada um; "Compêndio do
 * Futhark Antigo" é hoje uma obra única e completa (não mais dividida em
 * partes), com ISBN já definido; "A Linguagem da Chama" é título novo,
 * ainda em revisão (sem ISBN/data de publicação por enquanto).
 */
function ba_seed_catalog(PDO $pdo): void
{
    $collections = [
        [
            'id' => 'compendio-futhark',
            'title' => 'Compêndio do Futhark Antigo',
            'type' => 'livro',
            'description' => 'Das origens cósmicas à práxis oracular e mágica — estudo completo do Futhark Antigo pelos três Ætts (Freyr e Freyja, Heimdallr, Týr), com rigor conceitual e aplicação prática, incluindo o sistema autoral de Galdr e vibroturgia do destino.',
            'accent_color' => '#ffe23f',
            'volumes' => [
                [
                    'id' => 'cf-v1', 
                    'volume_label' => 'Edição completa', 
                    'subtitle' => 'Das Origens Cósmicas à Práxis Oracular e Mágica',
                    'description' => 'Os três Ætts do Futhark Antigo — de Freyr e Freyja à soberania de Týr —, o Galdr como arquitetura do som primordial, a arte oracular e a ética do Wyrd, e a prática mágica com runas. Traz tabelas de referência rápida, glossário norrænt-português, glossário conceitual rúnico e índice remissivo.',
                    'price' => 142.90,
                    'promo_active' => 1, 
                    'promo_type' => 'fixed', 
                    'promo_value' => 10, 
                    'promo_label' => 'Lançamento',
                    'promo_start_date' => '2026-07-15', 
                    'promo_end_date' => '2026-09-01',
                    'isbn' => '978-65-01-94561-3', 
                    'publication_date' => '2026-01-01',
                    'tags' => ['Futhark', 'runas', 'Galdr', 'Wyrd', 'oráculo'],
                ],
            ],
        ],        
        [
            'id' => 'historias-ventos',
            'title' => 'As Histórias que os Ventos Trazem',
            'type' => 'livro',
            'description' => 'Mitos e saberes da antiga tradição nórdica, recontados à luz do fogo, através da voz de Kunnigr Afi — o Avô Sábio — para quem já sabe ouvir e para quem ainda está aprendendo. Coleção em 4 volumes.',
            'accent_color' => '#8f7624',
            'volumes' => [
                [
                    'id' => 'hv-v1', 
                    'volume_label' => 'Volume I', 
                    'subtitle' => 'Das Origens ao Tear do Destino',
                    'description' => 'Do fogo e do gelo ao nascimento das Nove Terras: Ymir, Auðhumbla, a árvore Yggdrasil e o surgimento dos primeiros humanos, Ask e Embla. Encerra com as Nornas e o Tear do Destino — Wyrd, Örlög, Hamingja e o que essas ideias significam de verdade.',
                    'price' => 39.87,
                    'promo_active' => 1, 
                    'promo_type' => 'fixed', 
                    'promo_value' => 5,90, 
                    'promo_label' => 'Lançamento',
                    'promo_start_date' => '2026-06-01', 
                    'promo_end_date' => '2026-09-01',
                    'isbn' => '', 
                    'publication_date' => '2026-01-01',
                    'tags' => ['cosmogonia', 'Yggdrasil', 'Nornir', 'Wyrd', 'Örlög'],
                ],
                [
                    'id' => 'hv-v2', 
                    'volume_label' => 'Volume II', 
                    'subtitle' => 'Os Deuses da Ordem',
                    'description' => 'Óðinn, o Pai de Todos; Þórr, o Defensor; Týr, o Sacrificado; Baldr, o Brilhante; e os demais Æsir — encerrando com a Guerra Æsir-Vanir e a grande troca que redesenhou o panteão nórdico.',
                    'price' => 99.90,
                    'promo_active' => 0, 
                    'promo_type' => 'percent', 
                    'promo_value' => 0, 
                    'promo_label' => '',
                    'promo_start_date' => '', 
                    'promo_end_date' => '',
                    'isbn' => '', 
                    'publication_date' => '',
                    'tags' => ['Óðinn', 'Þórr', 'Týr', 'Baldr', 'Æsir'],
                ],
                [
                    'id' => 'hv-v3', 
                    'volume_label' => 'Volume III', 
                    'subtitle' => 'Criaturas, Heróis e Sagas',
                    'description' => 'Freyja e Freyr, Njörðr e os ventos do mar, Loki o Cambiante, os gigantes, os anões artífices, elfos e espíritos da terra — e a saga de Sigurðr, o Matador de Dragões, entre outras histórias de coragem.',
                    'price' => 99.90,
                    'promo_active' => 0, 
                    'promo_type' => 'percent', 
                    'promo_value' => 0, 
                    'promo_label' => '',
                    'promo_start_date' => '', 
                    'promo_end_date' => '',
                    'isbn' => '', 
                    'publication_date' => '',
                    'tags' => ['Freyja', 'Loki', 'Sigurðr', 'gigantes', 'anões'],
                ],
                [
                    'id' => 'hv-v4', 
                    'volume_label' => 'Volume IV', 
                    'subtitle' => 'Sabedoria, Magia e o Fogo Eterno',
                    'description' => 'As runas como alfabeto dos sussurros, magia e práticas espirituais, os ritos do ano sagrado e as palavras do Hávamál — encerrando com Wyrd aplicado à vida hoje. Traz também glossário, guia de pronúncia, linha do tempo mítica e mapa dos Nove Mundos.',
                    'price' => 99.90,
                    'promo_active' => 0, 
                    'promo_type' => 'percent', 
                    'promo_value' => 0, 
                    'promo_label' => '',
                    'promo_start_date' => '', 
                    'promo_end_date' => '',
                    'isbn' => '', 
                    'publication_date' => '',
                    'tags' => ['runas', 'Hávamál', 'magia', 'rituais'],
                ],
            ],
        ],
        [
            'id' => 'breviario-nordico',
            'title' => 'Breviário Nórdico',
            'type' => 'liturgico',
            'description' => 'Liturgias de abertura, encerramento e orientação para a consulta das runas — um breviário para uso pessoal, contemplativo e reverente.',
            'accent_color' => '#4d91ce',
            'volumes' => [
                [
                    'id' => 'bn-v1',
                    'volume_label' => 'Volume único', 
                    'subtitle' => 'Ritos de Abertura e Encerramento',
                    'description' => 'A Porta do Vé, a preparação do espaço e as fórmulas de fechamento — a estrutura completa de um rito pessoal.',
                    'price' => 19.90,
                    'promo_active' => 0, 
                    'promo_type' => 'percent', 
                    'promo_value' => 0, 
                    'promo_label' => '',
                    'promo_start_date' => '', 
                    'promo_end_date' => '',
                    'isbn' => '', 
                    'publication_date' => '',
                    'tags' => ['ritual', 'Vé', 'runas', 'magia', 'oração']
                ]
            ],
        ],
        [
            'id' => 'linguagem-da-chama',
            'title' => 'A Linguagem da Chama',
            'type' => 'livro',
            'description' => 'Fazer, consagrar e manter aceso — um manual prático de ofício do fogo: lamparinas, velas, incensos e ervas, com segurança e critério do início ao fim. Ainda em revisão.',
            'accent_color' => '#9a3412',
            'volumes' => [
                [
                    'id' => 'ldc-v1', 
                    'volume_label' => 'Volume único', 
                    'subtitle' => 'Fazer, Consagrar e Manter Aceso',
                    'description' => 'Da chama votiva às lamparinas, velas e incensos: princípios, construção segura, combustíveis e manutenção, diagnóstico pela chama, ervas e resinas com limites claros — encerrando com encerramento e descarte consciente. Inclui apêndice de herbologia do artífice.',
                    'price' => 69.90,
                    'promo_active' => 0, 
                    'promo_type' => 'percent',
                    'promo_value' => 0,
                    'promo_label' => '',
                    'promo_start_date' => '',
                    'promo_end_date' => '',
                    'isbn' => '', 
                    'publication_date' => '',
                    'tags' => ['fogo ritual', 'velas', 'incenso', 'lamparinas'],
                ],
            ],
        ],


        [
            'id' => 'sessao-monografias',
            'title' => 'Palestras em Monografias',
            'type' => 'monografia',
            'description' => 'Uma série de monografias impressas e em PDF, cada uma com uma palestra completa sobre um tema específico, incluindo xamanismo, cultura ( afro, indigena, brasileira ) como umbanda, kimbanda, candomble, mitologia, magia e ética do Wyrd.',
            'accent_color' => '#4e4636',
            'volumes' => [
                [
                    'id' => 'sm-v1', 
                    'volume_label' => 'Ancestralidade',
                    'subtitle' => 'Monografia 1',
                    'description' => 'Conectando-se com a Força das Raízes',
                    'price' => 19.90,
                    'promo_active' => 1, 
                    'promo_type' => 'fixed', 
                    'promo_value' => 17.90, 
                    'promo_label' => 'Lançamento',
                    'promo_start_date' => '2026-07-15', 
                    'promo_end_date' => '2026-09-01',
                    'isbn' => '978-65-01-94561-3', 
                    'publication_date' => '2026-01-01',
                    'tags' => ['ancestralidade', 'umbanda', 'kimbanda', 'candomblé', 'magia'],
                ],
                               [
                    'id' => 'sm-v2', 
                    'volume_label' => 'Cimatica',
                    'subtitle' => 'Monografia 2',
                    'description' => 'Explorando a Vibração e a Forma',
                    'price' => 19.90,
                    'promo_active' => 1, 
                    'promo_type' => 'fixed', 
                    'promo_value' => 17.90, 
                    'promo_label' => 'Lançamento',
                    'promo_start_date' => '2026-07-15', 
                    'promo_end_date' => '2026-09-01',
                    'isbn' => '978-65-01-94561-3', 
                    'publication_date' => '2026-01-01',
                    'tags' => ['cimatica', 'vibração', 'forma', 'som', 'energia'],
                ],
            ],
        ], 


        /**
         * Coleções antigas que serão lançadas novamente em 2026, com ISBNs e datas de publicação já definidas. 
         * A ideia é que o catálogo seja atualizado com essas informações.
         */
        [
            'id' => 'xamans-floresta-concreto',
            'title' => 'Os Xamãs da Floresta de Concreto',
            'type' => 'livro',
            'description' => 'Uma análise da espiritualidade urbana e da prática xamânica em contextos contemporâneos, explorando a relação entre o sagrado e o cotidiano.',
            'accent_color' => '#4c00ff6e',
            'volumes' => [
                [
                    'id' => 'xfc-v1', 
                    'volume_label' => 'Edição completa', 
                    'subtitle' => 'Explorando a Espiritualidade Urbana',
                    'description' => 'Uma análise da espiritualidade urbana e da prática xamânica em contextos contemporâneos, explorando a relação entre o sagrado e o cotidiano.',
                    'price' => 32.91,
                    'promo_active' => 1, 
                    'promo_type' => 'fixed', 
                    'promo_value' => 5.8, 
                    'promo_label' => 'Final de Estoque',
                    'promo_start_date' => '2026-07-15', 
                    'promo_end_date' => '2026-09-01',
                    'isbn' => '978-65-01-94561-3', 
                    'publication_date' => '2026-01-01',
                    'tags' => ['xamã', 'espiritualidade urbana', 'prática xamânica', 'sagrado', 'cotidiano'],
                ],
            ],
        ],

        [
            'id' => 'apostila-ervas-utilizacoes',
            'title' => 'Apostila de Ervas e Suas Utilizações',
            'type' => 'apostila',
            'description' => 'Uma apostila completa sobre ervas, suas propriedades e aplicações, voltada para práticas espirituais e medicinais.',
            'accent_color' => '#4be78c',
            'volumes' => [
                [
                    'id' => 'erv-v1', 
                    'volume_label' => 'Edição completa', 
                    'subtitle' => 'Guia Prático de Ervas e Suas Aplicações',
                    'description' => 'Uma apostila completa sobre ervas, suas propriedades e aplicações, voltada para práticas espirituais e medicinais.',
                    'price' => 48.89,
                    'promo_active' => 1, 
                    'promo_type' => 'percent', 
                    'promo_value' => 5, 
                    'promo_label' => 'Final de Estoque',
                    'promo_start_date' => '2026-07-15', 
                    'promo_end_date' => '2026-09-01',
                    'isbn' => '978-65-01-94561-3', 
                    'publication_date' => '2026-01-01',
                    'tags' => ['ervas', 'propriedades', 'aplicações', 'práticas espirituais', 'medicinais', 'xamanismo', 'umbanda', 'kimbanda', 'candomblé'],
                ],
            ],
        ],       
        
        

    ];

    $insCol = $pdo->prepare('INSERT INTO collections (id, title, type, description, accent_color, sort_order) VALUES (?,?,?,?,?,?)');
    $insVol = $pdo->prepare(
        'INSERT INTO volumes
         (id, collection_id, volume_label, subtitle, description, price, promo_active, promo_type, promo_value, promo_label, promo_start_date, promo_end_date, sort_order, isbn, publication_date)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $insTag = $pdo->prepare('INSERT INTO volume_tags (volume_id, tag) VALUES (?, ?)');

    foreach ($collections as $ci => $col) {
        $insCol->execute([$col['id'], $col['title'], $col['type'], $col['description'], $col['accent_color'], $ci]);
        foreach ($col['volumes'] as $vi => $vol) {
            $insVol->execute([
                $vol['id'], $col['id'], $vol['volume_label'], $vol['subtitle'], $vol['description'],
                $vol['price'], $vol['promo_active'], $vol['promo_type'], $vol['promo_value'],
                $vol['promo_label'], $vol['promo_start_date'], $vol['promo_end_date'], $vi,
                $vol['isbn'], $vol['publication_date'],
            ]);
            foreach ($vol['tags'] as $tag) {
                $insTag->execute([$vol['id'], $tag]);
            }
        }
    }
}
