<?php

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");

mysqli_report(MYSQLI_REPORT_OFF);

$conexion = @new mysqli(
    getenv("DB_HOST") ?: "127.0.0.1",
    getenv("MYSQL_USER") ?: "root",
    getenv("MYSQL_PASSWORD") !== false ? getenv("MYSQL_PASSWORD") : "",
    getenv("MYSQL_DATABASE") ?: "proyecto"
);

$baseConectada = !$conexion->connect_error;
$tablasCreadas = false;

if ($baseConectada) {
    $resultado = $conexion->query("SHOW TABLES LIKE 'Usuario'");
    $tablasCreadas = $resultado && $resultado->num_rows > 0;
}

$secreto = getenv("JWT_SECRET");
$claveValida = $secreto !== false && strlen($secreto) >= 32;

$ok = $baseConectada && $tablasCreadas && $claveValida;

http_response_code($ok ? 200 : 503);

echo json_encode([
    "servicio" => "gestion",
    "ok" => $ok,
    "base_de_datos" => $baseConectada,
    "tablas_creadas" => $tablasCreadas,
    "jwt_secret_valido" => $claveValida
]);
