<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/controllers/RecoleccionController.php";

requireAuth(["Administrador", "Operario"]);

$datos = leerJson(["nombre", "zona"]);

$controller = new RecoleccionController();

echo json_encode($controller->crearRuta($datos));
