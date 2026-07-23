# Brasa & Agulha Editorial — Documentação técnica

Este é o site institucional, catálogo e cartão de visita digital da
Brasa & Agulha Editorial. Esta pasta documenta como o projeto funciona
por dentro — para uso de quem for mexer no código depois (você mesmo,
em seis meses, ou qualquer outra pessoa/IA que assumir o projeto).

## Índice

- [`arquitetura.md`](arquitetura.md) — pastas, padrões de código, como a
  migração de banco funciona
- [`banco-de-dados.md`](banco-de-dados.md) — cada tabela do SQLite, o
  que guarda, como se relaciona
- [`paginas.md`](paginas.md) — mapa de toda página e endpoint do site
- [`deploy-e-patches.md`](deploy-e-patches.md) — como publicar mudanças,
  os dois jeitos de deploy, como aplicar patches

## Em uma frase

PHP puro (sem framework) + SQLite, hospedado em hospedagem compartilhada
(Hostinger), com um painel administrativo próprio protegido por sessão
e senha com hash — sem dependências pesadas, sem build step, sem
Composer. O objetivo desde o início foi rodar em qualquer hospedagem PHP
comum, sem exigir nada além do que já vem instalado por padrão.

## Estrutura de pastas (visão rápida)

```
.
├── config.php          # constantes de configuração — FICA FORA do webroot
├── src/                 # lógica compartilhada (banco, sessão, CSRF) — TAMBÉM fora do webroot
├── public_html/         # tudo aqui é servido pela web — este é o document root real
│   ├── admin/           # painel administrativo (exige login)
│   ├── api/              # endpoints JSON (públicos e administrativos)
│   ├── css/, js/, img/   # front-end
│   └── *.php, *.html     # páginas públicas (index, cartão, vCard, autor)
├── docs/                 # você está aqui
└── .deploy/              # ferramenta de deploy local (PowerShell), opcional
```

O porquê de `config.php` e `src/` ficarem fora de `public_html/` está
explicado em [`arquitetura.md`](arquitetura.md) — resumindo: é o que
impede que alguém baixe o banco de dados ou leia a lógica do servidor
direto por uma URL.

## Histórico de fases (contexto de por que as coisas são como são)

1. **Fase 1** — catálogo, painel admin com autenticação, cartão de
   visita digital (`/cartao`) e vCard dinâmico (`/vcard`).
2. **Fase 2** — metadados de livro no padrão de livraria (ISBN, idioma,
   páginas, data de publicação) e autores com página pública própria.
3. **Fase 3** — painel administrativo redesenhado (lista + pop-up em vez
   de formulários abertos), central de "Páginas" com os links que não
   aparecem em nenhum menu.

Próximas fases planejadas (ainda não construídas): política de
privacidade/LGPD, conta de cliente com múltiplos endereços, e pedidos
com pagamento via PIX confirmado por WhatsApp.
