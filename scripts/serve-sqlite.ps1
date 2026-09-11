$ErrorActionPreference = 'Stop'
Push-Location (Split-Path $PSScriptRoot -Parent)
$previousConnection = $env:DB_CONNECTION
$previousDatabase = $env:DB_DATABASE
try {
    $env:DB_CONNECTION = 'sqlite'
    $env:DB_DATABASE = Join-Path (Get-Location) 'database\practice.sqlite'
    if (!(Test-Path -LiteralPath $env:DB_DATABASE)) {
        New-Item -ItemType File -Path $env:DB_DATABASE | Out-Null
    }
    php artisan migrate --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'No se pudieron ejecutar las migraciones.' }
    php artisan db:seed --class=TaskDemoSeeder --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'No se pudieron crear los datos de práctica.' }
    php artisan serve --host=127.0.0.1 --port=8000
} finally {
    $env:DB_CONNECTION = $previousConnection
    $env:DB_DATABASE = $previousDatabase
    Pop-Location
}
