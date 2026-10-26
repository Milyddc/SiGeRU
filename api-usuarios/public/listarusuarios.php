<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/controllers/UsuarioController.php";

requireAuth(["Administrador"]);

$controller = new UsuarioController();

echo json_encode($controller->listarUsuarios());
