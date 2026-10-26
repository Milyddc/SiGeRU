<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/config/request.php";
require_once "../src/controllers/EstadisticaController.php";

$usuario = requireAuth(["Administrador", "Operario"]);

$controller = new EstadisticaController();

echo json_encode($controller->resumen());
