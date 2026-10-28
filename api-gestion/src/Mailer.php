<?php

class Mailer
{
    public static $enviadorPrueba = null;

    private static string $ultimoError = "";

    public static function ultimoError(): string
    {
        return self::$ultimoError;
    }

    public static function habilitado(): bool
    {
        return filter_var(getenv("MAIL_ENABLED"), FILTER_VALIDATE_BOOLEAN)
            && getenv("SMTP_HOST") && getenv("SMTP_USER");
    }

    public static function urlApp(): string
    {
        return rtrim(getenv("APP_URL") ?: "http://localhost:8080/app", "/");
    }

    public static function enviar(array $emails, string $asunto, string $cuerpo): bool
    {
        self::$ultimoError = "";

        $emails = array_values(array_unique(array_filter($emails, function ($e) {
            return filter_var($e, FILTER_VALIDATE_EMAIL);
        })));

        if (!$emails) {
            self::$ultimoError = "Sin destinatarios válidos";
            return false;
        }

        $cuerpo .= "\n\n--\nMensaje automático de SiGeRU. No respondas a este correo.\n"
            . "Ingresá al sistema: " . self::urlApp() . "/login.html";

        if (self::$enviadorPrueba !== null) {
            try {
                return (bool)(self::$enviadorPrueba)($emails, $asunto, $cuerpo);
            } catch (Throwable $e) {
                self::$ultimoError = $e->getMessage();
                return false;
            }
        }

        if (!self::habilitado()) {
            self::$ultimoError = "El envío de emails está deshabilitado (MAIL_ENABLED=false o faltan datos SMTP)";
            return false;
        }

        try {
            self::enviarPorSmtp($emails, $asunto, $cuerpo);
            return true;
        } catch (Throwable $e) {
            self::$ultimoError = $e->getMessage();
            error_log("Mailer: no se pudo enviar el email: " . $e->getMessage());
            return false;
        }
    }

    private static function leerRespuesta($socket): array
    {
        $texto = "";

        while (($linea = fgets($socket, 515)) !== false) {
            $texto .= $linea;

            if (strlen($linea) < 4 || $linea[3] === " ") {
                break;
            }
        }

        if ($texto === "") {
            throw new RuntimeException("El servidor SMTP no respondió (tiempo agotado)");
        }

        return [(int)substr($texto, 0, 3), trim($texto)];
    }

    private static function comando($socket, ?string $linea, array $codigosOk, string $etiqueta): string
    {
        if ($linea !== null) {
            fwrite($socket, $linea . "\r\n");
        }

        [$codigo, $texto] = self::leerRespuesta($socket);

        if (!in_array($codigo, $codigosOk, true)) {
            throw new RuntimeException("SMTP rechazó " . $etiqueta . ": " . $texto);
        }

        return $texto;
    }

    private static function enviarPorSmtp(array $emails, string $asunto, string $cuerpo): void
    {
        $host = getenv("SMTP_HOST");
        $puerto = (int)(getenv("SMTP_PORT") ?: 587);
        $usaSsl = getenv("SMTP_SECURE") === "ssl";
        $usuario = getenv("SMTP_USER");
        $clave = getenv("SMTP_PASS") ?: "";
        $desde = getenv("MAIL_FROM") ?: $usuario;
        $nombreDesde = getenv("MAIL_FROM_NAME") ?: "SiGeRU";

        if (!filter_var($desde, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("MAIL_FROM no es una dirección válida");
        }

        $contexto = stream_context_create([
            "ssl" => ["verify_peer" => true, "verify_peer_name" => true]
        ]);

        $socket = @stream_socket_client(
            ($usaSsl ? "ssl://" : "tcp://") . $host . ":" . $puerto,
            $errno,
            $errstr,
            8,
            STREAM_CLIENT_CONNECT,
            $contexto
        );

        if (!$socket) {
            throw new RuntimeException("No se pudo conectar a $host:$puerto ($errstr)");
        }

        stream_set_timeout($socket, 8);

        try {

            self::comando($socket, null, [220], "la conexión");
            self::comando($socket, "EHLO sigeru.local", [250], "EHLO");

            if (!$usaSsl) {
                self::comando($socket, "STARTTLS", [220], "STARTTLS");

                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException("No se pudo establecer la conexión segura (TLS) con el servidor SMTP");
                }

                self::comando($socket, "EHLO sigeru.local", [250], "EHLO (segura)");
            }

            self::comando($socket, "AUTH LOGIN", [334], "AUTH LOGIN");
            self::comando($socket, base64_encode($usuario), [334], "el usuario SMTP");
            self::comando($socket, base64_encode($clave), [235], "la contraseña SMTP (revisá SMTP_USER y SMTP_PASS)");

            self::comando($socket, "MAIL FROM:<" . $desde . ">", [250], "el remitente");

            foreach ($emails as $email) {
                self::comando($socket, "RCPT TO:<" . $email . ">", [250, 251], "un destinatario");
            }

            self::comando($socket, "DATA", [354], "DATA");

            $cabeceras = [
                "Date: " . date("r"),
                "From: =?UTF-8?B?" . base64_encode($nombreDesde) . "?= <" . $desde . ">",
                "To: <" . $desde . ">",
                "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=",
                "Message-ID: <" . bin2hex(random_bytes(12)) . "@sigeru.local>",
                "MIME-Version: 1.0",
                "Content-Type: text/plain; charset=UTF-8",
                "Content-Transfer-Encoding: base64"
            ];

            $mensaje = implode("\r\n", $cabeceras) . "\r\n\r\n"
                . chunk_split(base64_encode($cuerpo), 76, "\r\n");

            self::comando($socket, $mensaje . ".", [250], "el mensaje");
            self::comando($socket, "QUIT", [221], "QUIT");

        } finally {
            fclose($socket);
        }
    }
}
