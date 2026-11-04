param(
    [string]$Base = "http://localhost:8080"
)

Set-Location (Join-Path $PSScriptRoot "..")

$utf8 = New-Object System.Text.UTF8Encoding $false
$temporal = [IO.Path]::GetTempPath()
$archivoCuerpo = Join-Path $temporal "sigeru_cuerpo.txt"
$archivoCabeceras = Join-Path $temporal "sigeru_cabeceras.txt"
$archivoEntrada = Join-Path $temporal "sigeru_entrada.json"
$resultados = New-Object System.Collections.Generic.List[object]
$estampa = Get-Date -Format "yyyyMMddHHmmss"

function Llamar {
    param(
        [string]$Metodo,
        [string]$Ruta,
        $Datos = $null,
        [string]$Token = "",
        [string]$Crudo = ""
    )

    $parametros = @("-s", "-o", $archivoCuerpo, "-D", $archivoCabeceras, "-w", "%{http_code}", "-X", $Metodo, "-H", "Content-Type: application/json")

    if ($Token) {
        $parametros += @("-H", "Authorization: Bearer $Token")
    }

    if ($Crudo) {
        [IO.File]::WriteAllText($archivoEntrada, $Crudo, $utf8)
        $parametros += @("--data-binary", "@$archivoEntrada")
    } elseif ($null -ne $Datos) {
        $json = $Datos | ConvertTo-Json -Compress -Depth 6
        [IO.File]::WriteAllText($archivoEntrada, $json, $utf8)
        $parametros += @("--data-binary", "@$archivoEntrada")
    }

    $parametros += ($Base + $Ruta)

    Remove-Item $archivoCuerpo, $archivoCabeceras -ErrorAction SilentlyContinue
    $codigo = & curl.exe @parametros 2>$null

    $texto = ""
    if (Test-Path $archivoCuerpo) { $texto = [IO.File]::ReadAllText($archivoCuerpo, $utf8) }

    $cabeceras = ""
    if (Test-Path $archivoCabeceras) { $cabeceras = [IO.File]::ReadAllText($archivoCabeceras) }

    $json = $null
    try { $json = $texto | ConvertFrom-Json } catch { $json = $null }

    return [pscustomobject]@{
        Codigo = [int]$codigo
        Json = $json
        Texto = $texto
        Cabeceras = $cabeceras
    }
}

function Registrar {
    param($Id, $Descripcion, $Esperado, [bool]$Ok, $Obtenido)

    $estado = if ($Ok) { "OK" } else { "FALLA" }

    $resultados.Add([pscustomobject]@{
        ID = $Id
        Descripcion = $Descripcion
        Esperado = $Esperado
        Obtenido = $Obtenido
        Estado = $estado
    })

    $color = if ($Ok) { "Green" } else { "Red" }
    Write-Host ("{0,-6} {1,-6} {2}" -f $Id, $estado, $Descripcion) -ForegroundColor $color

    if (-not $Ok) {
        Write-Host ("       obtenido: " + $Obtenido) -ForegroundColor DarkYellow
    }
}

function Probar {
    param($Id, $Descripcion, $Esperado, [scriptblock]$Bloque)

    try {
        $r = & $Bloque
        Registrar $Id $Descripcion $Esperado ([bool]$r.Ok) ([string]$r.Obtenido)
    } catch {
        Registrar $Id $Descripcion $Esperado $false ("Excepción: " + $_.Exception.Message)
    }
}

function Resumen {
    param($Respuesta)
    $mensaje = ""
    if ($Respuesta.Json -and $Respuesta.Json.mensaje) { $mensaje = $Respuesta.Json.mensaje }
    return ("HTTP {0} {1}" -f $Respuesta.Codigo, $mensaje).Trim()
}

function IniciarSesion {
    param($Correo, $Clave)
    return Llamar "POST" "/api/usuarios/login.php" @{ correo = $Correo; password = $Clave }
}

Write-Host ""
Write-Host "Pruebas de caja negra de SiGeRU contra $Base" -ForegroundColor Cyan
Write-Host ""

