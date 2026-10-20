<#
  Windows twin of the Makefile (same targets).
  Usage: ./dev.ps1 up | down | build | shell | migrate | fresh | test | pint | stan | lint | logs | ps
#>
param([Parameter(Position = 0)][string]$Target = 'help')

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

function Invoke-Step([string[]]$Cmd) {
    Write-Host "> docker $($Cmd -join ' ')" -ForegroundColor DarkGray
    & docker @Cmd
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
}

switch ($Target) {
    'up' {
        if (-not (Test-Path .env)) { Copy-Item .env.example .env }
        Invoke-Step @('compose', 'up', '-d', '--build')
        Invoke-Step @('compose', 'ps')
    }
    'down'    { Invoke-Step @('compose', 'down') }
    'build'   { Invoke-Step @('compose', 'build') }
    'shell'   { Invoke-Step @('compose', 'exec', 'app', 'bash') }
    'migrate' { Invoke-Step @('compose', 'exec', 'app', 'php', 'artisan', 'migrate') }
    'fresh'   { Invoke-Step @('compose', 'exec', 'app', 'php', 'artisan', 'migrate:fresh', '--seed', '--force') }
    'test' {
        Invoke-Step @('compose', 'exec', 'app', 'php', 'artisan', 'config:clear')
        Invoke-Step @('compose', 'exec', 'app', './vendor/bin/pest', '--exclude-group=browser')
        Invoke-Step @('compose', 'exec', 'app', './vendor/bin/pest', '--group=browser')
    }
    'pint'    { Invoke-Step @('compose', 'exec', 'app', './vendor/bin/pint') }
    'stan'    { Invoke-Step @('compose', 'exec', 'app', './vendor/bin/phpstan', 'analyse', '--memory-limit=1G') }
    'lint' {
        Invoke-Step @('compose', 'exec', 'vite', 'npm', 'run', 'lint:check')
        Invoke-Step @('compose', 'exec', 'vite', 'npm', 'run', 'format:check')
    }
    'logs'    { Invoke-Step @('compose', 'logs', '-f', '--tail=100') }
    'ps'      { Invoke-Step @('compose', 'ps') }
    default {
        Write-Host 'Targets: up down build shell migrate fresh test pint stan lint logs ps'
    }
}
