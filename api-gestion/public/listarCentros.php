<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/controllers/GestionController.php";

requireAuth();

$controller = new GestionController();

echo json_encode(
    $controller->listarCentros()
);
