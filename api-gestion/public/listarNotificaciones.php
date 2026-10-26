<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/config/request.php";
require_once "../src/controllers/NotificacionController.php";

$usuario = requireAuth();

$controller = new NotificacionController();

echo json_encode($controller->listar((int)$usuario["sub"]));
