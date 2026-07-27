<?php

require_once "../../config/headers.php";
require_once "../../controllers/UsuarioController.php";

$controller = new UsuarioController();

echo json_encode($controller->listarUsuarios());
