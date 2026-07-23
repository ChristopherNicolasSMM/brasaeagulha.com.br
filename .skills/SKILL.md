---
name: brasa-agulha-webdev
description: Skill técnica (não editorial) do site da Brasa & Agulha Editorial — PHP + SQLite, painel administrativo, deploy. Use SEMPRE que o usuário pedir para editar, debugar, adicionar funcionalidade, revisar segurança, gerar patch, ou discutir deploy/publicação do site em https://github.com/ChristopherNicolasSMM/site-brasa-e-agulha-editorial — mesmo que ele não mencione o repositório explicitamente, se o contexto for "o site", "o painel admin", "o catálogo" (no sentido de sistema, não de conteúdo editorial) ou "o /cartao". Não confundir com as skills norse-* (norse-hub, norse-estilo-editorial etc.), que tratam do conteúdo/estilo dos livros, não do código do site.
---

# Brasa & Agulha — Site (skill técnica)

Router técnico para trabalhar no código do site. Para estilo editorial,
tom de voz ou diagramação de livros, use as skills `norse-*` — esta
skill é só sobre o sistema (PHP, banco, admin, deploy).

## O que é o projeto

Site institucional + catálogo + cartão de visita digital da Brasa &
Agulha Editorial. PHP puro (sem framework) + SQLite, pensado pra rodar
em hospedagem compartilhada comum (Hostinger), sem build step e sem
Composer. Repositório público:
`https://github.com/ChristopherNicolasSMM/site-brasa-e-agulha-editorial`

**Primeiro passo ao começar qualquer tarefa nesta skill:** clone o
repositório (`git clone` funciona sem autenticação, é público) e leia
`docs/` de lá — é a documentação completa e sempre atualizada
(arquitetura, schema do banco, mapa de páginas, deploy). Esta skill é
um resumo com as regras mais importantes de não esquecer; `docs/` tem
a profundidade.

## Regras não negociáveis (leia antes de tocar em código)

1. **`config.php` e `src/` ficam fora de `public_html/`.** Nunca mova
   nada sensível pra dentro do webroot. `catalogo.sqlite` mora ao lado
   de `config.php`, nunca dentro de `public_html/`, nunca no git (está
   no `.gitignore` — confira antes de commitar qualquer coisa nova).

2. **Toda mudança de schema é idempotente.** `CREATE TABLE IF NOT
   EXISTS` sempre. Coluna nova usa o helper `ba_ensure_column($pdo,
   $tabela, $coluna, $definicao)` (SQLite não tem `ADD COLUMN IF NOT
   EXISTS`). Isso roda em **toda** requisição via `ba_db()`, não só na
   primeira — então nunca pode fazer nada destrutivo.

3. **Migração que não seja "criar algo vazio" precisa de guard.** Se a
   migração popular um valor padrão, vincular dados, ou qualquer coisa
   que o usuário possa legitimamente querer desfazer depois, use:
   ```php
   if (ba_migration_done($pdo, 'chave-unica-da-migracao')) { return; }
   // ... faz a migração ...
   ba_mark_migration_done($pdo, 'chave-unica-da-migracao');
   ```
   Sem isso, a migração desfaz a edição do usuário na próxima
   requisição — já aconteceu de verdade (backfill de autor por
   coleção, Fase 2) e foi corrigido assim. Não repetir o erro.

4. **Todo endpoint em `api/admin/*.php` exige sessão + CSRF**, nessa
   ordem: `ba_require_admin_api()` primeiro (401 sem sessão), depois
   `ba_csrf_verify($input['csrf'] ?? null)` (403 se inválido). Nomes de
   coluna SQL vindos do cliente **sempre** passam por lista branca fixa
   no PHP — nunca interpolar direto.

5. **Nunca ative `dangerous-clean-slate: true`** no `deploy.yml`, nem
   implemente remoção de arquivo remoto órfão no `.deploy/` sem
   confirmar explicitamente com o usuário — é o que protege o banco de
   produção de ser apagado por um deploy automático.

## Fluxo de trabalho esperado

1. Clonar o repositório público, trabalhar numa cópia local.
2. Implementar a mudança.
3. **Testar antes de entregar** — ver `references/testing-checklist.md`
   pro roteiro completo. Resumo: lint de todo PHP/JS alterado, subir
   `php -S` local, testar o fluxo por `curl` (incluindo `401`/`403`
   dos endpoints protegidos), e — sempre que mexer em schema —
   simular um banco com dados "reais" no schema **anterior** e rodar o
   código novo em cima, confirmando que nada foi apagado.
4. Gerar o patch com `git diff` (não `git format-patch`, a menos que o
   usuário peça explicitamente formato `git am`).
5. **Validar o patch aplicando numa cópia limpa** (`git clone` de novo
   em outra pasta, `git apply --check`) antes de entregar — não confiar
   só na cópia de trabalho onde o código foi escrito.
6. Entregar o `.patch` com instrução de `git apply nome.patch`.

## Erros já vistos e a causa

- **`Patch format detection failed.`** → o usuário rodou `git am` num
  patch gerado por `git diff`. Comando certo: `git apply`.
- **`... não está assinado digitalmente ...` ao rodar `.deploy/deploy.ps1`**
  → política de execução do PowerShell no Windows, não é bug no script.
  `pwsh -ExecutionPolicy Bypass -File .\.deploy\deploy.ps1`.
- **Patch não aplica (`patch does not apply` / `already exists`)** → a
  base do patch não bate com o estado atual do repositório (ex.: fase
  já aplicada localmente sem commit/push). Perguntar em que estado o
  repositório está antes de gerar o próximo patch, ou clonar e conferir
  diretamente.

## Referências

- `references/testing-checklist.md` — roteiro de testes antes de
  qualquer entrega
- `references/schema-resumo.md` — resumo rápido das tabelas (schema
  completo sempre em `docs/banco-de-dados.md` no repositório)
- Documentação completa: `docs/` no repositório (sempre a fonte mais
  atual — esta skill pode ficar desatualizada, o repositório não)
