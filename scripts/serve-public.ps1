param([int]$Port = 8001)
$ErrorActionPreference = 'Stop'
Push-Location (Split-Path $PSScriptRoot -Parent)
$previous = @{}
foreach ($key in @('DB_CONNECTION', 'DB_DATABASE', 'APP_DEBUG', 'APP_ENV')) {
    $previous[$key] = [Environment]::GetEnvironmentVariable($key, 'Process')
}
try {
    $env:DB_CONNECTION = 'sqlite'
    $env:DB_DATABASE = Join-Path (Get-Location) 'database\practice.sqlite'
    $env:APP_DEBUG = 'false'
    $env:APP_ENV = 'production'
    if (!(Test-Path -LiteralPath $env:DB_DATABASE)) {
        New-Item -ItemType File -Path $env:DB_DATABASE | Out-Null
    }
    php artisan config:clear
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo limpiar la configuración.' }
    php artisan migrate --force --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'No se pudieron ejecutar las migraciones.' }
    php artisan serve --host=127.0.0.1 --port=$Port --no-reload
} finally {
    foreach ($key in $previous.Keys) {
        [Environment]::SetEnvironmentVariable($key, $previous[$key], 'Process')
    }
    Pop-Location
}
