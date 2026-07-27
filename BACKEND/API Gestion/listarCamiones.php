<?php

require_once "../../config/headers.php";
require_once "../../controllers/GestionController.php";

$controller = new GestionController();

echo json_encode($controller->listarCamiones());
