#Requires -Version 7.0
<#
.SYNOPSIS
    Ponto de entrada do "GitHub Actions Local" - deploy via FTP/FTPS/SFTP.

.PARAMETER DryRun
    Simula o deploy: mostra o que seria enviado, sem transferir nada.

.PARAMETER Force
    Força o reenvio de todos os arquivos, ignorando a comparação incremental.

.PARAMETER Clean
    Reservado para versão futura (limpeza de arquivos remotos órfãos). Atualmente apenas emite aviso.

.PARAMETER Silent
    Suprime a saída no console (o log em arquivo continua sendo gravado normalmente).

.EXAMPLE
    .\deploy.ps1

.EXAMPLE
    .\deploy.ps1 -DryRun -Verbose

.EXAMPLE
    .\deploy.ps1 -Force
#>

[CmdletBinding()]
param(
    [switch]$DryRun,
    [switch]$Force,
    [switch]$Clean,
    [switch]$Silent
)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot

Import-Module (Join-Path $root 'Logger.psm1') -Force
Import-Module (Join-Path $root 'Utils.psm1')  -Force
Import-Module (Join-Path $root 'Ignore.psm1') -Force
Import-Module (Join-Path $root 'Config.psm1') -Force
Import-Module (Join-Path $root 'Ftp.psm1')    -Force
Import-Module (Join-Path $root 'Sync.psm1')   -Force
Import-Module (Join-Path $root 'Deploy.psm1') -Force

$isVerbose = ($VerbosePreference -ne 'SilentlyContinue') -or $PSBoundParameters.ContainsKey('Verbose')

$exitCode = Invoke-Deploy -DeployRoot $root -DryRun:$DryRun -Force:$Force -Clean:$Clean -Silent:$Silent -VerboseMode:$isVerbose

exit $exitCode
