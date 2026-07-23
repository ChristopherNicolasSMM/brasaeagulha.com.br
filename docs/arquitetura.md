# Arquitetura

## Por que `config.php` e `src/` ficam fora de `public_html/`

`public_html/` é a única pasta que o servidor web expõe por URL. Tudo
que está um nível acima (`config.php`, `src/`, e o próprio
`catalogo.sqlite`, que é criado ali na primeira execução) é alcançável
pelo PHP via caminho de arquivo, mas **nunca** por uma requisição HTTP
direta.

Isso importa especialmente para o banco de dados: como é SQLite (um
arquivo único, diferente de MySQL onde os dados moram num servidor
separado), se ele estivesse dentro de `public_html/` qualquer pessoa
poderia baixá-lo direto pela URL e abrir offline — expondo todo o
catálogo e o hash da senha do admin. Por isso ele mora fora.

## Conexão com o banco: `ba_db()`

Toda a lógica de banco vive em `src/db.php` — **exceto os dados
iniciais do catálogo**, que ficam em `src/seed-data.php` (função
`ba_seed_catalog()`), separado de propósito: esse arquivo só é lido
(`require_once`) na primeira execução, quando a tabela `collections`
está vazia. Depois disso, ele nunca mais é processado em nenhuma
requisição — só existe pra popular um site novo.

A função central de `db.php` é `ba_db()`, chamada no topo de
praticamente todo arquivo PHP (via `require config.php`, que por sua
vez inclui `src/db.php`). Ela faz duas coisas em **toda chamada**, não
só na primeira:

```php
ba_ensure_schema($pdo);   // cria tabelas/colunas que ainda não existem
ba_ensure_seed($pdo);     // popula dados padrão que ainda não existem
```

### Por que schema idempotente, e como funciona

Cada `CREATE TABLE` usa `IF NOT EXISTS`. Colunas novas usam o helper
`ba_ensure_column($pdo, $tabela, $coluna, $definicao)`, que confere via
`PRAGMA table_info` se a coluna já existe antes de tentar adicionar —
porque SQLite não tem `ADD COLUMN IF NOT EXISTS` nativo.

Isso significa: **nunca é preciso rodar um "instalador"** separado nem
pedir pra alguém apagar o banco antes de subir uma atualização. O
mesmo código funciona tanto num banco novo (cria tudo do zero) quanto
num banco de produção com meses de dados reais (só adiciona o que
falta, nunca apaga nada).

Isso foi testado na prática: cada fase nova foi validada simulando um
banco com o schema da fase anterior + dados fictícios "reais" antes de
ser entregue, justamente pra garantir que a migração nunca destrói nada.

### O cuidado com migrações que rodam "só uma vez de verdade"

`ba_ensure_schema()` e a criação de tabelas/colunas são seguras de
repetir sempre. **Mas nem toda migração é assim.** Isso gerou um bug
real durante a Fase 2, que vale documentar como lição:

> A vinculação automática de autor por coleção (ex.: todo volume de
> "As Histórias que os Ventos Trazem" recebe o autor "Kunnigr Afi" por
> padrão) inicialmente rodava dentro de `ba_ensure_seed()`, chamada em
> toda requisição. Resultado: se alguém trocasse manualmente o autor de
> um volume, ou apagasse esse autor, a próxima requisição desfazia a
> mudança sozinha — porque a regra "se não tem autor, atribui o
> padrão" rodava de novo.

A correção foi um marcador de migração, guardado na própria tabela
`site_settings` com prefixo `_migration_`:

```php
function ba_migration_done(PDO $pdo, string $key): bool { /* ... */ }
function ba_mark_migration_done(PDO $pdo, string $key): void { /* ... */ }
```

**Regra prática para o futuro:** qualquer migração que faça mais do que
"criar algo que não existe" (por exemplo: preencher um valor padrão que
o usuário pode legitimamente querer apagar ou mudar depois) precisa
desse guard. Só "criar tabela/coluna vazia" dispensa — o resto, não.

## Inativação em vez de exclusão (a partir da Fase 4)