$previa = Llamar "GET" "/api/usuarios/estado.php"
if ($previa.Codigo -eq 0) {
    Write-Host "No se pudo conectar con $Base. Levantá el sistema con: docker compose up -d --build" -ForegroundColor Red
    exit 1
}

$clave = "Sigeru2026!"
$claveNueva = "Prueba1234"

$respAdmin = IniciarSesion "admin@gmail.com" $clave
$tokenAdmin = ""
if ($respAdmin.Json -and $respAdmin.Json.token) { $tokenAdmin = $respAdmin.Json.token }

Probar "CN-01" "Inicio de sesión del administrador con datos correctos" "HTTP 200, ok=true y token" {
    @{ Ok = ($respAdmin.Codigo -eq 200 -and $respAdmin.Json.ok -eq $true -and $tokenAdmin.Length -gt 20); Obtenido = (Resumen $respAdmin) }
}

if (-not $tokenAdmin) {
    Write-Host ""
    Write-Host "Sin sesión de administrador no se puede continuar. Revisá el login y volvé a ejecutar." -ForegroundColor Red
    exit 1
}

Probar "CN-02" "Login con contraseña incorrecta" "ok=false y sin token" {
    $r = IniciarSesion "admin@gmail.com" "incorrecta123"
    @{ Ok = ($r.Json.ok -eq $false -and -not $r.Json.token); Obtenido = (Resumen $r) }
}

Probar "CN-03" "Login con correo inexistente devuelve el mismo mensaje (no revela si el correo existe)" "Mismo mensaje que CN-02" {
    $a = IniciarSesion "admin@gmail.com" "incorrecta123"
    $b = IniciarSesion "nadie.$estampa@test.com" "incorrecta123"
    @{ Ok = ($b.Json.ok -eq $false -and $a.Json.mensaje -eq $b.Json.mensaje); Obtenido = ($a.Json.mensaje + " | " + $b.Json.mensaje) }
}

Probar "CN-04" "Login con un cuerpo que no es JSON" "HTTP 400" {
    $r = Llamar "POST" "/api/usuarios/login.php" $null "" "esto-no-es-json"
    @{ Ok = ($r.Codigo -eq 400); Obtenido = (Resumen $r) }
}

Probar "CN-05" "Intento de inyección SQL en el campo correo" "ok=false y sin token" {
    $r = IniciarSesion "' OR '1'='1' --" "x"
    @{ Ok = ($r.Json.ok -eq $false -and -not $r.Json.token); Obtenido = (Resumen $r) }
}

Probar "CN-06" "Pedir datos protegidos sin token" "HTTP 401" {
    $r = Llamar "GET" "/api/gestion/listarContenedores.php"
    @{ Ok = ($r.Codigo -eq 401); Obtenido = (Resumen $r) }
}

Probar "CN-07" "Pedir datos con un token inventado" "HTTP 401" {
    $r = Llamar "GET" "/api/gestion/listarContenedores.php" $null "token-inventado"
    @{ Ok = ($r.Codigo -eq 401); Obtenido = (Resumen $r) }
}

Probar "CN-08" "Pedir datos con un token válido pero con la firma alterada" "HTTP 401" {
    $alterado = $tokenAdmin.Substring(0, $tokenAdmin.Length - 3) + "AAA"
    $r = Llamar "GET" "/api/gestion/listarContenedores.php" $null $alterado
    @{ Ok = ($r.Codigo -eq 401); Obtenido = (Resumen $r) }
}

$respVecino = IniciarSesion "vecino@sigeru.com" $clave
$tokenVecino = ""
if ($respVecino.Json -and $respVecino.Json.token) { $tokenVecino = $respVecino.Json.token }

Probar "CN-09" "Un vecino intenta ver la lista de usuarios (solo administrador)" "HTTP 403" {
    $r = Llamar "GET" "/api/usuarios/listarusuarios.php" $null $tokenVecino
    @{ Ok = ($r.Codigo -eq 403); Obtenido = (Resumen $r) }
}

