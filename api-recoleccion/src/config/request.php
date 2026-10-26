<?php

// Lee el JSON del cuerpo de la petición y valida campos obligatorios.
// Corta con 400 si algo falta. Devuelve el array de datos.
function leerJson(array $requeridos = []): array
{
    $datos = json_decode(file_get_contents("php://input"), true);

    if (!is_array($datos)) {
        http_response_code(400);
        echo json_encode(["ok" => false, "mensaje" => "No se recibieron datos correctamente"]);
        exit;
    }

    foreach ($requeridos as $campo) {
        if (!isset($datos[$campo]) || $datos[$campo] === "") {
            http_response_code(400);
            echo json_encode(["ok" => false, "mensaje" => "Falta el campo: " . $campo]);
            exit;
        }
    }

    return $datos;
}

function enteroONulo($valor): ?int
{
    return ($valor === null || $valor === "" || $valor === 0 || $valor === "0") ? null : (int)$valor;
}
