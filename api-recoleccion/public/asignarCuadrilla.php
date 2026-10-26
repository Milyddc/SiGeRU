<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/controllers/RecoleccionController.php";

requireAuth(["Administrador", "Operario"]);

$datos = leerJson(["id_ruta"]);

$controller = new RecoleccionController();

echo json_encode(
    $controller->asignarCuadrilla($datos["id_ruta"], $datos["id_cuadrilla"] ?? null)
);
