<#
.SYNOPSIS
    Monta o conteúdo do script WinSCP (comandos "open" + "synchronize remote"
    por mapeamento + "exit") a partir da configuração resolvida.

.DESCRIPTION
    Com a mudança pra WinSCP.exe via linha de comando (ver Ftp.psm1), quem decide
    o que precisa ser enviado passou a ser o próprio comando "synchronize
    remote" do WinSCP — não é mais um laço em PowerShell comparando arquivo por
    arquivo. Esta função só monta o TEXTO do script; quem executa é
    Invoke-WinSCPScript (Ftp.psm1).
#>

function New-DeployScript {
    <#
    .SYNOPSIS
        Constrói o script WinSCP completo para uma execução.
    .PARAMETER DryRun
        Quando presente, adiciona "-preview" em cada "synchronize remote" — o
        WinSCP conecta, compara e mostra o que faria, mas não transfere nada.
        Continua sendo uma conexão de rede (só leitura), não uma simulação 100%
        offline — ver docs/deploy-e-patches.md para a diferença.
    #>
    param(
        [Parameter(Mandatory)] $Config,
        [Parameter(Mandatory)][string]$ProjectRoot,
        [switch]$DryRun,
        [switch]$Force
    )

    $url = ConvertTo-WinSCPUrl -Protocol $Config.Protocol -HostName $Config.Host `
        -Username $Config.Username -Password $Config.Password -Port $Config.Port

    $mask = Convert-GlobsToWinSCPMask -Patterns $Config.Exclude

    $lines = @()
    $lines += 'option batch abort'
    $lines += 'option confirm off'
    $lines += 'option transfer binary'

    $openLine = "open $url"
    if ($Config.Protocol.ToLower() -ne 'sftp') {
        $openLine += $(if ($Config.Passive) { ' -passive=on' } else { ' -passive=off' })
    }
    $lines += $openLine

    foreach ($mapping in $Config.Mappings) {
        $fromTrimmed = $mapping.From.TrimStart('.', '/', '\')
        $localPath = if ([string]::IsNullOrWhiteSpace($fromTrimmed)) { $ProjectRoot } else { Join-Path $ProjectRoot $fromTrimmed }

        $toTrimmed = $mapping.To.Trim().TrimStart('.', '/', '\').TrimEnd('/')
        $remotePath = if ([string]::IsNullOrWhiteSpace($toTrimmed)) { '/' } else { '/' + $toTrimmed }

        $syncCmd = 'synchronize remote'
        if ($mask -ne '') { $syncCmd += " -filemask=`"$mask`"" }
        if ($Force) { $syncCmd += ' -criteria=none' }
        if ($DryRun) { $syncCmd += ' -preview' }
        $syncCmd += " `"$localPath`" `"$remotePath`""
        $lines += $syncCmd
    }

    $lines += 'exit'

    return ($lines -join "`r`n")
}

Export-ModuleMember -Function New-DeployScript
