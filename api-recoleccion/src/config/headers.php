<?php

ini_set("display_errors", "0");
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");

$origenPermitido = getenv("CORS_ORIGIN");
if ($origenPermitido) {
    header("Access-Control-Allow-Origin: " . $origenPermitido);
    header("Vary: Origin");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
}

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");
header("Cache-Control: no-store");

if (($_SERVER["REQUEST_METHOD"] ?? "") === "OPTIONS") {
    http_response_code(204);
    exit;
}
