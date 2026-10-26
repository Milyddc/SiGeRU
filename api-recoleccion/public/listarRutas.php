<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/controllers/RecoleccionController.php";

requireAuth();

$controller = new RecoleccionController();

echo json_encode($controller->listarRutas());
