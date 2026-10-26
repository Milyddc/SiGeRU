<?php

// Credenciales desde variables de entorno (.env). Nunca hardcodeadas.
$host        = getenv("DB_HOST") ?: "127.0.0.1";
$usuario     = getenv("MYSQL_USER") ?: "root";
$password    = getenv("MYSQL_PASSWORD") !== false ? getenv("MYSQL_PASSWORD") : "";
$baseDeDatos = getenv("MYSQL_DATABASE") ?: "proyecto";

mysqli_report(MYSQLI_REPORT_OFF);

$conexion = @new mysqli($host, $usuario, $password, $baseDeDatos);

if ($conexion->connect_error) {
    error_log("Error de conexión a la BD: " . $conexion->connect_error);
    http_response_code(500);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["ok" => false, "mensaje" => "Error interno del servidor"]);
    exit;
}

$conexion->set_charset("utf8mb4");