Probar "CN-10" "Las tres APIs informan que están sanas" "HTTP 200 y ok=true en las tres" {
    $detalle = @()
    $todas = $true
    foreach ($servicio in "usuarios", "gestion", "recoleccion") {
        $r = Llamar "GET" "/api/$servicio/estado.php"
        $bien = ($r.Codigo -eq 200 -and $r.Json.ok -eq $true)
        if (-not $bien) { $todas = $false }
        $detalle += ("{0}:{1}" -f $servicio, $r.Codigo)
    }
    @{ Ok = $todas; Obtenido = ($detalle -join " ") }
}

$correoNuevo = "prueba.$estampa@test.com"

Probar "CN-11" "Registro público con contraseña débil" "ok=false" {
    $r = Llamar "POST" "/api/usuarios/registro.php" @{ nombre = "Prueba"; apellido = "Debil"; correo = "debil.$estampa@test.com"; password = "abc" }
    @{ Ok = ($r.Json.ok -eq $false); Obtenido = (Resumen $r) }
}

Probar "CN-12" "Registro público con datos válidos" "ok=true" {
    $r = Llamar "POST" "/api/usuarios/registro.php" @{ nombre = "Prueba"; apellido = "Automatica"; correo = $correoNuevo; password = $claveNueva }
    @{ Ok = ($r.Json.ok -eq $true); Obtenido = (Resumen $r) }
}

Probar "CN-13" "Una cuenta recién registrada no puede iniciar sesión (pendiente de aprobación)" "ok=false y mensaje de cuenta pendiente" {
    $r = IniciarSesion $correoNuevo $claveNueva
    @{ Ok = ($r.Json.ok -eq $false -and $r.Json.mensaje -match "pendiente"); Obtenido = (Resumen $r) }
}

Probar "CN-14" "El administrador aprueba la cuenta" "ok=true" {
    $lista = Llamar "GET" "/api/usuarios/listarusuarios.php" $null $tokenAdmin
    $persona = @($lista.Json) | Where-Object { $_.email -eq $correoNuevo } | Select-Object -First 1
    $r = Llamar "POST" "/api/usuarios/aprobarusuario.php" @{ id_usuario = [int]$persona.id_usuario } $tokenAdmin
    @{ Ok = ($r.Json.ok -eq $true); Obtenido = (Resumen $r) }
}

Probar "CN-15" "Tras la aprobación la persona ingresa con rol Vecino" "ok=true y rol Vecino" {
    $r = IniciarSesion $correoNuevo $claveNueva
    @{ Ok = ($r.Json.ok -eq $true -and $r.Json.usuario.rol -eq "Vecino"); Obtenido = (Resumen $r) }
}

Probar "CN-16" "La cuenta se bloquea después de 5 intentos fallidos" "Con la contraseña correcta: ok=false y mensaje de cuenta bloqueada" {
    for ($i = 1; $i -le 5; $i++) {
        IniciarSesion $correoNuevo "incorrecta$i" | Out-Null
    }
    $r = IniciarSesion $correoNuevo $claveNueva
    @{ Ok = ($r.Json.ok -eq $false -and $r.Json.mensaje -match "bloqueada"); Obtenido = (Resumen $r) }
}

$respContenedores = Llamar "GET" "/api/gestion/listarContenedores.php" $null $tokenAdmin

Probar "CN-17" "Listar los contenedores" "HTTP 200 y al menos 6 contenedores" {
    @{ Ok = ($respContenedores.Codigo -eq 200 -and @($respContenedores.Json).Count -ge 6); Obtenido = ("HTTP {0}, {1} contenedores" -f $respContenedores.Codigo, @($respContenedores.Json).Count) }
}

$codigoNuevo = "CN$estampa"
$datosContenedor = @{
    codigo = $codigoNuevo
    ubicacion = "Calle de prueba 123"
    estado = "Disponible"
    capacidad = 1100
    id_tipo_residuo = 1
    id_ruta = $null
    id_centro_acopio = 1
    latitud = -34.9011
    longitud = -56.1645
    zona = "Centro"
}

Probar "CN-18" "Crear un contenedor con datos válidos" "ok=true" {
    $r = Llamar "POST" "/api/gestion/crearContenedor.php" $datosContenedor $tokenAdmin
    @{ Ok = ($r.Json.ok -eq $true); Obtenido = (Resumen $r) }
}

