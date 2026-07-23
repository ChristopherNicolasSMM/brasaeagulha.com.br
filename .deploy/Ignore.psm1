<#
.SYNOPSIS
    Converte padrões de exclusão estilo GitHub Actions (**, *, ?) para a sintaxe
    de máscara de arquivo do WinSCP.

.DESCRIPTION
    A partir da reescrita em torno do winscp.com/WinSCP.exe (ver Ftp.psm1), quem
    decide o que sincronizar passou a ser o próprio WinSCP (comando "synchronize
    remote -filemask=..."), não mais um laço local em PowerShell. Por isso esta
    função não retorna mais regex — ela monta diretamente a string de máscara que
    o WinSCP entende.

    Diferença de semântica entre os dois formatos (documentada aqui porque não é
    óbvia): no WinSCP, uma máscara de nome sem barra (ex. "*.sqlite") já casa em
    QUALQUER profundidade de diretório durante uma sincronização — synchronize
    processa tudo recursivamente por padrão. Ou seja, o prefixo "**/" do GitHub
    Actions é redundante no WinSCP e pode ser removido. Da mesma forma, excluir um
    diretório (ex. ".git*") já exclui tudo dentro dele automaticamente, então um
    padrão do tipo "**/.git*/**" também é redundante e é descartado.
    Fonte: https://winscp.net/eng/docs/file_mask

    Padrões com "**" no MEIO (ex. "foo/**/bar") não têm equivalente direto e são
    mantidos como estão (com o "**" trocado por "*"), sem garantia de
    comportamento idêntico — nenhum padrão usado neste projeto até agora se
    encaixa nesse caso.
#>

function Convert-GlobsToWinSCPMask {
    <#
    .SYNOPSIS
        Recebe a lista de padrões (do config.Exclude) e retorna uma única string
        de máscara WinSCP, pronta para usar em "-filemask=...".
    #>
    param([string[]]$Patterns = @())

    $cleaned = @()
    foreach ($p in $Patterns) {
        if ([string]::IsNullOrWhiteSpace($p)) { continue }
        $m = $p.Trim()

        # "**/" no início é implícito no WinSCP (máscara sem barra já é recursiva) — remove.
        $m = $m -replace '^\*\*/', ''
        # "/**" no final é redundante (excluir o diretório já exclui o conteúdo) — remove.
        $m = $m -replace '/\*\*$', ''
        # "**" restante no meio do padrão: sem equivalente direto, vira "*" (aproximação).
        $m = $m -replace '\*\*', '*'

        if ($m -ne '' -and ($cleaned -notcontains $m)) {
            $cleaned += $m
        }
    }

    if ($cleaned.Count -eq 0) { return '' }

    # Máscara vazia de inclusão (= tudo) + "|" + lista de exclusão separada por ";"
    return '| ' + ($cleaned -join '; ')
}

Export-ModuleMember -Function Convert-GlobsToWinSCPMask
