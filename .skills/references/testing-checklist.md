# Roteiro de testes antes de entregar qualquer patch

Baseado no que já pegou bugs reais neste projeto — não é burocracia,
cada item aqui já evitou (ou pegou) um problema de verdade em alguma
fase anterior.

## 1. Lint

```bash
find . -name "*.php" -not -path "./.git/*" | xargs -I{} php -l {} 2>&1 | grep -v "No syntax errors"
node --check public_html/js/algum-arquivo.js
```
Vazio = ok. Rodar em **todo** arquivo alterado, não só nos novos.

## 2. Servidor local + fluxo por curl

```bash
rm -f catalogo.sqlite   # começa do zero
(php -S 127.0.0.1:8000 -t public_html > /tmp/php-server.log 2>&1 &)
sleep 1
```

Dali, testar com `curl`:
- Rota nova responde o código esperado (200, 302, 401, 403, 404 conforme o caso)
- Endpoint `api/admin/*.php` sem cookie de sessão → **401**
- Mesmo endpoint com sessão mas CSRF errado/ausente → **403**
- Mesmo endpoint com sessão e CSRF certos → 200 e o dado realmente mudou
  (confirmar lendo de volta via GET, não só confiar no `{"ok":true}`)
- `grep -i "warning\|notice\|fatal" /tmp/php-server.log` → precisa vir vazio

Lembrar: o servidor embutido do PHP não lê `.htaccess` — pra testar
rotas reescritas (`/vcard`, `/cartao`, `/autor/{slug}`) localmente, usar
o nome de arquivo real (`vcard.php`, `cartao.html`, `autor.php?slug=...`).

## 3. Segurança da migração (sempre que mexer em `src/db.php`)

Este é o teste mais importante do projeto. Simular um banco "de
produção" no schema **anterior** à mudança, com dados fictícios que
representem algo real que o usuário já tenha cadastrado, e confirmar
que o código novo não apaga nada:

```bash
sqlite3 catalogo.sqlite <<'EOF'
-- CREATE TABLE ... (schema da versão ANTERIOR, sem a coluna/tabela nova)
-- INSERT ... um registro representando dado real do usuário
EOF

# roda o código novo por cima, sem alterar o banco manualmente antes
curl -s http://127.0.0.1:8000/api/catalogo.php -o /dev/null -w "%{http_code}\n"

# confere: schema novo apareceu?
sqlite3 catalogo.sqlite "PRAGMA table_info(tabela_alterada);"
# confere: dado antigo continua exatamente como estava?
sqlite3 catalogo.sqlite "SELECT * FROM tabela_com_dado_real;"
```

Se a mudança envolve algum tipo de "preenchimento automático" (como o
backfill de autor por coleção), rodar o endpoint relevante **mais de
uma vez em sequência** depois de uma edição manual simulada — é assim
que se pega o bug de migração rodando repetidamente e desfazendo edição
do usuário.

## 4. Validar o patch numa cópia limpa

Nunca confiar só na cópia de trabalho onde o código foi escrito — ela
já reflete a mudança, então "aplicar o patch nela" não prova nada.

```bash
git diff <commit-base> HEAD > mudanca.patch

# em outra pasta, do zero:
git clone --depth 5 https://github.com/ChristopherNicolasSMM/site-brasa-e-agulha-editorial.git verificacao
cd verificacao
git apply --check mudanca.patch && echo "aplicaria limpo"
git apply mudanca.patch
find . -name "*.php" -not -path "./.git/*" | xargs -I{} php -l {} 2>&1 | grep -v "No syntax errors"
```

Se o usuário já tiver aplicado uma fase anterior localmente sem
commitar/pushar, o repositório público pode estar "atrasado" em
relação ao que ele já tem — nesse caso, considerar gerar dois patches
(um partindo do estado público, outro assumindo a fase anterior já
aplicada) e deixar claro qual usar em cada cenário.
