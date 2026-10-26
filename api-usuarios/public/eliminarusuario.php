<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/controllers/UsuarioController.php";

$actual = requireAuth(["Administrador"]);

$datos = json_decode(file_get_contents("php://input"), true);

if (!is_array($datos) || !isset($datos["id_usuario"])) {
    http_response_code(400);
    echo json_encode(["ok" => false, "mensaje" => "ID de usuario no válido"]);
    exit;
}

if ((int)$datos["id_usuario"] === (int)$actual["sub"]) {
    http_response_code(400);
    echo json_encode(["ok" => false, "mensaje" => "No podés eliminar tu propio usuario"]);
    exit;
}

$controller = new UsuarioController();

echo json_encode($controller->eliminar((int)$datos["id_usuario"]));
