<#
.SYNOPSIS
    Funções utilitárias compartilhadas pelo framework de deploy local.
#>

function ConvertTo-UnixPath {
    <#
    .SYNOPSIS
        Converte um caminho Windows (com \) para o formato Unix (com /).
    #>
    param([Parameter(Mandatory)][string]$Path)
    return ($Path -replace '\\', '/')
}

function Join-UnixPath {
    <#
    .SYNOPSIS
        Junta dois segmentos de caminho remoto usando '/' e evita barras duplicadas.
    #>
    param(
        [Parameter(Mandatory)][string]$Base,
        [Parameter(Mandatory)][string]$Child
    )
    $b = ($Base -replace '\\', '/').TrimEnd('/')
    $c = ($Child -replace '\\', '/').TrimStart('/')
    if ([string]::IsNullOrEmpty($b)) { return "/$c" }
    return "$b/$c"
}

function Get-RelativePath {
    <#
    .SYNOPSIS
        Retorna o caminho relativo (estilo Unix) de $Full em relação a $Base.
    #>
    param(
        [Parameter(Mandatory)][string]$Full,
        [Parameter(Mandatory)][string]$Base
    )
    $fullUri = New-Object System.Uri((Resolve-Path $Full).Path)
    $baseUri = New-Object System.Uri(((Resolve-Path $Base).Path.TrimEnd('\','/') + [System.IO.Path]::DirectorySeparatorChar))
    $rel = $baseUri.MakeRelativeUri($fullUri).ToString()
    return [System.Uri]::UnescapeDataString($rel)
}

function Format-FileSize {
    <#
    .SYNOPSIS
        Formata um tamanho em bytes de forma legível (KB, MB, GB).
    #>
    param([Parameter(Mandatory)][long]$Bytes)
    $units = @('B','KB','MB','GB','TB')
    $size = [double]$Bytes
    $unitIndex = 0
    while ($size -ge 1024 -and $unitIndex -lt $units.Length - 1) {
        $size = $size / 1024
        $unitIndex++
    }
    return "{0:N2} {1}" -f $size, $units[$unitIndex]
}

function Format-Duration {
    <#
    .SYNOPSIS
        Formata um TimeSpan como HH:mm:ss.
    #>
    param([Parameter(Mandatory)][TimeSpan]$Span)
    return ('{0:hh\:mm\:ss}' -f $Span)
}

Export-ModuleMember -Function ConvertTo-UnixPath, Join-UnixPath, Get-RelativePath, Format-FileSize, Format-Duration
