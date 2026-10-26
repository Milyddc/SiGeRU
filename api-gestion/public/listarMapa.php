<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/controllers/MapaController.php";

requireAuth();

$controller = new MapaController();

echo json_encode($controller->datosMapa());