Probar "CN-19" "Crear un contenedor con un código que ya existe" "ok=false" {
    $r = Llamar "POST" "/api/gestion/crearContenedor.php" $datosContenedor $tokenAdmin
    @{ Ok = ($r.Json.ok -eq $false); Obtenido = (Resumen $r) }
}

Probar "CN-20" "Crear un contenedor con latitud fuera de rango (95)" "ok=false" {
    $malos = $datosContenedor.Clone()
    $malos.codigo = "CX$estampa"
    $malos.latitud = 95
    $r = Llamar "POST" "/api/gestion/crearContenedor.php" $malos $tokenAdmin
    @{ Ok = ($r.Json.ok -eq $false); Obtenido = (Resumen $r) }
}

Probar "CN-21" "Un vecino intenta crear un contenedor" "HTTP 403" {
    $r = Llamar "POST" "/api/gestion/crearContenedor.php" $datosContenedor $tokenVecino
    @{ Ok = ($r.Codigo -eq 403); Obtenido = (Resumen $r) }
}

Probar "CN-22" "Eliminar el contenedor de prueba" "ok=true" {
    $lista = Llamar "GET" "/api/gestion/listarContenedores.php" $null $tokenAdmin
    $creado = @($lista.Json) | Where-Object { $_.codigo -eq $codigoNuevo } | Select-Object -First 1
    $r = Llamar "POST" "/api/gestion/eliminarContenedor.php" @{ id_contenedor = [int]$creado.id_contenedor } $tokenAdmin
    @{ Ok = ($r.Json.ok -eq $true); Obtenido = (Resumen $r) }
}

$libre = @($respContenedores.Json) | Where-Object { $_.estado -eq "Disponible" } | Select-Object -First 1
if (-not $libre) { $libre = @($respContenedores.Json) | Select-Object -First 1 }
$script:idIncidencia = 0

Probar "CN-23" "Un vecino registra una incidencia de desborde" "ok=true y número de incidencia" {
    $r = Llamar "POST" "/api/gestion/crearIncidencia.php" @{ id_contenedor = [int]$libre.id_contenedor; tipo = "Desborde"; descripcion = "Prueba automática de caja negra" } $tokenVecino
    $script:idIncidencia = [int]$r.Json.id_incidencia
    @{ Ok = ($r.Json.ok -eq $true -and $script:idIncidencia -gt 0); Obtenido = (Resumen $r) }
}

Probar "CN-24" "Un vecino solo ve las incidencias que él reportó" "Todas son de Vecino Prueba y hay al menos una" {
    $r = Llamar "GET" "/api/gestion/listarIncidencias.php" $null $tokenVecino
    $filas = @($r.Json)
    $ajenas = @($filas | Where-Object { $_.reportante -ne "Vecino Prueba" })
    @{ Ok = ($r.Codigo -eq 200 -and $filas.Count -ge 1 -and $ajenas.Count -eq 0); Obtenido = ("{0} incidencias, {1} ajenas" -f $filas.Count, $ajenas.Count) }
}

Probar "CN-25" "Un vecino intenta cambiar el estado de una incidencia" "HTTP 403" {
    $r = Llamar "POST" "/api/gestion/cambiarEstadoIncidencia.php" @{ id_incidencia = $script:idIncidencia; estado = "Resuelta" } $tokenVecino
    @{ Ok = ($r.Codigo -eq 403); Obtenido = (Resumen $r) }
}

Probar "CN-26" "Registrar una incidencia con un tipo inválido" "ok=false" {
    $r = Llamar "POST" "/api/gestion/crearIncidencia.php" @{ id_contenedor = [int]$libre.id_contenedor; tipo = "Explosión"; descripcion = "Tipo inválido" } $tokenVecino
    @{ Ok = ($r.Json.ok -eq $false); Obtenido = (Resumen $r) }
}

Probar "CN-27" "Registrar una incidencia sin descripción" "HTTP 400" {
    $r = Llamar "POST" "/api/gestion/crearIncidencia.php" @{ id_contenedor = [int]$libre.id_contenedor; tipo = "Rotura" } $tokenVecino
    @{ Ok = ($r.Codigo -eq 400); Obtenido = (Resumen $r) }
}

