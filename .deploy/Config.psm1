<#
.SYNOPSIS
    Lê .github/workflows/deploy.yml e produz um objeto de configuração normalizado.

.DESCRIPTION
    Suporta dois formatos:
      1) Uma seção customizada de nível superior "deploy:" (formato descrito no README).
      2) Fallback automático: se "deploy:" não existir, procura um step que use
         "SamKirkland/FTP-Deploy-Action" e extrai a configuração do bloco "with:".
         Isso permite reaproveitar o workflow que você já tem, sem editar nada.

    Depois do parse, TODA a estrutura (incluindo listas e objetos aninhados, como
    "mappings" e "exclude") passa por Resolve-Variables, que substitui:
      - ${{ secrets.NOME }}   (sintaxe de expressão do GitHub Actions — é o formato
        que aparece de verdade quando o "deploy:" é lido a partir do step do
        FTP-Deploy-Action, já que os valores do "with:" são copiados literalmente)
      - ${NOME}               (formato legado, mantido por compatibilidade)
    Nessa ordem de prioridade: secrets.json -> variável de ambiente do Windows ->
    string vazia (com aviso no log, sem lançar exceção).
#>

function Resolve-Variables {
    <#
    .SYNOPSIS
        Substitui recursivamente ${{ secrets.NOME }} / ${NOME} em qualquer string
        dentro de uma estrutura (hashtable/dicionário, array, ou valor escalar).
    #>
    param(
        $InputObject,
        [Parameter(Mandatory)][hashtable]$Secrets,
        $Logger = $null
    )

    if ($null -eq $InputObject) { return $InputObject }

    if ($InputObject -is [string]) {
        $pattern = '\$\{\{\s*secrets\.(\w+)\s*\}\}|\$\{(\w+)\}'
        return [regex]::Replace($InputObject, $pattern, {
            param($m)
            $name = if ($m.Groups[1].Success) { $m.Groups[1].Value } else { $m.Groups[2].Value }
            if ($Secrets.ContainsKey($name)) { return [string]$Secrets[$name] }
            $envVal = [System.Environment]::GetEnvironmentVariable($name)
            if ($envVal) { return $envVal }
            $msg = "Aviso: variável '$name' não encontrada em secrets.json nem nas variáveis de ambiente."
            if ($Logger) { Write-DeployLog -Logger $Logger -Level Warn -Message $msg }
            else { Write-Host $msg -ForegroundColor Yellow }
            return ''
        })
    }

    if ($InputObject -is [System.Collections.IDictionary]) {
        $result = [ordered]@{}
        foreach ($key in $InputObject.Keys) {
            $result[$key] = Resolve-Variables -InputObject $InputObject[$key] -Secrets $Secrets -Logger $Logger
        }
        return $result
    }

    if (($InputObject -is [System.Collections.IEnumerable]) -and (-not ($InputObject -is [string]))) {
        $result = @()
        foreach ($item in $InputObject) {
            $result += , (Resolve-Variables -InputObject $item -Secrets $Secrets -Logger $Logger)
        }
        return $result
    }

    return $InputObject
}

