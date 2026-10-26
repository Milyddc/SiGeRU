<?php

// Verificación de tokens JWT firmados (HS256). Los tokens los emite api-usuarios al iniciar sesión;
// esta API solo los valida. La firma impide que el cliente modifique su rol o id.

function b64urlEncode(string $datos): string
{
    return rtrim(strtr(base64_encode($datos), "+/", "-_"), "=");
}

function b64urlDecode(string $datos): string
{
    return base64_decode(strtr($datos, "-_", "+/"));
}

function jwtSecret(): string
{
    $secreto = getenv("JWT_SECRET");

    if (!$secreto || strlen($secreto) < 32) {
        error_log("JWT_SECRET no configurado o demasiado corto (mínimo 32 caracteres)");
        http_response_code(500);
        echo json_encode(["ok" => false, "mensaje" => "Error interno del servidor"]);
        exit;
    }

    return $secreto;
}

function jwtVerificar(string $token): ?array
{
    $partes = explode(".", $token);

    if (count($partes) !== 3) {
        return null;
    }

    [$parte1, $parte2, $firmaRecibida] = $partes;

    $cabecera = json_decode(b64urlDecode($parte1), true);
    if (!is_array($cabecera) || ($cabecera["alg"] ?? "") !== "HS256") {
        return null; // rechaza "alg: none" y otros algoritmos
    }

    $firmaEsperada = b64urlEncode(
        hash_hmac("sha256", "$parte1.$parte2", jwtSecret(), true)
    );

    if (!hash_equals($firmaEsperada, $firmaRecibida)) {
        return null;
    }

    $datos = json_decode(b64urlDecode($parte2), true);

    if (!is_array($datos) || ($datos["exp"] ?? 0) < time()) {
        return null;
    }

    return $datos;
}

function tokenDeLaPeticion(): ?string
{
    $cabecera = $_SERVER["HTTP_AUTHORIZATION"]
        ?? $_SERVER["REDIRECT_HTTP_AUTHORIZATION"]
        ?? "";

    if ($cabecera === "" && function_exists("apache_request_headers")) {
        foreach (apache_request_headers() as $nombre => $valor) {
            if (strtolower($nombre) === "authorization") {
                $cabecera = $valor;
            }
        }
    }

    if (preg_match('/^Bearer\s+(.+)$/i', $cabecera, $coincidencia)) {
        return trim($coincidencia[1]);
    }

    return null;
}

// Devuelve el usuario autenticado o null (sin cortar la ejecución)
function usuarioActual(): ?array
{
    $token = tokenDeLaPeticion();

    return $token ? jwtVerificar($token) : null;
}

// Corta con 401/403 si no hay sesión válida o el rol no está permitido.
// Uso: $usuario = requireAuth();                      (cualquier usuario logueado)
//      $usuario = requireAuth(["Administrador"]);     (solo ciertos roles)
function requireAuth(array $rolesPermitidos = []): array
{
    $usuario = usuarioActual();

    if (!$usuario) {
        http_response_code(401);
        echo json_encode(["ok" => false, "mensaje" => "No autenticado"]);
        exit;
    }

    if ($rolesPermitidos && !in_array($usuario["rol"], $rolesPermitidos, true)) {
        http_response_code(403);
        echo json_encode(["ok" => false, "mensaje" => "No tenés permisos para esta acción"]);
        exit;
    }

    return $usuario;
}
