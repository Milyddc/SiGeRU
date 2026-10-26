<?php

// Envío de emails por SMTP con PHPMailer.
// Regla de oro: un fallo en el envío NUNCA interrumpe la operación principal
// (crear una incidencia, registrar un usuario...). Se devuelve false y el motivo
// queda en Mailer::ultimoError() y en el log del servidor.
class Mailer
{
    // Solo para los tests: si se define, reemplaza al envío real (recibe emails, asunto, cuerpo)
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

    // $emails: lista de direcciones. Van en copia oculta: nadie ve los correos de los demás.
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

        foreach (["/opt/vendor/autoload.php", __DIR__ . "/../vendor/autoload.php"] as $autoload) {
            if (file_exists($autoload)) {
                require_once $autoload;
                break;
            }
        }

        if (!class_exists("PHPMailer\\PHPMailer\\PHPMailer")) {
            self::$ultimoError = "PHPMailer no está instalado en la imagen (reconstruir con --build)";
            error_log("Mailer: " . self::$ultimoError);
            return false;
        }

        try {

            $m = new PHPMailer\PHPMailer\PHPMailer(true);

            $m->isSMTP();
            $m->Host = getenv("SMTP_HOST");
            $m->Port = (int)(getenv("SMTP_PORT") ?: 587);
            $m->SMTPAuth = true;
            $m->Username = getenv("SMTP_USER");
            $m->Password = getenv("SMTP_PASS") ?: "";
            $m->SMTPSecure = getenv("SMTP_SECURE") === "ssl"
                ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS      // puerto 465
                : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;  // puerto 587
            $m->Timeout = 8;
            $m->CharSet = "UTF-8";

            $desde = getenv("MAIL_FROM") ?: getenv("SMTP_USER");
            $m->setFrom($desde, getenv("MAIL_FROM_NAME") ?: "SiGeRU");
            $m->addAddress($desde);

            foreach ($emails as $email) {
                $m->addBCC($email);
            }

            $m->isHTML(false);
            $m->Subject = $asunto;
            $m->Body = $cuerpo;

            $m->send();

            return true;

        } catch (Throwable $e) {

            self::$ultimoError = $e->getMessage();
            error_log("Mailer: no se pudo enviar el email: " . $e->getMessage());

            return false;
        }
    }
}
