<?php

require_once "../config/headers.php";
require_once "../controllers/GestionController.php";

$datos = json_decode(file_get_contents("php://input"), true);

if (!is_array($datos) || !isset($datos["id_camion"])) {
    echo json_encode([
        "ok" => false,
        "mensaje" => "ID de camión no válido"
    ]);
    exit;
}

$controller = new GestionController();

echo json_encode(
    $controller->eliminarCamion($datos["id_camion"])
);
