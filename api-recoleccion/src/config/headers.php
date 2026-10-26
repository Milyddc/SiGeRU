<?php

// No mostrar errores internos al cliente (se registran en el log del servidor)
ini_set("display_errors", "0");
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");

// CORS: solo si se define un origen permitido en .env (CORS_ORIGIN). Nunca "*".
$origenPermitido = getenv("CORS_ORIGIN");
if ($origenPermitido) {
    header("Access-Control-Allow-Origin: " . $origenPermitido);
    header("Vary: Origin");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
}

// Cabeceras de seguridad (se mencionan en el informe OWASP)
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");
header("Cache-Control: no-store");

if (($_SERVER["REQUEST_METHOD"] ?? "") === "OPTIONS") {
    http_response_code(204);
    exit;
}
