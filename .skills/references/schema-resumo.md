# Schema — resumo rápido

Schema completo e sempre atualizado: `docs/banco-de-dados.md` no
repositório. Isto aqui é só pra não precisar buscar o repositório em
toda pergunta simples.

- **`admin_users`** — conta(s) do painel. `password_hash` sempre via
  `password_hash()` do PHP, nunca texto puro. Criada só por
  `/setup.php` (que se autodesativa depois da primeira conta).
- **`collections`** — as séries do catálogo. `type`: `livro` |
  `monografia` | `liturgico`.
- **`volumes`** — o livro/volume em si. FK pra `collections` (cascade)
  e pra `authors` (`SET NULL` ao excluir autor). Campos de preço/promo
  + ISBN/idioma/páginas/data (Fase 2).
- **`volume_tags`** — N:N simples, sem tabela de tag separada.
- **`authors`** — bio/foto/slug, página pública em `/autor/{id}`.
  Semeada com `kunnigr-afi` e `christopher-mauricio` (guard de
  migração — não recria se apagado de propósito).
- **`site_settings`** — chave/valor genérico, dados do cartão de
  visita/vCard. Também guarda marcadores de migração (chaves
  `_migration_*`).
- **`login_attempts`** — força bruta por IP no login do admin.

Planejadas, ainda não existem: `customers`, `customer_addresses`,
`orders`, `order_items` (ver `docs/banco-de-dados.md` pro desenho já
discutido antes de implementar do zero).