function Import-DeployConfig {
    param(
        [Parameter(Mandatory)][string]$WorkflowPath,
        [Parameter(Mandatory)][string]$SecretsPath,
        $Logger = $null
    )

    if (-not (Get-Module -ListAvailable -Name powershell-yaml)) {
        Write-Host "Módulo 'powershell-yaml' não encontrado. Instalando (uma única vez)..." -ForegroundColor Yellow
        Install-Module -Name powershell-yaml -Scope CurrentUser -Force -AllowClobber
    }
    Import-Module powershell-yaml -Force

    if (-not (Test-Path $WorkflowPath)) {
        throw "Workflow não encontrado em: $WorkflowPath"
    }

    $raw = Get-Content $WorkflowPath -Raw
    $doc = ConvertFrom-Yaml $raw -Ordered

    $deployNode = $null

    if ($doc -is [System.Collections.IDictionary] -and $doc.Contains('deploy')) {
        # Formato 1: seção customizada "deploy:" no topo do arquivo
        $deployNode = $doc['deploy']
    }
    else {
        # Formato 2 (fallback): procurar step do SamKirkland/FTP-Deploy-Action
        $steps = @()
        if ($doc.Contains('jobs')) {
            foreach ($jobKey in $doc['jobs'].Keys) {
                $job = $doc['jobs'][$jobKey]
                if ($job.Contains('steps')) { $steps += $job['steps'] }
            }
        }
        $ftpStep = $steps | Where-Object {
            $_.Contains('uses') -and $_['uses'] -match 'FTP-Deploy-Action'
        } | Select-Object -First 1

        if (-not $ftpStep) {
            throw "Nenhuma seção 'deploy:' nem step do FTP-Deploy-Action foi encontrada em $WorkflowPath"
        }

        $with = $ftpStep['with']
        $excludeList = @()
        if ($with.Contains('exclude') -and $with['exclude']) {
            $excludeList = ($with['exclude'] -split "`n") |
                ForEach-Object { $_.Trim() } |
                Where-Object { $_ -ne '' }
        }

        $isClean = $false
        if ($with.Contains('dangerous-clean-slate')) {
            $isClean = ($with['dangerous-clean-slate'] -eq 'true') -or ($with['dangerous-clean-slate'] -eq $true)
        }

        $deployNode = [ordered]@{
            protocol = $with['protocol']
            host     = $with['server']
            username = $with['username']
            password = $with['password']
            clean    = $isClean
            mappings = @(
                [ordered]@{
                    from = $(if ($with.Contains('local-dir')) { $with['local-dir'] } else { './' })
                    to   = $(if ($with.Contains('server-dir')) { $with['server-dir'] } else { '/' })
                }
            )
            exclude  = $excludeList
        }
    }

    $secrets = @{}
    if (Test-Path $SecretsPath) {
        try {
            $secrets = Get-Content $SecretsPath -Raw | ConvertFrom-Json -AsHashtable
        } catch {
            Write-Host "Aviso: não foi possível ler secrets.json ($($_.Exception.Message))" -ForegroundColor Yellow
        }
    }

    # Substitui ${{ secrets.NOME }} e ${NOME} em TODA a estrutura (incluindo
    # mappings/exclude aninhados), não só nos campos escalares de topo.
    $deployNode = Resolve-Variables -InputObject $deployNode -Secrets $secrets -Logger $Logger

    $mappings = @()
    foreach ($m in $deployNode['mappings']) {
        $mappings += [PSCustomObject]@{
            From = [string]$m['from']
            To   = [string]$m['to']
        }
    }
    if ($mappings.Count -eq 0) {
        $mappings = @([PSCustomObject]@{ From = './'; To = '/' })
    }

    $port = 21
    if ($deployNode.Contains('port') -and $deployNode['port']) { $port = [int]$deployNode['port'] }

    $retry = 3
    if ($deployNode.Contains('retry') -and $deployNode['retry']) { $retry = [int]$deployNode['retry'] }

    $timeout = 30
    if ($deployNode.Contains('timeout') -and $deployNode['timeout']) { $timeout = [int]$deployNode['timeout'] }

    $config = [PSCustomObject]@{
        Protocol  = [string]$deployNode['protocol']
        Host      = [string]$deployNode['host']
        Username  = [string]$deployNode['username']
        Password  = [string]$deployNode['password']
        Port      = $port
        Clean     = [bool]$deployNode['clean']
        Passive   = $(if ($deployNode.Contains('passive')) { [bool]$deployNode['passive'] } else { $true })
        Overwrite = $(if ($deployNode.Contains('overwrite')) { [bool]$deployNode['overwrite'] } else { $true })
        Retry     = $retry
        Timeout   = $timeout
        Mappings  = $mappings
        Exclude   = @($deployNode['exclude'])
    }

    if ([string]::IsNullOrWhiteSpace($config.Protocol)) { $config.Protocol = 'ftps' }

    return $config
}

Export-ModuleMember -Function Import-DeployConfig, Resolve-Variables
