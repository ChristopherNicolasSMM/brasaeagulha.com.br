<#
.SYNOPSIS
    Comunicação com o servidor remoto via linha de comando do WinSCP (WinSCP.exe
    /script=...), em vez da assembly .NET (WinSCPnet.dll).

.DESCRIPTION
    Motivo da mudança: WinSCPnet.dll é compilada contra .NET Framework clássico e
    quebra em PowerShell 7 com erro de "Method not found" envolvendo
    System.Threading.EventWaitHandle — incompatibilidade binária conhecida entre
    essa assembly e o runtime .NET usado pelo PowerShell 7+. Não há correção
    limpa mantendo a assembly; a solução é rodar o executável do WinSCP como
    processo externo.

    O pacote NuGet "WinSCP" inclui WinSCP.exe (não inclui WinSCP.com — só o .exe
    mesmo, confirmado na documentação oficial do pacote). WinSCP.exe aceita
    exatamente os mesmos parâmetros de scripting (/script=, /log=, /xmllog=,
    /ini=), então funciona da mesma forma para este propósito.
#>

function Get-WinSCPExecutable {
    <#
    .SYNOPSIS
        Garante que WinSCP.exe exista em $LibDir, baixando via NuGet se necessário.
    #>
    param([Parameter(Mandatory)][string]$LibDir)

    $exePath = Join-Path $LibDir 'WinSCP.exe'
    if (Test-Path $exePath) { return $exePath }

    Write-Host "WinSCP.exe não encontrado. Baixando pacote oficial (uma única vez)..." -ForegroundColor Yellow
    if (-not (Test-Path $LibDir)) { New-Item -ItemType Directory -Path $LibDir -Force | Out-Null }

    $tempZip = Join-Path $env:TEMP "winscp_$(Get-Random).zip"
    $extractDir = Join-Path $env:TEMP "winscp_extract_$(Get-Random)"

    try {
        Invoke-WebRequest -Uri 'https://www.nuget.org/api/v2/package/WinSCP' -OutFile $tempZip -UseBasicParsing
        Expand-Archive -Path $tempZip -DestinationPath $extractDir -Force

        $found = Get-ChildItem -Path $extractDir -Recurse -Filter 'WinSCP.exe' | Select-Object -First 1
        if (-not $found) { throw "Não foi possível localizar WinSCP.exe dentro do pacote NuGet baixado." }
        Copy-Item $found.FullName -Destination $exePath -Force
    } finally {
        Remove-Item $tempZip, $extractDir -Recurse -Force -ErrorAction SilentlyContinue
    }

    return $exePath
}

function ConvertTo-WinSCPUrl {
    <#
    .SYNOPSIS
        Monta a URL de sessão do WinSCP (usada no comando "open"), com
        usuário/senha escapados corretamente e o esquema certo por protocolo.
    #>
    param(
        [Parameter(Mandatory)][string]$Protocol,
        [Parameter(Mandatory)][string]$HostName,
        [Parameter(Mandatory)][string]$Username,
        [Parameter(Mandatory)][string]$Password,
        [int]$Port = 21
    )

    # ftps aqui sempre significa FTPS explícito (é o que o FTP-Deploy-Action e a
    # porta 21 implicam) — WinSCP usa o esquema "ftpes://" pra isso.
    $scheme = switch ($Protocol.ToLower()) {
        'sftp' { 'sftp' }
        'ftps' { 'ftpes' }
        'ftp'  { 'ftp' }
        default { throw "Protocolo não suportado: $Protocol (use ftp, ftps ou sftp)" }
    }

    $user = [uri]::EscapeDataString($Username)
    $pass = [uri]::EscapeDataString($Password)
    return "${scheme}://${user}:${pass}@${HostName}:${Port}/"
}

