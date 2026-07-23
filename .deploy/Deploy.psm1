<#
.SYNOPSIS
    Orquestra o fluxo completo: ler config -> montar script -> (Dry Run: mostrar
    e parar aqui) -> executar via WinSCP -> resumir.
#>

function Invoke-Deploy {
    param(
        [Parameter(Mandatory)][string]$DeployRoot,
        [switch]$DryRun,
        [switch]$Force,
        [switch]$Clean,
        [switch]$Silent,
        [switch]$VerboseMode
    )

    $projectRoot = Split-Path $DeployRoot -Parent
    $logDir = Join-Path $DeployRoot 'logs'
    $logger = New-DeployLogger -LogDir $logDir -Silent:$Silent -VerboseMode:$VerboseMode

    Write-DeployLog -Logger $logger -Level Raw -Message '===== Deploy Local ====='
    Write-DeployLog -Logger $logger -Level Info -Message "Projeto: $projectRoot"
    if ($DryRun) {
        Write-DeployLog -Logger $logger -Level Warn -Message 'Modo Dry Run: conecta só para comparar (-preview), nenhum arquivo é enviado, criado ou apagado.'
    }
    if ($Clean) {
        Write-DeployLog -Logger $logger -Level Warn -Message 'A opção -Clean ainda não remove arquivos remotos nesta versão (protegido por segurança).'
    }

    try {
        $workflowPath = Join-Path $projectRoot '.github/workflows/deploy.yml'
        $secretsPath = Join-Path $DeployRoot 'secrets.json'

        # 1) Ler YAML + resolver ${{ secrets.X }} / ${X} — feito dentro de
        #    Import-DeployConfig, ANTES de qualquer validação ou conexão.
        $config = Import-DeployConfig -WorkflowPath $workflowPath -SecretsPath $secretsPath -Logger $logger

        # 2) Validar configuração obrigatória.
        if ([string]::IsNullOrWhiteSpace($config.Host) -or [string]::IsNullOrWhiteSpace($config.Username)) {
            throw "Configuração incompleta: host/usuário não resolvidos. Verifique .deploy\secrets.json."
        }
        if ([string]::IsNullOrWhiteSpace($config.Password)) {
            Write-DeployLog -Logger $logger -Level Warn -Message 'Senha vazia após resolução — a conexão provavelmente vai falhar. Confira .deploy\secrets.json.'
        }

        Write-DeployLog -Logger $logger -Level Info -Message "Servidor: $($config.Host)"
        Write-DeployLog -Logger $logger -Level Info -Message "Usuário: $($config.Username)"
        Write-DeployLog -Logger $logger -Level Info -Message "Protocolo: $($config.Protocol)"

        # 3) Montar o script do WinSCP (não conecta ainda — isso é só texto).
        $script = New-DeployScript -Config $config -ProjectRoot $projectRoot -DryRun:$DryRun -Force:$Force
        $maskedScript = if ($config.Password) { $script -replace [regex]::Escape($config.Password), '********' } else { $script }
        Write-DeployLog -Logger $logger -Level Verbose -Message "Script gerado:`n$maskedScript"

        # 4) Só a partir daqui existe qualquer conexão de rede — e mesmo em Dry
        #    Run, é uma conexão somente leitura (-preview no synchronize), sem
        #    upload/mkdir/delete. Nada acontece por escrito antes deste ponto.
        $libDir = Join-Path $DeployRoot 'lib'
        $winscpExe = Get-WinSCPExecutable -LibDir $libDir

        $result = Invoke-WinSCPScript -WinSCPExePath $winscpExe -ScriptContent $script -LogDir $logDir -Logger $logger -Retry $config.Retry

        $summary = [ordered]@{
            'Código de saída' = $result.ExitCode
            'Log completo'    = $result.TextLogPath
        }
        if ($result.Parsed) {
            $summary['Arquivos enviados'] = $result.Parsed.Uploaded
            $summary['Falhas']            = $result.Parsed.Failed
            if ($result.Parsed.FailMessages.Count -gt 0) {
                $summary['Mensagens de erro'] = ($result.Parsed.FailMessages -join ' | ')
            }
        } else {
            $summary['Aviso'] = 'Não foi possível interpretar o log XML — confira o log completo acima para detalhes.'
        }

        Complete-DeployLogger -Logger $logger -Summary $summary

        if (-not $result.Success) {
            Write-DeployLog -Logger $logger -Level Error -Message 'Deploy terminou com erro — ver log completo.'
            return 1
        }

        Write-DeployLog -Logger $logger -Level Success -Message $(if ($DryRun) { 'Simulação concluída (nada foi enviado).' } else { 'Deploy concluído.' })
        return 0
    } catch {
        Write-DeployLog -Logger $logger -Level Error -Message "Erro fatal: $($_.Exception.Message)"
        Write-DeployLog -Logger $logger -Level Error -Message $_.ScriptStackTrace
        return 1
    }
}

Export-ModuleMember -Function Invoke-Deploy
