# Mapa de páginas e endpoints

## Públicas

| URL | Arquivo | O que é |
|---|---|---|
| `/` | `index.html` | Site principal: início, catálogo, detalhe de livro, contato — SPA por hash (`#catalogo`, `#livro-{id}`, `#colecao-{id}`, `#contato`) |
| `/cartao` | `cartao.html` (via rewrite) | Cartão de visita digital — botões de WhatsApp, PIX, redes sociais, catálogo |
| `/vcard` | `vcard.php` (via rewrite) | Gera `.vcf` na hora, dados vindos de `site_settings` |
| `/autor/{id}` | `autor.php?slug={id}` (via rewrite) | Bio + lista de obras de um autor |

`/cartao`, `/vcard` e `/autor/{id}` não aparecem em nenhum menu do site
principal — só são alcançáveis por link direto ou QR Code. A central de
links em `/admin/paginas.php` existe justamente pra ter uma lista
copiável desses endereços.

## Administrativas (exigem sessão válida)

| URL | O que é |
|---|---|
| `/admin/` | Catálogo — lista de coleções/volumes, modal de criar/editar, adicionar coleção |
| `/admin/autores.php` | Lista de autores, modal de criar/editar/excluir |
| `/admin/tags.php` | Lista de tags, modal de criar/editar/inativar |
| `/admin/cartao.php` | Configurações do cartão de visita/vCard + gerador de QR Code |
| `/admin/paginas.php` | Central de links (esta lista, mas pra uso no dia a dia) |
| `/admin/change-password.php` | Trocar a própria senha |
| `/admin/login.php` | Login (público — é o único jeito de conseguir a sessão) |
| `/admin/logout.php` | Encerra a sessão |

## Configuração inicial (uso único)

| URL | O que é |
|---|---|
| `/setup.php` | Cria a primeira (e única) conta de admin. Depois disso, sempre recusa criar outra — confere `admin_users` antes de aceitar qualquer cadastro, então é seguro deixar o arquivo no servidor. |

## API pública (GET, sem autenticação)

| Endpoint | Retorna |
|---|---|
| `/api/catalogo.php` | Todas as coleções e volumes, com preço já calculado, autor, tags, ISBN etc. |
| `/api/authors.php` | Lista de autores (id, nome, bio, foto) |
| `/api/tags.php` | Lista de tags ativas (usada na nuvem de temas) |
| `/api/settings.php` | Todas as chaves de `site_settings` (usado por `/cartao` e no futuro por qualquer página que precise de telefone/PIX/redes sociais) |

Nenhuma dessas expõe dado sensível — é exatamente o que já é público no
site/cartão, só em JSON.

## API pública de escrita (POST, sem autenticação — mas com validação)

| Endpoint | Efeito |
|---|---|
| `/api/notify-me.php` | Registra pedido de aviso quando um volume `out_of_stock` voltar (e-mail + WhatsApp obrigatórios, aniversário opcional) |

## API administrativa (POST, exige sessão + token CSRF)

| Endpoint | Efeito |
|---|---|
| `/api/admin/save-volume.php` | Cria (id vazio) ou edita (id preenchido) um volume — todos os campos de uma vez, incluindo disponibilidade |
| `/api/admin/delete-volume.php` | Exclui um volume e suas tags |
| `/api/admin/add-collection.php` | Cria uma coleção |
| `/api/admin/update-collection.php` | Edita uma coleção existente, incluindo inativar |
| `/api/admin/upload-volume-image.php` | Envia uma foto pro volume (`multipart/form-data`, campo `image` + `volume_id`) |
| `/api/admin/set-primary-image.php` | Marca uma imagem como principal (desmarca as outras do mesmo volume) |
| `/api/admin/delete-volume-image.php` | Apaga arquivo + registro; promove outra imagem se a apagada era a principal |
| `/api/admin/catalogo-full.php` | Como `/api/catalogo.php`, mas inclui coleções inativadas (uso exclusivo do admin) |
| `/api/admin/save-tag.php` | Cria ou edita uma tag, incluindo inativar |
| `/api/admin/tags-list.php` | Lista completa de tags (ativas e inativas), com contagem de uso |
| `/api/admin/notify-counts.php` | Quantas pessoas pediram aviso, por volume |
| `/api/admin/save-author.php` | Cria ou edita um autor |
| `/api/admin/delete-author.php` | Exclui um autor (os livros dele ficam sem autor, não são apagados) |
| `/api/admin/update-settings.php` | Atualiza uma ou mais chaves de `site_settings` (lista branca fixa de chaves aceitas) |
| `/api/admin/import.php` | Substitui **todo** o catálogo (coleções + volumes) pelo conteúdo de um JSON — operação destrutiva, usada pra restaurar backup |

Todo endpoint desta tabela responde `401` sem sessão válida e `403` com
token CSRF ausente/errado — testado explicitamente pra cada um antes de
cada entrega.

## Fluxo de navegação (site público)

```
/  (index.html)
├── #home        → hero, prévia das 3 coleções, promoções ativas
├── #catalogo     → grade de todos os volumes, busca, filtro por tag/coleção
├── #colecao-{id} → catálogo filtrado por uma coleção
├── #livro-{id}   → detalhe de um volume (preço, ISBN, autor com link, tags)
└── #contato      → formulário (abre o cliente de e-mail do visitante via mailto:)

/autor/{id}  → bio + lista de obras, cada uma linkando de volta pra #livro-{id}
/cartao      → hub de links (pensado pra QR Code impresso)
/vcard       → download direto, sem página intermediária
```
