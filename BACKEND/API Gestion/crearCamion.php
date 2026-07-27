<?php

require_once "../../config/headers.php";
require_once "../../CONTROLADORES/controladorgestion.php";

$controller = new controladorgestion.php();

$datos = json_decode(file_get_contents("php://input"), true);

echo json_encode($controller->crearcamion($datos));