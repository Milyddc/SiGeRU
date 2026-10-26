<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/config/request.php";
require_once "../src/controllers/CamionController.php";

$usuario = requireAuth(["Administrador", "Operario"]);

$datos = leerJson(["id_camion", "descripcion", "fecha_programada"]);

$controller = new CamionController();

echo json_encode($controller->programarMantenimiento($datos));
