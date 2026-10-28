Set-Location (Join-Path $PSScriptRoot "..")

Write-Host "1/4  Deteniendo y borrando contenedores y datos de este proyecto..."
docker compose down -v --remove-orphans

$viejos = docker ps -aq --filter "name=sigeru_"
if ($viejos) {
    Write-Host "     Quitando contenedores de versiones anteriores del proyecto..."
    docker rm -f $viejos | Out-Null
}

Write-Host "2/4  Construyendo y levantando (la primera vez tarda varios minutos)..."
docker compose up -d --build
if ($LASTEXITCODE -ne 0) {
    Write-Host "Docker no pudo levantar el proyecto. Revisá el mensaje de error de arriba." -ForegroundColor Red
    exit 1
}

Write-Host "3/4  Esperando a que las aplicaciones respondan..."
$servicios = "usuarios", "gestion", "recoleccion"
$listos = @{}
for ($i = 0; $i -lt 60; $i++) {
    foreach ($s in $servicios) {
        if (-not $listos[$s]) {
            $codigo = curl.exe -s -o NUL -w "%{http_code}" "http://localhost:8080/api/$s/estado.php"
            if ($codigo -eq "200") { $listos[$s] = $true }
        }
    }
    if ($listos.Count -eq $servicios.Count) { break }
    Start-Sleep -Seconds 3
}

Write-Host "4/4  Resultado:"
foreach ($s in $servicios) {
    if ($listos[$s]) {
        Write-Host ("     api-{0}: OK" -f $s) -ForegroundColor Green
    } else {
        Write-Host ("     api-{0}: NO responde. Ejecutá: docker compose logs api-{0}" -f $s) -ForegroundColor Red
    }
}

if ($listos.Count -eq $servicios.Count) {
    Write-Host ""
    Write-Host "Todo listo. Entrá a http://localhost:8080/app/login.html  (admin@gmail.com / Sigeru2026!)" -ForegroundColor Green
}