Probar "CN-28" "El administrador pasa la incidencia a En curso" "ok=true" {
    $r = Llamar "POST" "/api/gestion/cambiarEstadoIncidencia.php" @{ id_incidencia = $script:idIncidencia; estado = "En curso"; comentario = "Prueba" } $tokenAdmin
    @{ Ok = ($r.Json.ok -eq $true); Obtenido = (Resumen $r) }
}

Probar "CN-29" "El administrador resuelve la incidencia" "ok=true" {
    $r = Llamar "POST" "/api/gestion/cambiarEstadoIncidencia.php" @{ id_incidencia = $script:idIncidencia; estado = "Resuelta"; comentario = "Prueba" } $tokenAdmin
    @{ Ok = ($r.Json.ok -eq $true); Obtenido = (Resumen $r) }
}

Probar "CN-30" "El historial registra la creación y los dos cambios de estado" "Al menos 3 registros" {
    $r = Llamar "GET" "/api/gestion/historialIncidencia.php?id_incidencia=$($script:idIncidencia)" $null $tokenAdmin
    @{ Ok = ($r.Json.ok -eq $true -and @($r.Json.historial).Count -ge 3); Obtenido = ("{0} registros" -f @($r.Json.historial).Count) }
}

Probar "CN-31" "Un vecino no puede ver el historial de una incidencia ajena" "ok=false" {
    $todas = Llamar "GET" "/api/gestion/listarIncidencias.php" $null $tokenAdmin
    $ajena = @($todas.Json) | Where-Object { $_.reportante -ne "Vecino Prueba" } | Select-Object -First 1
    $r = Llamar "GET" "/api/gestion/historialIncidencia.php?id_incidencia=$($ajena.id_incidencia)" $null $tokenVecino
    @{ Ok = ($r.Json.ok -eq $false); Obtenido = (Resumen $r) }
}

Probar "CN-32" "El administrador recibe una alerta por el desborde reportado" "Notificación de tipo alerta que menciona la nueva incidencia" {
    $r = Llamar "GET" "/api/gestion/listarNotificaciones.php" $null $tokenAdmin
    $alerta = @($r.Json.notificaciones) | Where-Object { $_.tipo -eq "alerta" -and $_.mensaje -match "Nueva incidencia" } | Select-Object -First 1
    @{ Ok = ($null -ne $alerta); Obtenido = ("{0} notificaciones, {1} sin leer" -f @($r.Json.notificaciones).Count, $r.Json.no_leidas) }
}

Probar "CN-33" "Listar las rutas" "HTTP 200 y al menos 3 rutas" {
    $r = Llamar "GET" "/api/recoleccion/listarRutas.php" $null $tokenAdmin
    @{ Ok = ($r.Codigo -eq 200 -and @($r.Json).Count -ge 3); Obtenido = ("HTTP {0}, {1} rutas" -f $r.Codigo, @($r.Json).Count) }
}

$nombreRuta = "Ruta temporal $estampa"

Probar "CN-34" "Crear una ruta" "ok=true" {
    $r = Llamar "POST" "/api/recoleccion/crearRuta.php" @{ nombre = $nombreRuta; zona = "Prueba"; frecuencia = "Diaria"; id_cuadrilla = 1 } $tokenAdmin
    @{ Ok = ($r.Json.ok -eq $true); Obtenido = (Resumen $r) }
}

Probar "CN-35" "Eliminar la ruta creada" "ok=true" {
    $lista = Llamar "GET" "/api/recoleccion/listarRutas.php" $null $tokenAdmin
    $ruta = @($lista.Json) | Where-Object { $_.nombre -eq $nombreRuta } | Select-Object -First 1
    $r = Llamar "POST" "/api/recoleccion/eliminarRuta.php" @{ id_ruta = [int]$ruta.id_ruta } $tokenAdmin
    @{ Ok = ($r.Json.ok -eq $true); Obtenido = (Resumen $r) }
}

