<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/config/request.php";
require_once "../src/controllers/CamionController.php";

$usuario = requireAuth(["Administrador", "Operario"]);

$datos = leerJson(["id_camion", "matricula", "capacidad", "estado", "id_cuadrilla"]);

$controller = new CamionController();

echo json_encode($controller->actualizar($datos));
