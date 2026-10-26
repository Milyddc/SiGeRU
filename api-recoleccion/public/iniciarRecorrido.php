<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/config/request.php";
require_once "../src/controllers/RecorridoController.php";

$usuario = requireAuth(["Administrador", "Operario", "Cuadrilla"]);

$datos = leerJson(["id_ruta", "id_camion"]);

$controller = new RecorridoController();

echo json_encode($controller->iniciar($usuario, (int)$datos["id_ruta"], (int)$datos["id_camion"]));