$rutaRecorrido = "Ruta recorrido $estampa"
$matriculaLibre = "T$estampa"
$matriculaTaller = "M$estampa"

Llamar "POST" "/api/recoleccion/crearRuta.php" @{ nombre = $rutaRecorrido; zona = "Prueba"; frecuencia = "Diaria"; id_cuadrilla = 1 } $tokenAdmin | Out-Null
Llamar "POST" "/api/recoleccion/crearCamion.php" @{ matricula = $matriculaLibre; modelo = "Prueba"; capacidad = 9000; estado = "Disponible"; id_cuadrilla = 1 } $tokenAdmin | Out-Null
Llamar "POST" "/api/recoleccion/crearCamion.php" @{ matricula = $matriculaTaller; modelo = "Prueba"; capacidad = 9000; estado = "En mantenimiento"; id_cuadrilla = 1 } $tokenAdmin | Out-Null

$idRuta = [int]((@((Llamar "GET" "/api/recoleccion/listarRutas.php" $null $tokenAdmin).Json) | Where-Object { $_.nombre -eq $rutaRecorrido } | Select-Object -First 1).id_ruta)

function BuscarCamion {
    param($Matricula)
    $r = Llamar "GET" "/api/recoleccion/listarCamiones.php" $null $tokenAdmin
    return (@($r.Json) | Where-Object { $_.matricula -eq $Matricula } | Select-Object -First 1)
}

$camionLibre = BuscarCamion $matriculaLibre
$camionTaller = BuscarCamion $matriculaTaller
$script:idRecorrido = 0

Probar "CN-36" "Un camión en mantenimiento no puede iniciar un recorrido" "ok=false y mensaje de camión no disponible" {
    $r = Llamar "POST" "/api/recoleccion/iniciarRecorrido.php" @{ id_ruta = $idRuta; id_camion = [int]$camionTaller.id_camion } $tokenAdmin
    @{ Ok = ($r.Json.ok -eq $false -and $r.Json.mensaje -match "disponible"); Obtenido = (Resumen $r) }
}

Probar "CN-37" "Iniciar un recorrido con un camión disponible deja el camión En ruta" "ok=true y camión En ruta" {
    $r = Llamar "POST" "/api/recoleccion/iniciarRecorrido.php" @{ id_ruta = $idRuta; id_camion = [int]$camionLibre.id_camion } $tokenAdmin
    $script:idRecorrido = [int]$r.Json.id_recorrido
    $estado = (BuscarCamion $matriculaLibre).estado
    @{ Ok = ($r.Json.ok -eq $true -and $estado -eq "En ruta"); Obtenido = ((Resumen $r) + " | camión: " + $estado) }
}

Probar "CN-38" "Registrar un recurso usado en el recorrido" "ok=true" {
    $r = Llamar "POST" "/api/recoleccion/registrarRecurso.php" @{ id_recorrido = $script:idRecorrido; recurso = "Combustible"; cantidad = 40; unidad = "L" } $tokenAdmin
    @{ Ok = ($r.Json.ok -eq $true); Obtenido = (Resumen $r) }
}

Probar "CN-39" "Finalizar el recorrido libera el camión" "ok=true y camión Disponible" {
    $r = Llamar "POST" "/api/recoleccion/finalizarRecorrido.php" @{ id_recorrido = $script:idRecorrido; volumen_kg = 1500.5; observaciones = "Prueba" } $tokenAdmin
    $estado = (BuscarCamion $matriculaLibre).estado
    @{ Ok = ($r.Json.ok -eq $true -and $estado -eq "Disponible"); Obtenido = ((Resumen $r) + " | camión: " + $estado) }
}

Probar "CN-40" "El panel devuelve las estadísticas al administrador" "HTTP 200 con por_estado, top_desbordados y por_zona" {
    $r = Llamar "GET" "/api/gestion/estadisticas.php" $null $tokenAdmin
    $claves = @()
    if ($r.Json) { $claves = $r.Json.PSObject.Properties.Name }
    @{ Ok = ($r.Codigo -eq 200 -and ($claves -contains "por_estado") -and ($claves -contains "top_desbordados") -and ($claves -contains "por_zona")); Obtenido = ("HTTP {0}, secciones: {1}" -f $r.Codigo, ($claves -join ",")) }
}

