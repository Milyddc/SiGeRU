<?php

require_once "../config/headers.php";
require_once "../controllers/UsuarioController.php";

$datos = json_decode(file_get_contents("php://input"), true);

$camposRequeridos = ["id_usuario", "nombre", "apellido", "correo", "rol"];

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

$controller = new UsuarioController();

echo json_encode(
    $controller->actualizar($datos)
);
