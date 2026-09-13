<?php

require_once "../config/headers.php";
require_once "../controllers/UsuarioController.php";

$controller = new UsuarioController();

$usuarios = $controller->listarusuarios();

echo json_encode($usuarios);
