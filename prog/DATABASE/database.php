<?php

if (getenv("DOCKER_ENV") === "true") {
    $host = "db";
    $usuario = "root";
    $password = "root";
    $baseDeDatos = "proyecto";
} else {
    $host = "127.0.0.1";
    $usuario = "root";
    $password = "";
    $baseDeDatos = "proyecto";
}

$conexion = new mysqli(
    $host,
    $usuario,
    $password,
    $baseDeDatos
);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

?>
