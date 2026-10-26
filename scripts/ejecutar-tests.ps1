# Ejecuta los tests de las tres APIs (Windows / PowerShell).
# Uso:  .\scripts\ejecutar-tests.ps1   o   .\scripts\ejecutar-tests.ps1 gestion
param([string]$Servicio = "")

Set-Location (Join-Path $PSScriptRoot "..")

$compose = @("compose", "-f", "docker-compose.test.yml")
$servicios = if ($Servicio) { @($Servicio) } else { @("usuarios", "gestion", "recoleccion") }
$resultado = 0

docker @compose up -d --wait db-test
if ($LASTEXITCODE -ne 0) { exit 1 }

foreach ($s in $servicios) {
    Write-Host ""
    Write-Host "=============== Tests de api-$s ==============="
    docker @compose run --rm --build "test-$s"
    if ($LASTEXITCODE -ne 0) { $resultado = 1 }
}

docker @compose down -v
exit $resultado