function ConvertFrom-WinSCPXmlLog {
    <#
    .SYNOPSIS
        Extrai contagem de sucesso/falha do log XML do WinSCP (/xmllog=).
        Retorna $null se o arquivo não existir ou não puder ser interpretado —
        nesse caso, o chamador deve recorrer ao log de texto (/log=) como
        referência bruta, já que o XML não é gerado em todo cenário (ex.: uma
        sessão que falha antes de abrir pode não produzir XML nenhum).
    #>
    param([Parameter(Mandatory)][string]$Path)

    if (-not (Test-Path $Path)) { return $null }

    try {
        [xml]$xml = Get-Content -Path $Path -Raw
    } catch {
        return $null
    }

    $ns = New-Object System.Xml.XmlNamespaceManager($xml.NameTable)
    $ns.AddNamespace('w', 'http://winscp.net/schema/session/1.0')

    # Aceita tanto o namespace http quanto https (documentação já usou os dois
    # em versões diferentes do WinSCP).
    $ops = $xml.SelectNodes('//*[local-name()="upload" or local-name()="mkdir" or local-name()="rm" or local-name()="mv" or local-name()="chmod" or local-name()="touch"]')

    $uploaded = 0
    $failed = 0
    $failMessages = @()

    foreach ($op in $ops) {
        $result = $op.SelectSingleNode('*[local-name()="result"]')
        $success = $result -and $result.success -eq 'true'
        if ($op.LocalName -eq 'upload') {
            if ($success) { $uploaded++ } else { $failed++ }
        }
        if (-not $success -and $result) {
            $msgNode = $result.SelectSingleNode('*[local-name()="message"]')
            if ($msgNode) { $failMessages += $msgNode.InnerText }
        }
    }

    return [PSCustomObject]@{
        Uploaded     = $uploaded
        Failed       = $failed
        FailMessages = $failMessages
    }
}

function Invoke-WinSCPScript {
    <#
    .SYNOPSIS
        Roda WinSCP.exe com o conteúdo de script informado, capturando log de
        texto e log XML. Não usa nenhum objeto .NET do WinSCP — só invoca o
        processo e lê os arquivos de log gerados.
    #>
    param(
        [Parameter(Mandatory)][string]$WinSCPExePath,
        [Parameter(Mandatory)][string]$ScriptContent,
        [Parameter(Mandatory)][string]$LogDir,
        [Parameter(Mandatory)] $Logger,
        [int]$Retry = 3
    )

    if (-not (Test-Path $LogDir)) { New-Item -ItemType Directory -Path $LogDir -Force | Out-Null }

    $stamp = Get-Date -Format 'yyyyMMdd_HHmmss'
    $scriptPath = Join-Path $LogDir "_script_$stamp.txt"
    $textLogPath = Join-Path $LogDir "winscp_$stamp.log"
    $xmlLogPath = Join-Path $LogDir "winscp_$stamp.xml"

    # UTF8 sem BOM — WinSCP às vezes lida mal com o BOM em arquivos de script.
    [System.IO.File]::WriteAllText($scriptPath, $ScriptContent, [System.Text.UTF8Encoding]::new($false))

    $attempt = 0
    $maxAttempts = [Math]::Max(1, $Retry)
    $exitCode = -1

    try {
        while ($true) {
            $attempt++
            Write-DeployLog -Logger $Logger -Level Info -Message "Executando WinSCP (tentativa $attempt/$maxAttempts)..."

            & $WinSCPExePath "/script=$scriptPath" "/log=$textLogPath" "/xmllog=$xmlLogPath" "/ini=nul"
            $exitCode = $LASTEXITCODE

            if ($exitCode -eq 0) { break }
            Write-DeployLog -Logger $Logger -Level Warn -Message "WinSCP saiu com código $exitCode."
            if ($attempt -ge $maxAttempts) { break }
            Start-Sleep -Seconds 2
        }
    } finally {
        # Sempre remove o script temporário (contém a senha em texto puro) —
        # mesmo se a chamada acima lançar um erro inesperado.
        Remove-Item -Path $scriptPath -ErrorAction SilentlyContinue
    }

    $parsed = ConvertFrom-WinSCPXmlLog -Path $xmlLogPath

    return [PSCustomObject]@{
        ExitCode    = $exitCode
        Success     = ($exitCode -eq 0)
        TextLogPath = $textLogPath
        XmlLogPath  = $xmlLogPath
        Parsed      = $parsed
    }
}

Export-ModuleMember -Function Get-WinSCPExecutable, ConvertTo-WinSCPUrl, ConvertFrom-WinSCPXmlLog, Invoke-WinSCPScript
