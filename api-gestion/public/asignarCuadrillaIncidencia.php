<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/config/request.php";
require_once "../src/controllers/IncidenciaController.php";

$usuario = requireAuth(["Administrador", "Operario"]);

$datos = leerJson(["id_incidencia", "id_cuadrilla"]);

$controller = new IncidenciaController();

echo json_encode(
    $controller->asignarCuadrilla($usuario, (int)$datos["id_incidencia"], (int)$datos["id_cuadrilla"])
);
