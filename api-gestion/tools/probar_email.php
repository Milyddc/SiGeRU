<?php

require_once __DIR__ . '/../src/Mailer.php';

$destino = $argv[1] ?? null;

if (!$destino) {
    fwrite(STDERR, "Uso: php tools/probar_email.php destino@correo.com\n");
    exit(1);
}

echo "MAIL_ENABLED:   " . (Mailer::habilitado() ? "sí" : "NO (revisá MAIL_ENABLED, SMTP_HOST y SMTP_USER en .env)") . "\n";
echo "Servidor SMTP:  " . (getenv('SMTP_HOST') ?: '(sin definir)') . ':' . (getenv('SMTP_PORT') ?: '587') . "\n";
echo "Usuario SMTP:   " . (getenv('SMTP_USER') ?: '(sin definir)') . "\n";
echo "Enviando a $destino ...\n";

$ok = Mailer::enviar(
    [$destino],
    '[SiGeRU] Prueba de envío',
    'Si recibís este mensaje, el envío de emails de SiGeRU está funcionando correctamente.'
);

if ($ok) {
    echo "OK: email enviado. Revisá la bandeja de entrada (y la carpeta de spam).\n";
    exit(0);
}

echo "FALLÓ: " . Mailer::ultimoError() . "\n";
exit(1);
