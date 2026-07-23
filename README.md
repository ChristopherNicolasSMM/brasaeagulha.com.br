# Brasa & Agulha Editorial — pacote de publicação

Este pacote contém o site completo (catálogo, contato, painel administrativo
com login e banco de dados). Testado localmente com PHP 8.3 + SQLite antes
da entrega — os fluxos de login, bloqueio por tentativas, permissões e
edição de preço/promoção foram todos conferidos na prática.

## 1. Estrutura do pacote

```
config.php          ← fica FORA do public_html (um nível acima)
src/                 ← fica FORA do public_html também (junto com config.php)
public_html/         ← o CONTEÚDO desta pasta vai DENTRO do public_html do Hostinger
```

`config.php` e `src/` NUNCA devem ir para dentro de `public_html`. É isso
que impede que alguém baixe o banco de dados ou leia os arquivos de lógica
direto pela URL.

## 2. Passo a passo no hPanel (Hostinger)
 
1. **Arquivos → Gerenciador de Arquivos**, entre na pasta do seu domínio
   (algo como `domains/seusite.com/`).
2. Você verá uma pasta `public_html` já existente. **Não apague o que já
   tem lá** se o domínio já estiver em uso — combine com o conteúdo deste
   pacote.
3. Suba `config.php` e a pasta `src/` para o nível **acima** de
   `public_html` (ou seja, direto em `domains/seusite.com/`, ao lado da
   pasta `public_html`, não dentro dela).
4. Suba todo o conteúdo da pasta `public_html/` deste pacote para dentro
   do `public_html/` real do seu domínio.
5. Confirme a versão do PHP em **Websites → [seu site] → PHP Configuration**:
   escolha **PHP 8.1 ou mais recente**. SQLite (`pdo_sqlite`) já vem
   habilitado por padrão — não precisa mexer em extensões.
6. Ative o **SSL grátis** em Websites → [seu site] → SSL, se ainda não
   estiver ativo. O login do painel funciona sem HTTPS, mas com HTTPS o
   cookie de sessão fica mais protegido (isso é automático no código,
   não exige configuração extra).

## 3. Criar o usuário administrador (uma única vez)

1. Acesse `https://seusite.com/setup.php`.
2. Escolha um usuário e uma senha (mínimo 10 caracteres — quanto mais
   longa e menos óbvia, melhor).
3. Pronto. A partir daqui, `setup.php` se desativa sozinho — mesmo que
   você esqueça de apagá-lo, ele não deixa criar um segundo admin.
4. Por organização, é recomendável apagar `setup.php` do servidor depois
   de usar, mas não é uma falha de segurança grave se esquecer.

## 4. Usando o painel

- Entrar: `https://seusite.com/admin/login.php`
- Painel: `https://seusite.com/admin/` (redireciona pro login se a sessão
  não for válida)
- Trocar senha: link "Trocar senha" dentro do painel
- Sair: link "Sair"

No painel você edita preço e promoção de cada volume (salva direto no
banco, aparece no site na hora), adiciona volumes a uma coleção existente,
cria coleções novas, e pode exportar/importar o catálogo inteiro em JSON
como backup.

## 5. Sobre as imagens

A logo já está em `public_html/img/` em três tamanhos (512px para uso
grande, 192px e 32px para favicon/ícone), recortada em círculo com fundo
transparente. Para adicionar mais imagens (capas de volumes, por exemplo),
suba os arquivos em `public_html/img/` — se quiser que eu ligue elas aos
volumes correspondentes, é só enviar e avisar quais vão em qual título.

## 6. O que já foi testado antes da entrega

- Criação do admin (uma vez só, com validação de senha)
- Login correto / incorreto
- Bloqueio automático após 5 tentativas erradas (por 15 minutos)
- Acesso ao painel exige sessão válida (senão redireciona)
- Todo endpoint de escrita exige sessão **e** token CSRF (401 sem sessão,
  403 com token inválido, 200 com tudo certo)
- Edição de preço/promoção grava no banco e aparece no site público
- Adicionar volume e adicionar coleção (com geração automática de slug)
- Logout invalida a sessão de fato
- Importar catálogo substitui os dados de forma transacional (tudo ou nada)

## 7. Novidade: Cartão de visita digital + vCard (Fase 1)

Duas páginas novas, pensadas pra imprimir um QR Code só e reaproveitar em
tudo (livro, marcador, cartão, banner, assinatura de e-mail):

- **`https://seusite.com/cartao`** — a página com os botões (Salvar
  Contato, WhatsApp, PIX, Catálogo, Loja, Instagram, YouTube,
  Localização etc).
- **`https://seusite.com/vcard`** — gera o arquivo `.vcf` na hora, sempre
  com os dados mais recentes.

Tudo isso é editado em **`/admin/cartao.php`** — dá pra mudar telefone,
WhatsApp, chave PIX, redes sociais, sem tocar em nenhum arquivo. Essa
página também gera o QR Code (aponta pra `/cartao`) com botão de baixar
PNG pra usar nas artes impressas.

**Depois de subir esta atualização, entre em `/admin/cartao.php` e
preencha pelo menos:**
- Chave PIX (está vazia por padrão)
- Número de WhatsApp, se for diferente do que já está lá
- Instagram / YouTube / Localização
- A URL do botão "O Runomante" (deixei em branco de propósito — o botão só
  aparece no cartão quando essa URL for preenchida)
- "Loja" está apontando pro mesmo lugar que "Catálogo" por enquanto — isso
  muda quando a parte de pedidos (Fase 5 do planejamento) estiver pronta

**Sobre a atualização do banco:** se você já tinha subido a versão
anterior e já cadastrou livros de verdade, pode ficar tranquilo — o
código novo adiciona a tabela do cartão automaticamente na primeira
requisição, sem apagar nada do que já existe. Testei exatamente esse
cenário (banco antigo com dados reais + código novo) antes de te entregar.

**Sobre o QR Code:** ele é gerado no navegador por uma biblioteca externa
(qrcodejs, via cdnjs) — se o QR não aparecer em `/admin/cartao.php`, me
avise que ajusto a fonte da biblioteca.

## 8. Se algo der errado

- **Erro 500 em qualquer página**: normalmente é permissão de escrita.
  A pasta onde fica `config.php` (para criar o `catalogo.sqlite`) precisa
  ter permissão de escrita para o PHP — no Hostinger isso já vem certo por
  padrão, mas se der erro, tente definir permissão 755 nessa pasta pelo
  Gerenciador de Arquivos.
- **"Não foi possível carregar o catálogo"** na página inicial: confira se
  `config.php` está mesmo um nível acima de `public_html` e se o caminho
  bate (o Hostinger às vezes usa `domains/seusite.com/public_html`, não
  `public_html` direto em `/home/usuario/`).

## 9. Documentação técnica

Para arquitetura, schema do banco, mapa de páginas e o funcionamento do
deploy, veja a pasta [`docs/`](docs/README.md).
