# Banco de dados

SQLite, um arquivo único (`catalogo.sqlite`), criado automaticamente
fora de `public_html/` na primeira requisição. Schema definido em
`src/db.php` (`ba_ensure_schema()`) — este documento é um espelho dele;
em caso de dúvida, o código é a fonte da verdade.

## `admin_users`

Conta(s) do painel administrativo. Hoje o fluxo só cria uma (via
`/setup.php`), mas a tabela suporta mais de uma se um dia fizer sentido.

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | INTEGER PK | |
| `username` | TEXT UNIQUE | |
| `password_hash` | TEXT | `password_hash()` do PHP (bcrypt), nunca texto puro |
| `created_at` | TEXT | timestamp automático |

## `collections`

As "séries"/coleções do catálogo (ex.: *As Histórias que os Ventos
Trazem*).

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | TEXT PK | slug, ex. `historias-ventos` |
| `title` | TEXT | |
| `type` | TEXT | `livro` \| `monografia` \| `liturgico` |
| `description` | TEXT | |
| `accent_color` | TEXT | cor hex usada no site e no admin |
| `sort_order` | INTEGER | ordem de exibição |

## `volumes`

Cada volume/livro individual dentro de uma coleção. É a tabela mais
central do sistema.

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | TEXT PK | ex. `historias-ventos-vXXXXXXXX` |
| `collection_id` | TEXT | FK → `collections.id`, `ON DELETE CASCADE` |
| `volume_label` | TEXT | ex. "Volume I" |
| `subtitle` | TEXT | título de exibição do volume |
| `description` | TEXT | |
| `price` | REAL | preço base, sem promoção |
| `promo_active` | INTEGER (bool) | |
| `promo_type` | TEXT | `percent` \| `fixed` |
| `promo_value` | REAL | |
| `promo_label` | TEXT | texto do selo de promoção |
| `promo_start_date` / `promo_end_date` | TEXT | formato `AAAA-MM-DD`, vazio = sem limite |
| `sort_order` | INTEGER | |
| `isbn` | TEXT | *(Fase 2)* |
| `language` | TEXT | *(Fase 2)*, padrão "Português (Brasil)" |
| `page_count` | INTEGER, nullable | *(Fase 2)* |
| `publication_date` | TEXT | *(Fase 2)*, formato `AAAA-MM-DD` |
| `author_id` | TEXT, nullable | *(Fase 2)* FK → `authors.id`, `ON DELETE SET NULL` |

O cálculo de preço final (aplicando a promoção, se ativa e dentro da
janela de datas) **não** é uma coluna — é calculado em runtime tanto no
PHP (`ba_get_settings`/endpoints) quanto espelhado em JS
(`getPricing()` em `common.js`). Se mudar a regra de cálculo, precisa
mudar nos dois lugares.

## `volume_tags`

Relação simples N:N entre volume e tema (tag), sem tabela de tags
separada — a tag é só o texto.

| Coluna | Tipo |
|---|---|
| `volume_id` | TEXT, FK → `volumes.id`, `ON DELETE CASCADE` |
| `tag` | TEXT |

## `authors` *(Fase 2)*

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | TEXT PK | slug, também usado na URL `/autor/{id}` |
| `name` | TEXT | |
| `bio` | TEXT | |
| `photo_url` | TEXT | URL externa, sem upload de arquivo |
| `sort_order` | INTEGER | |

Semeada com dois registros fixos na primeira execução:
`kunnigr-afi` e `christopher-mauricio` (ver
[`arquitetura.md`](arquitetura.md) sobre o guard de migração que evita
recriá-los se forem apagados de propósito).

## `site_settings` *(Fase 1)*

Chave/valor genérico — dados do cartão de visita/vCard (telefone,
WhatsApp, PIX, redes sociais, etc.), editáveis via `/admin/cartao.php`.
Também guarda os marcadores de migração (chaves prefixadas
`_migration_*`, não editáveis pela interface).

| Coluna | Tipo |
|---|---|
| `key` | TEXT PK |
| `value` | TEXT |

Chaves atuais em uso pelo site (fora as de migração): `org_name`,
`editor_name`, `editor_title`, `note`, `phone`, `email`,
`address_line`, `site_url`, `photo_url`, `logo_url`,
`whatsapp_number`, `whatsapp_message`, `pix_key`, `pix_key_type`,
`instagram_url`, `youtube_url`, `location_maps_url`, `catalogo_url`,
`loja_url`, `runomante_label`, `runomante_url`, `site_oficial_url`.

## `login_attempts`

Controle de força bruta no login do admin, por IP.

| Coluna | Tipo |
|---|---|
| `ip` | TEXT PK |
| `attempts` | INTEGER |
| `locked_until` | TEXT, timestamp — vazio/nulo = não bloqueado |

## Diagrama de relações

```
collections 1───* volumes *───1 authors
                    │
                    *
                    │
              volume_tags

site_settings, admin_users, login_attempts — tabelas independentes,
sem relação com as demais.
```

## Tabelas planejadas (ainda não existem)

Do roadmap de próximas fases — documentado aqui pra quem for
implementar não precisar redesenhar do zero:

- `customers` — cadastro de cliente (nome, e-mail, senha com hash, telefone)
- `customer_addresses` — vários endereços por cliente, um marcado padrão
- `orders` / `order_items` — pedidos, com preço e endereço **congelados
  no momento da compra** (não referenciar o preço/endereço atual — ver
  discussão de arquitetura no histórico do projeto)
