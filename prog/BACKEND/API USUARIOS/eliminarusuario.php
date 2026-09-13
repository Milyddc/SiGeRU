<?php

require_once "../config/headers.php";
require_once "../controllers/UsuarioController.php";

$datos = json_decode(
    file_get_contents("php://input"),
    true
);

if (!is_array($datos) || !isset($datos["id_usuario"])) {

    echo json_encode([
        "ok" => false,
        "mensaje" => "ID de usuario no válido"
    ]);

    exit;
}

$controller = new UsuarioController();

echo json_encode(
    $controller->eliminar($datos["id_usuario"])
);
