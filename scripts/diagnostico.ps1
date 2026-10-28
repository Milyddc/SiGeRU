Set-Location (Join-Path $PSScriptRoot "..")
$salida = Join-Path (Get-Location).Path "diagnostico.txt"
"Diagnóstico SiGeRU - $(Get-Date)" | Out-File $salida -Encoding utf8

function Log($texto) { $texto | Tee-Object -FilePath $salida -Append }
function Seccion($titulo) { Log ""; Log "=== $titulo ===" }

Seccion "Carpeta"
Log (Get-Location).Path
Log ("docker-compose.yml: " + (Test-Path .\docker-compose.yml))
Log ("gateway/: " + (Test-Path .\gateway) + "   landing/: " + (Test-Path .\landing) + "   frontend/: " + (Test-Path .\frontend) + "   api-usuarios/: " + (Test-Path .\api-usuarios))

Seccion "Docker"
Log (docker version --format "cliente {{.Client.Version}} / servidor {{.Server.Version}}" 2>&1 | Out-String)

Seccion "Contenedores (docker compose ps -a)"
Log (docker compose ps -a 2>&1 | Out-String)

Seccion "Archivo .env (sin valores secretos)"
if (Test-Path .\.env) {
    Get-Content .\.env | Where-Object { $_ -match '^[A-Z_]+=' } | ForEach-Object {
        $partes = $_ -split '=', 2
        if ($partes[0] -match 'PASS|SECRET') {
            Log ("{0} = (largo {1})" -f $partes[0], $partes[1].Length)
        } else {
            Log ("{0} = {1}" -f $partes[0], $partes[1])
        }
    }
} else {
    Log "NO EXISTE el archivo .env"
}

Seccion "Pruebas HTTP"
foreach ($ruta in "/", "/app/login.html", "/api/usuarios/login.php") {
    $codigo = curl.exe -s -o NUL -w "%{http_code}" ("http://localhost:8080" + $ruta) 2>&1
    Log ("GET  http://localhost:8080{0}  ->  HTTP {1}" -f $ruta, $codigo)
}

'{"correo":"admin@gmail.com","password":"Sigeru2026!"}' | Set-Content -Path .\login_test.json -Encoding ascii
Log ""
Log "POST /api/usuarios/login.php con admin@gmail.com:"
$respuesta = curl.exe -s -i -X POST -H "Content-Type: application/json" --data-binary "@login_test.json" http://localhost:8080/api/usuarios/login.php 2>&1
Log (($respuesta | ForEach-Object { $_ -replace '"token":"[^"]*"', '"token":"(oculto)"' }) | Out-String)
Remove-Item .\login_test.json -ErrorAction SilentlyContinue

foreach ($servicio in "gateway", "api-usuarios", "api-gestion", "frontend", "db") {
    Seccion ("Logs de " + $servicio + " (últimas 30 líneas)")
    Log (docker compose logs --tail 30 --no-color $servicio 2>&1 | Out-String)
}

Seccion "Base de datos: tablas"
Log (docker compose exec -T db sh -c "mysql -uroot -p`$MYSQL_ROOT_PASSWORD -e 'SHOW TABLES FROM proyecto'" 2>&1 | Out-String)

Seccion "Base de datos: usuarios"
Log (docker compose exec -T db sh -c "mysql -uroot -p`$MYSQL_ROOT_PASSWORD -e 'SELECT id_usuario, email, estado, intentos_fallidos, LEFT(password,7) AS inicio_hash FROM proyecto.Usuario'" 2>&1 | Out-String)

Seccion "Volúmenes y contenedores viejos"
Log (docker volume ls 2>&1 | Out-String)
Log (docker ps -a --format "{{.Names}}  {{.Status}}  {{.Ports}}" 2>&1 | Out-String)

Write-Host ""
Write-Host "Listo. Se guardó el archivo: $salida"
Write-Host "Subilo (o pegá su contenido) para ver qué está fallando."
