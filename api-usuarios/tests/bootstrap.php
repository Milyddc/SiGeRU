<?php

require_once __DIR__ . '/../vendor/autoload.php';

$nombreBd = getenv('MYSQL_DATABASE') ?: 'proyecto_test';

if (strpos($nombreBd, 'test') === false) {
    fwrite(STDERR, "Por seguridad, los tests solo corren contra una base cuyo nombre contenga 'test'.\n");
    exit(1);
}

$admin = new mysqli(
    getenv('DB_HOST') ?: 'db-test',
    getenv('MYSQL_USER') ?: 'root',
    getenv('MYSQL_PASSWORD') ?: ''
);
$admin->query("CREATE DATABASE IF NOT EXISTS `$nombreBd` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$admin->close();

require_once __DIR__ . '/../src/database.php';

function reiniciarBaseDeDatos(): void
{
    global $conexion;
    static $sql = null;

    if ($sql === null) {
        $ruta = getenv('SCHEMA_SQL') ?: '/database/database.sql';
        $contenido = file_get_contents($ruta);

        if ($contenido === false) {
            throw new RuntimeException("No se pudo leer $ruta");
        }

        $sql = preg_replace('/^\s*CREATE DATABASE[^;]*;\s*USE\s+\w+;/i', '', $contenido, 1);
    }

    $conexion->query('SET FOREIGN_KEY_CHECKS = 0');

    $tablas = [];
    $resultado = $conexion->query('SHOW TABLES');

    while ($fila = $resultado->fetch_row()) {
        $tablas[] = $fila[0];
    }

    foreach ($tablas as $tabla) {
        $conexion->query('DROP TABLE IF EXISTS `' . $tabla . '`');
    }

    $conexion->query('SET FOREIGN_KEY_CHECKS = 1');

    $conexion->multi_query($sql);

    do {
        if ($res = $conexion->store_result()) {
            $res->free();
        }
    } while ($conexion->next_result());

    if ($conexion->errno) {
        throw new RuntimeException('Error cargando el esquema de pruebas: ' . $conexion->error);
    }
}
