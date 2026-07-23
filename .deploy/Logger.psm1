<#
.SYNOPSIS
    Logger simples: escreve no console (colorido) e em arquivo de log por execução.
#>

function New-DeployLogger {
    <#
    .SYNOPSIS
        Cria um novo contexto de logger, com arquivo de log timestampado.
    #>
    param(
        [Parameter(Mandatory)][string]$LogDir,
        [switch]$Silent,
        [switch]$VerboseMode
    )
    if (-not (Test-Path $LogDir)) {
        New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
    }
    $timestamp = Get-Date -Format 'yyyy-MM-dd_HH-mm-ss'
    $logFile = Join-Path $LogDir "deploy_$timestamp.log"
    New-Item -ItemType File -Path $logFile -Force | Out-Null

    return [PSCustomObject]@{
        LogFile     = $logFile
        Silent      = [bool]$Silent
        VerboseMode = [bool]$VerboseMode
        StartTime   = Get-Date
    }
}

function Write-DeployLog {
    <#
    .SYNOPSIS
        Escreve uma linha de log no console (se aplicável) e sempre no arquivo.
    #>
    param(
        [Parameter(Mandatory)] $Logger,
        [ValidateSet('Info', 'Success', 'Warn', 'Error', 'Verbose', 'Raw')]
        [string]$Level = 'Info',
        [Parameter(Mandatory)][AllowEmptyString()][string]$Message
    )

    $time = Get-Date -Format 'HH:mm:ss'
    $line = if ($Level -eq 'Raw') { $Message } else { "[$time] [$Level] $Message" }

    Add-Content -Path $Logger.LogFile -Value $line -Encoding UTF8

    if ($Level -eq 'Verbose' -and -not $Logger.VerboseMode) { return }
    if ($Logger.Silent -and $Level -notin @('Error')) { return }

    $color = switch ($Level) {
        'Success' { 'Green' }
        'Warn'    { 'Yellow' }
        'Error'   { 'Red' }
        'Verbose' { 'DarkGray' }
        'Raw'     { 'White' }
        default   { 'Gray' }
    }
    Write-Host $line -ForegroundColor $color
}

function Complete-DeployLogger {
    <#
    .SYNOPSIS
        Escreve o resumo final da execução no console e no arquivo de log.
    #>
    param(
        [Parameter(Mandatory)] $Logger,
        [Parameter(Mandatory)][hashtable]$Summary
    )
    $elapsed = (Get-Date) - $Logger.StartTime

    Write-DeployLog -Logger $Logger -Level Raw -Message ''
    Write-DeployLog -Logger $Logger -Level Raw -Message '===== Resumo ====='
    foreach ($key in $Summary.Keys) {
        Write-DeployLog -Logger $Logger -Level Raw -Message ("{0}: {1}" -f $key, $Summary[$key])
    }
    Write-DeployLog -Logger $Logger -Level Raw -Message ("Tempo total: {0:hh\:mm\:ss}" -f $elapsed)
    Write-DeployLog -Logger $Logger -Level Raw -Message '==================='
}

Export-ModuleMember -Function New-DeployLogger, Write-DeployLog, Complete-DeployLogger
