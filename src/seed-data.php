<?php
declare(strict_types=1);

/**
 * Dados iniciais do catálogo — usado só na primeira execução (banco
 * vazio). Fica separado de db.php de propósito: depois que o banco já
 * tem dados, este arquivo nunca mais é lido nem processado em nenhuma
 * requisição — só existe pra popular um site novo.
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
                    'promo_value' => 5.90, 
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
                    'isbn' => '', 
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
                    'isbn' => '', 
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
            'accent_color' => '#4c00ff',
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
                    'isbn' => '', 
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
                    'isbn' => '', 
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
