<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/config/request.php";
require_once "../src/controllers/IncidenciaController.php";

$usuario = requireAuth(["Administrador", "Operario", "Cuadrilla"]);

$datos = leerJson(["id_incidencia", "estado"]);

$controller = new IncidenciaController();

echo json_encode(
    $controller->cambiarEstado($usuario, (int)$datos["id_incidencia"], $datos["estado"], $datos["comentario"] ?? null)
);
