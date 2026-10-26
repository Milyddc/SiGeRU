<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/config/request.php";
require_once "../src/controllers/CamionController.php";

$usuario = requireAuth(["Administrador", "Operario"]);

$datos = leerJson(["id_mantenimiento"]);

$controller = new CamionController();

echo json_encode($controller->completarMantenimiento((int)$datos["id_mantenimiento"]));
