<?php

require_once "../config/headers.php";
require_once "../controllers/UsuarioController.php";

$datos = json_decode(file_get_contents("php://input"), true);

if (!is_array($datos)) {
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se recibieron datos correctamente",
        "datos_recibidos" => $datos
    ]);
    exit;
}

$controller = new UsuarioController();

echo json_encode($controller->login($datos));