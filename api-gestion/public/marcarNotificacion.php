<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/config/request.php";
require_once "../src/controllers/NotificacionController.php";

$usuario = requireAuth();

$datos = json_decode(file_get_contents("php://input"), true);

$id = (is_array($datos) && !empty($datos["id_notificacion"])) ? (int)$datos["id_notificacion"] : null;

$controller = new NotificacionController();

echo json_encode($controller->marcarLeida((int)$usuario["sub"], $id));
