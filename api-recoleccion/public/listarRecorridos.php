<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/config/request.php";
require_once "../src/controllers/RecorridoController.php";

$usuario = requireAuth();

$controller = new RecorridoController();

echo json_encode($controller->listar($usuario));
