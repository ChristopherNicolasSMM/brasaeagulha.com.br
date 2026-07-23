# Deploy Local (GitHub Actions Local)

Framework local em PowerShell 7+ para publicar via FTP/FTPS/SFTP sem depender do GitHub Actions,
reaproveitando a configuração que já existe em `.github/workflows/deploy.yml`.

## Como funciona no seu projeto

Você **não precisa editar** seu `deploy.yml` atual. Na primeira execução, o script:

1. Procura uma seção customizada `deploy:` no topo do arquivo (formato avançado, opcional).
2. Se não existir, procura automaticamente o step que usa `SamKirkland/FTP-Deploy-Action`
   e extrai `server`, `username`, `password`, `protocol`, `local-dir`, `server-dir` e `exclude`
   de dentro do `with:` — exatamente o workflow que você já tem hoje.
3. Substitui `${{ secrets.NOME }}` (a sintaxe real que aparece quando o workflow é lido dessa
   forma) e o formato legado `${NOME}`, buscando em `secrets.json` e depois nas variáveis de
   ambiente do Windows — em toda a configuração, incluindo `mappings` e `exclude`.

## Passo a passo

1. Copie a pasta `.deploy` para a raiz do seu projeto (ao lado de `.github`).
2. Preencha `.deploy/secrets.json`:

   ```json
   {
       "FTP_SERVER": "ftp.seusite.com",
       "FTP_USERNAME": "usuario",
       "FTP_PASSWORD": "senha"
   }
   ```

   > ⚠️ Nunca commite esse arquivo. Já existe um `.deploy/.gitignore` cuidando disso.

3. Execute a partir da raiz do projeto (ou de dentro de `.deploy`):

   ```powershell
   .\.deploy\deploy.ps1 -DryRun
   ```

   Na primeira execução, o script baixa automaticamente `WinSCP.exe` (via pacote oficial do
   NuGet) para `.deploy\lib`. As próximas execuções não precisam mais baixar nada.

   > **Nota:** o script exige PowerShell 7+. Se `pwsh --version` não for reconhecido, instale
   > primeiro (`winget install --id Microsoft.PowerShell -e`), abra um terminal novo, confirme
   > com `pwsh --version`, e só então rode `pwsh -ExecutionPolicy Bypass -File ".\.deploy\deploy.ps1" -DryRun`.

## Opções de linha de comando

| Comando                        | Efeito                                                                    |
|---------------------------------|----------------------------------------------------------------------------|
| `.\deploy.ps1`                 | Deploy incremental normal (o WinSCP decide o que mudou, por data/tamanho)  |
| `.\deploy.ps1 -Verbose`        | Mostra o script gerado (com a senha mascarada) e mais detalhes            |
| `.\deploy.ps1 -DryRun`         | Conecta, compara e mostra o que seria enviado — **não envia nada**        |
| `.\deploy.ps1 -Force`          | Reenvia tudo, ignorando a comparação por data/tamanho (`-criteria=none`)  |
| `.\deploy.ps1 -Silent`         | Não imprime no console (o log em arquivo continua sendo gravado)          |
| `.\deploy.ps1 -Clean`          | Reservado para versão futura (hoje apenas emite um aviso)                 |

> pwsh -ExecutionPolicy Bypass -File ".\.deploy\deploy.ps1" -DryRun

### Sobre o `-DryRun`

Ele **conecta de verdade** ao servidor (é preciso pra comparar local × remoto e mostrar um plano
real), mas usa a opção `-preview` do WinSCP — nenhum upload, criação de pasta ou exclusão
acontece. É uma conexão só de leitura. Não existe hoje um modo 100% offline (que comparasse só
contra um cache local salvo de uma execução anterior); se isso um dia for necessário, é uma opção
nova a adicionar (`-Offline`), documentada aqui como ideia em aberto.

## Logs

Cada execução grava em `.deploy/logs/`:
- `deploy_AAAA-MM-DD_HH-mm-ss.log` — log desta ferramenta (as mesmas mensagens que aparecem no console)
- `winscp_AAAAMMDD_HHmmss.log` — log de texto do próprio WinSCP (útil pra depurar problema de conexão)
- `winscp_AAAAMMDD_HHmmss.xml` — log estruturado do WinSCP, usado pra montar o resumo (arquivos enviados/falhas)

