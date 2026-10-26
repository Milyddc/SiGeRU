<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/controllers/GestionController.php";

requireAuth(["Administrador", "Operario"]);

$datos = json_decode(file_get_contents("php://input"), true);

if (!is_array($datos) || !isset($datos["id_centro_acopio"])) {
    echo json_encode([
        "ok" => false,
        "mensaje" => "ID de centro no válido"
    ]);
    exit;
}

$controller = new GestionController();

echo json_encode(
    $controller->eliminarCentro($datos["id_centro_acopio"])
);