Tags e coleções usam uma coluna `active` (`1`/`0`) em vez de `DELETE`.
"Excluir" nessas telas é só desmarcar `active` — os dados continuam no
banco, ligados a tudo que já os usava, só somem do que é **novo**
(sugestões de tag, catálogo público, seletor de coleção pra criar
volume). Volumes e autores, por enquanto, continuam com exclusão de
verdade (`DELETE`) — não foram migrados pro mesmo padrão ainda; se
fizer sentido mudar, é uma alteração pequena e isolada.

Ao adicionar uma tela de gestão nova pra qualquer outra tabela, o
padrão esperado é o mesmo: coluna `active` + toggle no formulário, não
um botão de excluir que apaga a linha.

## Autenticação e sessão (`src/auth.php`)

- `ba_start_session()` configura o cookie de sessão com `HttpOnly`,
  `SameSite=Lax`, e `Secure` automático quando detecta HTTPS.
- `ba_require_admin_page()` — usado no topo de páginas HTML do admin;
  redireciona pro login se a sessão não for válida.
- `ba_require_admin_api()` — usado no topo de endpoints JSON; responde
  `401` em vez de redirecionar.
- Login errado incrementa um contador por IP em `login_attempts`; após
  5 tentativas, bloqueia por 15 minutos (constantes em `config.php`).
- A conta de admin **não** é semeada por código — só existe depois que
  alguém visita `/setup.php` e escolhe usuário/senha manualmente.
  `setup.php` se desativa sozinho depois da primeira conta criada
  (confere `SELECT COUNT(*) FROM admin_users` antes de aceitar um
  cadastro).

## CSRF (`src/csrf.php`)

Token gerado por sessão (`ba_csrf_token()`), comparado com
`hash_equals()` (`ba_csrf_verify()`). Todo endpoint de escrita em
`api/admin/*.php` exige o token no corpo da requisição — sem ele,
`403`.

## Padrão dos endpoints administrativos

Todo arquivo em `public_html/api/admin/*.php` segue a mesma ordem:

```php
require __DIR__ . '/../../../config.php';
ba_start_session();
header('Content-Type: application/json; charset=utf-8');
ba_require_admin_api();                    // 401 se não autenticado

$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (!ba_csrf_verify($input['csrf'] ?? null)) { /* 403 */ }

// ... validação de entrada, sempre com lista branca pra nomes de coluna ...
```

**Nunca** interpolar nome de coluna vindo do cliente direto numa query
SQL — sempre mapear contra uma lista branca fixa no PHP primeiro (ver
`update-volume.php`/`save-volume.php` como exemplo do padrão).

## Painel administrativo: lista + modal (a partir da Fase 3)

O Catálogo e Autores usam o elemento nativo `<dialog>` do HTML como
modal de criação/edição — sem biblioteca externa. Um único formulário
serve pra criar (campo `id` vazio) e editar (campo `id` preenchido); o
endpoint (`save-volume.php`, `save-author.php`) decide qual caso é
baseado nisso. Coleções continuam com formulário simples inline (não
entraram nesse padrão por decisão explícita, não por limitação técnica
— pode mudar se fizer sentido depois).

## Front-end (site público)

`public_html/js/common.js` tem as funções compartilhadas entre o site
público e o admin (`esc()`, `formatBRL()`, `getPricing()`, etc.) — sem
isso, cada página reimplementaria a mesma lógica de preço/promoção.
`js/site.js` busca o catálogo via `fetch('/api/catalogo.php')` e
renderiza tudo client-side, com roteamento por hash (`#catalogo`,
`#livro-{id}`, etc.) — não é uma SPA com framework, é JS direto.

## URLs limpas

Rotas como `/vcard`, `/cartao` e `/autor/{slug}` são só arquivos PHP
comuns (`vcard.php`, `cartao.html`, `autor.php?slug=...`) reescritos
via `.htaccess` (`mod_rewrite`). Isso só funciona em Apache/LiteSpeed —
o servidor embutido do PHP (`php -S`, usado pra testar localmente) não
lê `.htaccess`, então ao testar localmente use os nomes de arquivo
reais (`autor.php?slug=kunnigr-afi`).
