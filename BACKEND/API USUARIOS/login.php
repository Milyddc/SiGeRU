<?php

require_once "../../config/headers.php";
require_once "../../controllers/UsuarioController.php";

$controller = new UsuarioController();

$datos = json_decode(file_get_contents("php://input"), true);

echo json_encode($controller->login($datos));
