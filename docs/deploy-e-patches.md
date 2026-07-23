# Deploy e patches

## Dois jeitos de publicar

### 1. GitHub Actions (automático a cada push)

`.github/workflows/deploy.yml` dispara em todo push na branch `main`,
publica `public_html/` inteiro via FTP usando a action
`SamKirkland/FTP-Deploy-Action`. Credenciais em Settings → Secrets and
variables → Actions do repositório (`FTP_SERVER`, `FTP_USERNAME`,
`FTP_PASSWORD`).

Pontos importantes da configuração atual:
- `local-dir: ./` e `server-dir: ./` publicam o **repositório inteiro**
  (não só `public_html/`), porque a conta FTP usada aponta pra raiz do
  domínio — isso é o que permite `config.php` e `src/` também irem pro
  lugar certo (fora do `public_html` real) num único deploy. Se a conta
  FTP algum dia mudar pra uma que cai direto dentro de `public_html`,
  isso precisa ser ajustado, senão `config.php`/`src/` ficariam
  expostos publicamente.
- `dangerous-clean-slate: false` — nunca mude isso pra `true`. Com
  `false`, a action nunca apaga do servidor um arquivo que não esteja
  no repositório.
- `exclude` tem `**/*.sqlite` — o banco de produção nunca é tocado por
  um deploy, porque nunca esteve no repositório pra começo de conversa
  (está no `.gitignore`).

### 2. Deploy local (`.deploy/`, PowerShell)

Pra testar sem precisar dar `git push` toda vez. Lê a mesma
configuração do `deploy.yml` (reaproveita host/usuário/exclude de lá),
credenciais ficam só em `.deploy/secrets.json` (nunca commitado — está
no `.gitignore` da própria pasta `.deploy`).

```powershell
# primeira vez: copie o modelo e preencha com as credenciais reais
copy .deploy\secrets-modelo.json .deploy\secrets.json

# testar sem enviar nada:
pwsh -ExecutionPolicy Bypass -File ".\.deploy\deploy.ps1" -DryRun

# deploy de verdade:
pwsh -ExecutionPolicy Bypass -File ".\.deploy\deploy.ps1"
```

**Pegadinha comum:** Windows bloqueia rodar `.ps1` não assinado por
padrão (`UnauthorizedAccess: ... não está assinado digitalmente`). O
`-ExecutionPolicy Bypass` acima resolve pontualmente. Pra não precisar
disso toda vez:

```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

Internamente, essa ferramenta roda o `WinSCP.exe` oficial via linha de
comando (não a assembly .NET `WinSCPnet.dll` — essa quebra no
PowerShell 7 por incompatibilidade binária conhecida). O `-DryRun`
conecta de verdade (é preciso, pra comparar local × remoto e mostrar um
plano real), mas só em modo leitura (`-preview` do WinSCP) — nenhum
upload, criação de pasta ou exclusão acontece nesse modo. A opção
`-Clean` (remoção de arquivo remoto órfão) existe na interface mas está
deliberadamente desativada, "reservado para versão futura" — não existe
caminho de código que apague o `catalogo.sqlite` de produção. Detalhes
completos, incluindo por que a reescrita foi necessária, em
`.deploy/README.md`.

## Patches (`git apply` vs `git am`)

Ajustes de código chegam como arquivo `.patch` (gerado por
`git diff`), não como zip do projeto inteiro — assim dá pra revisar
exatamente o que muda antes de aplicar.

```bash
git apply nome-do-arquivo.patch
```

**Não use `git am`** nesses arquivos — `git am` espera um patch em
formato "e-mail" (com cabeçalho de commit/autor), e vai falhar com
`Patch format detection failed.` num diff comum gerado por
`git diff`. Se preferir esse formato (cada patch já vira um commit
pronto, com mensagem e autor), é possível gerar assim em vez de com
`git diff` — combine antecipadamente qual formato usar antes de pedir
o próximo ajuste.

### Se o patch não aplicar

O erro mais comum é a base estar diferente do esperado — por exemplo,
aplicar um patch pensado pra depois da Fase 2 num repositório que ainda
não tem a Fase 2. `git apply --check arquivo.patch` mostra se aplicaria
limpo sem alterar nada, útil pra checar antes.

Em caso de erro, o mais rápido é colar a mensagem completa de volta —
os erros de patch são bem específicos sobre qual arquivo e linha não
bateram, então normalmente a causa fica clara na hora.
