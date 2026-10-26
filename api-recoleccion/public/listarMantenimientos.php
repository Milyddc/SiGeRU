<?php

require_once "../src/config/headers.php";
require_once "../src/config/auth.php";
require_once "../src/config/request.php";
require_once "../src/controllers/CamionController.php";

$usuario = requireAuth();

$idCamion = isset($_GET["id_camion"]) ? (int)$_GET["id_camion"] : null;

$controller = new CamionController();

echo json_encode($controller->listarMantenimientos($idCamion));
