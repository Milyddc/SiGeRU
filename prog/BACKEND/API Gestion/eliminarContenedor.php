<?php

require_once "../config/headers.php";
require_once "../controllers/GestionController.php";

$datos = json_decode(file_get_contents("php://input"), true);

if (!is_array($datos) || !isset($datos["id_contenedor"])) {
    echo json_encode([
        "ok" => false,
        "mensaje" => "ID de contenedor no válido"
    ]);
    exit;
}

$controller = new GestionController();

echo json_encode(
    $controller->eliminarContenedor($datos["id_contenedor"])
);