O script de comandos gerado para o WinSCP é temporário e **sempre apagado** ao final da execução
(mesmo se der erro) — ele contém a senha em texto puro enquanto existe, então nunca fica no disco
por mais tempo que o necessário. A senha nunca é gravada em nenhum dos três logs acima.

## Formato avançado: seção `deploy:` própria

Se preferir não depender do parsing do `FTP-Deploy-Action`, adicione no topo do
`deploy.yml` (fora de `jobs:`) uma seção assim, que passa a ter prioridade:

```yaml
deploy:
  protocol: ftps
  host: ${{ secrets.FTP_SERVER }}
  username: ${{ secrets.FTP_USERNAME }}
  password: ${{ secrets.FTP_PASSWORD }}
  port: 21
  clean: false
  passive: true
  overwrite: true
  retry: 3
  timeout: 30
  mappings:
    - from: ./
      to: /
  exclude:
    - "**/.git*"
    - "**/.github/**"
    - "**/*.sqlite"
    - "**/*.sqlite3"
    - "**/node_modules/**"
    - "**/.vscode/**"
    - "**/*.log"
```

Tanto `${{ secrets.NOME }}` quanto `${NOME}` funcionam aqui, resolvidos a partir de
`.deploy/secrets.json` e, se não encontrados lá, das variáveis de ambiente do Windows.

## Estrutura de módulos

```
.deploy/
├── deploy.ps1     - ponto de entrada (CLI)
├── Deploy.psm1    - orquestração: config -> validação -> script -> executar -> resumo
├── Config.psm1    - leitura/normalização do YAML + Resolve-Variables (${{ secrets.X }} / ${X})
├── Ftp.psm1       - baixa/localiza WinSCP.exe e executa via linha de comando
├── Sync.psm1      - monta o texto do script WinSCP (comandos "synchronize remote")
├── Ignore.psm1    - converte padrões de exclusão estilo GitHub para máscara do WinSCP
├── Logger.psm1    - log em console + arquivo
├── Utils.psm1     - helpers de caminho/formatação (reservado para extensões futuras)
├── secrets.json   - credenciais locais (gitignored)
└── logs/          - logs de cada execução (gitignored)
```

### Por que WinSCP.exe via linha de comando, e não a assembly .NET

Versões anteriores desta ferramenta usavam `WinSCPnet.dll` (a assembly .NET oficial do WinSCP)
via `Add-Type`. Isso quebra no PowerShell 7 com um erro de "Method not found" envolvendo
`System.Threading.EventWaitHandle` — é uma incompatibilidade binária conhecida entre essa
assembly (compilada contra .NET Framework clássico) e o runtime usado pelo PowerShell 7+. Não há
correção mantendo a assembly, então a ferramenta passou a rodar `WinSCP.exe` como processo externo
(mesmo pacote NuGet, só que extraindo o executável em vez da DLL), lendo os logs que ele gera em
vez de usar objetos .NET com resultado estruturado.

## Limitações desta versão / próximos passos

Já preparado na arquitetura, mas ainda não implementado:

- `-Clean` (remoção de arquivos remotos órfãos) — hoje só avisa, não apaga nada, por segurança.
- Modo `-DryRun -Offline` 100% sem rede (comparando contra um cache local de execução anterior).
- Upload paralelo, múltiplos servidores, hooks `preDeploy`/`postDeploy`, execução de comandos SSH
  (o WinSCP já suporta nativamente via `call` em sessões SFTP), compressão ZIP antes do envio,
  backup/rollback, publicação seletiva por ambiente, plugins.

Esses itens dá pra ir adicionando incrementalmente sem quebrar a estrutura atual.

## Requisitos

- Windows com PowerShell 7+ (`#Requires -Version 7.0`) — `pwsh --version` pra conferir
- Acesso à internet na primeira execução (para baixar `powershell-yaml` via PSGallery e
  `WinSCP.exe` via NuGet)