Probar "CN-41" "Un vecino no puede ver las estadísticas" "HTTP 403" {
    $r = Llamar "GET" "/api/gestion/estadisticas.php" $null $tokenVecino
    @{ Ok = ($r.Codigo -eq 403); Obtenido = (Resumen $r) }
}

Probar "CN-42" "El mapa devuelve contenedores con coordenadas numéricas" "HTTP 200 y latitud numérica" {
    $r = Llamar "GET" "/api/gestion/listarMapa.php" $null $tokenAdmin
    $primero = @($r.Json.contenedores) | Select-Object -First 1
    $numerica = ($primero.latitud -is [double] -or $primero.latitud -is [int] -or $primero.latitud -is [decimal])
    @{ Ok = ($r.Codigo -eq 200 -and $numerica); Obtenido = ("HTTP {0}, {1} contenedores" -f $r.Codigo, @($r.Json.contenedores).Count) }
}

Probar "CN-43" "Las respuestas incluyen cabeceras de seguridad" "X-Content-Type-Options: nosniff y X-Frame-Options" {
    $cab = $respAdmin.Cabeceras
    @{ Ok = ($cab -match "(?i)x-content-type-options:\s*nosniff" -and $cab -match "(?i)x-frame-options:"); Obtenido = "cabeceras revisadas en la respuesta del login" }
}

Probar "CN-44" "El código fuente de la API no es accesible por web" "HTTP 404" {
    $r = Llamar "GET" "/api/usuarios/src/database.php"
    @{ Ok = ($r.Codigo -eq 404); Obtenido = (Resumen $r) }
}

Probar "CN-45" "Las carpetas de la API no muestran su listado de archivos" "No es HTTP 200" {
    $r = Llamar "GET" "/api/gestion/"
    @{ Ok = ($r.Codigo -ne 200 -and $r.Codigo -ne 0); Obtenido = (Resumen $r) }
}

Probar "CN-46" "El archivo .env no es accesible" "No es HTTP 200 y no muestra claves" {
    $r = Llamar "GET" "/app/.env"
    @{ Ok = ($r.Codigo -ne 200 -and $r.Texto -notmatch "JWT_SECRET"); Obtenido = (Resumen $r) }
}

Probar "CN-47" "Una ráfaga de intentos de login activa el límite del gateway" "Al menos una respuesta HTTP 429 en 45 intentos" {
    $limitados = 0
    for ($i = 1; $i -le 45; $i++) {
        $r = IniciarSesion "limite.$estampa@test.com" "x"
        if ($r.Codigo -eq 429) { $limitados++ }
    }
    @{ Ok = ($limitados -ge 1); Obtenido = ("{0} respuestas 429" -f $limitados) }
}

$ok = @($resultados | Where-Object { $_.Estado -eq "OK" }).Count
$falla = @($resultados | Where-Object { $_.Estado -eq "FALLA" }).Count

$carpetaEvidencias = Join-Path (Get-Location).Path "docs\evidencias"
New-Item -ItemType Directory -Force -Path $carpetaEvidencias | Out-Null
$archivoSalida = Join-Path $carpetaEvidencias ("caja-negra-" + $estampa + ".csv")
$resultados | Export-Csv -Path $archivoSalida -NoTypeInformation -Encoding UTF8

Write-Host ""
Write-Host ("Resultado: {0} correctas, {1} con falla, {2} en total" -f $ok, $falla, $resultados.Count) -ForegroundColor Cyan
Write-Host "Informe guardado en: $archivoSalida"
Write-Host "Nota: las pruebas dejan algunos registros de prueba (usuario, incidencia, camiones). Para limpiar: .\scripts\reiniciar.ps1" -ForegroundColor DarkYellow
Write-Host "Tras la CN-47 el login queda limitado por el gateway durante aproximadamente un minuto."

Remove-Item $archivoCuerpo, $archivoCabeceras, $archivoEntrada -ErrorAction SilentlyContinue

if ($falla -gt 0) { exit 1 }
