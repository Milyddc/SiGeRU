<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/controllers/UsuarioController.php";

requireAuth(["Administrador"]);

$datos = json_decode(file_get_contents("php://input"), true);

if (!is_array($datos) || !isset($datos["id_usuario"])) {
    http_response_code(400);
    echo json_encode(["ok" => false, "mensaje" => "ID de usuario no válido"]);
    exit;
}

$controller = new UsuarioController();

echo json_encode($controller->aprobar((int)$datos["id_usuario"]));
