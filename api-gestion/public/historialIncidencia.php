<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/config/request.php";
require_once "../src/controllers/IncidenciaController.php";

$usuario = requireAuth();

$id = (int)($_GET["id_incidencia"] ?? 0);

$controller = new IncidenciaController();

echo json_encode($controller->historial($usuario, $id));
