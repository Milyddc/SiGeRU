<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/config/request.php";
require_once "../src/controllers/RecorridoController.php";

$usuario = requireAuth(["Administrador", "Operario", "Cuadrilla"]);

$datos = leerJson(["id_recorrido", "recurso", "cantidad"]);

$controller = new RecorridoController();

echo json_encode($controller->registrarRecurso($usuario, $datos));
