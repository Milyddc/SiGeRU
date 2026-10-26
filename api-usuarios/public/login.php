<?php

require_once "../src/config/headers.php";
require_once "../src/controllers/UsuarioController.php";

$datos = json_decode(file_get_contents("php://input"), true);

if (!is_array($datos)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "mensaje" => "No se recibieron datos correctamente"]);
    exit;
}

$controller = new UsuarioController();

echo json_encode($controller->login($datos));
