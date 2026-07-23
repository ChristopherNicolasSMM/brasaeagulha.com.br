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
| `type` | TEXT | `livro` \| `monografia` \| `liturgico` \| `apostila` |
| `description` | TEXT | |
| `accent_color` | TEXT | cor hex usada no site e no admin |
| `sort_order` | INTEGER | ordem de exibição |
| `active` | INTEGER (bool) | *(Fase 4)* inativa em vez de apagar — some do site público e do admin de "novo volume", mas os volumes que já tem continuam existindo |

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
| `availability` | TEXT | *(Fase 4)* `available` \| `coming_soon` \| `out_of_stock` |

O cálculo de preço final (aplicando a promoção, se ativa e dentro da
janela de datas) **não** é uma coluna — é calculado em runtime tanto no
PHP (`ba_get_settings`/endpoints) quanto espelhado em JS
(`getPricing()` em `common.js`). Se mudar a regra de cálculo, precisa
mudar nos dois lugares.

## `volume_tags`

Relação N:N entre volume e tema (tag).

| Coluna | Tipo |
|---|---|
| `volume_id` | TEXT, FK → `volumes.id`, `ON DELETE CASCADE` |
| `tag` | TEXT — nome da tag, mantido por compatibilidade com todo código que já lê `vol.tags` como array de strings |
| `tag_id` | TEXT, nullable *(Fase 4)* — FK → `tags.id`, `ON DELETE SET NULL` |

## `tags` *(Fase 4)*

Registro central de tags — permite editar nome e inativar num só
lugar, em vez de string solta espalhada em cada `volume_tags`.

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | TEXT PK | slug |
| `name` | TEXT | nome de exibição |
| `active` | INTEGER (bool) | inativa = some da nuvem de temas e das sugestões de cadastro, mas continua nos volumes que já a usam |
| `sort_order` | INTEGER | |

Tag nova é criada automaticamente (`ba_find_or_create_tag()`) sempre
que alguém salva um volume com uma tag que ainda não existe — não
precisa cadastrar na tela de Tags antes de usar.

## `authors` *(Fase 2)*

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | TEXT PK | slug, também usado na URL `/autor/{id}` |
| `name` | TEXT | |
| `bio` | TEXT | |
| `photo_url` | TEXT | URL externa, sem upload de arquivo |
| `sort_order` | INTEGER | |

## `stock_interest` *(Fase 4)*

Quem pediu aviso quando um volume `out_of_stock` voltar. Base pra
campanha de marketing (backlog de tela ainda por construir).

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | INTEGER PK | |
| `volume_id` | TEXT | FK → `volumes.id`, `ON DELETE CASCADE` |
| `email` | TEXT | |
| `whatsapp` | TEXT | obrigatório na captura pública |
| `birthday` | TEXT | opcional, formato `AAAA-MM-DD` |
| `campaign_sent` | INTEGER (bool) | marca se já foi feito o envio quando o item voltou ao estoque |
| `campaign_sent_at` | TEXT | |
| `created_at` | TEXT | |

`UNIQUE(volume_id, email)` — pedir aviso de novo com o mesmo e-mail
atualiza whatsapp/aniversário em vez de duplicar.

## `volume_images` *(Fase B)*

Fotos por volume. Arquivo físico fica em
`public_html/img/livros/{volume_id}/{filename}` — uma pasta por
publicação, criada automaticamente no primeiro upload
(`ba_volume_image_dir()` em `src/images.php`). Sem foto cadastrada, o
site mostra o selo rúnico do tipo da coleção (ᛟ/ᚠ/ᚨ/ᛃ), como sempre foi.

| Coluna | Tipo | Observação |
|---|---|---|
| `id` | INTEGER PK | |
| `volume_id` | TEXT | FK → `volumes.id`, `ON DELETE CASCADE` |
| `filename` | TEXT | nome gerado (nunca o nome original enviado) |
| `is_primary` | INTEGER (bool) | uma só por volume — a primeira imagem enviada vira principal sozinha; as seguintes não |
| `sort_order` | INTEGER | |
| `created_at` | TEXT | |

Ao excluir a imagem que era a principal, a próxima da fila (por
`sort_order`) é promovida automaticamente — nunca fica um volume com
fotos e nenhuma principal.

**Validação de upload** (`api/admin/upload-volume-image.php`): nunca
confia na extensão nem no `Content-Type` enviado pelo navegador —
`getimagesize()` abre o arquivo de verdade e só aceita JPG/PNG/WEBP/GIF
reais, o que barra um `.php` disguised as `.jpg`. Limite de 5 MB. Como
camada extra, `public_html/img/livros/.htaccess` bloqueia a execução de
qualquer script nessa pasta, não importa a extensão.

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
