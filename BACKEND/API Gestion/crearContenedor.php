<?php

require_once "../../config/headers.php";
require_once "../../controllers/GestionController.php";

$controller = new GestionController();

$datos = json_decode(file_get_contents("php://input"), true);

echo json_encode($controller->crearContenedor($datos));
