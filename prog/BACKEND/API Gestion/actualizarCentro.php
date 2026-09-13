<?php

require_once "../config/headers.php";
require_once "../controllers/GestionController.php";

$datos = json_decode(file_get_contents("php://input"), true);

$camposRequeridos = ["id_centro_acopio", "nombre", "direccion", "capacidad"];

if (!is_array($datos)) {
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se recibieron datos correctamente"
    ]);
    exit;
}

foreach ($camposRequeridos as $campo) {
    if (!isset($datos[$campo]) || $datos[$campo] === "") {
        echo json_encode([
            "ok" => false,
            "mensaje" => "Falta el campo: " . $campo
        ]);
        exit;
    }
}

$controller = new GestionController();

echo json_encode(
    $controller->actualizarCentro($datos)
);
