<?php

require_once __DIR__ . "/../database.php";
require_once __DIR__ . "/../Mailer.php";

class NotificacionController
{
    // Crea una notificación individual por cada destinatario (así cada uno
    // marca como leída la suya sin afectar a los demás).
    // $email: true fuerza el envío por email, false lo evita; null = solo si es una alerta.
    public static function crear(array $idsUsuario, string $mensaje, string $tipo = "info", ?int $excluir = null, ?bool $email = null): void
    {
        global $conexion;

        $ids = array_unique(array_map("intval", $idsUsuario));
        $ids = array_filter($ids, function ($id) use ($excluir) {
            return $id > 0 && $id !== $excluir;
        });

        if (!$ids) {
            return;
        }

        $tipo = $tipo === "alerta" ? "alerta" : "info";
        $mensaje = mb_substr($mensaje, 0, 250);

        $stmt = $conexion->prepare(
            "INSERT INTO Notificacion (id_usuario, mensaje, tipo) VALUES (?, ?, ?)"
        );

        if (!$stmt) {
            error_log("Notificacion crear prepare: " . $conexion->error);
            return;
        }

        foreach ($ids as $id) {
            $stmt->bind_param("iss", $id, $mensaje, $tipo);
            $stmt->execute();
        }

        if ($email ?? ($tipo === "alerta")) {
            self::enviarEmail(array_values($ids), $mensaje, $tipo);
        }
    }

    // Envía el mismo aviso por email. Si algo falla, la notificación interna ya quedó guardada.
    private static function enviarEmail(array $ids, string $mensaje, string $tipo): void
    {
        global $conexion;

        try {

            $marcas = implode(",", array_fill(0, count($ids), "?"));

            $stmt = $conexion->prepare(
                "SELECT email FROM Usuario WHERE id_usuario IN ($marcas) AND estado = 'Activo'"
            );
            $stmt->bind_param(str_repeat("i", count($ids)), ...$ids);
            $stmt->execute();

            $emails = [];
            $res = $stmt->get_result();

            while ($fila = $res->fetch_assoc()) {
                $emails[] = $fila["email"];
            }

            if (!$emails) {
                return;
            }

            $asunto = "[SiGeRU] " . ($tipo === "alerta" ? "Alerta: " : "") . mb_substr($mensaje, 0, 90);

            Mailer::enviar($emails, $asunto, $mensaje);

        } catch (Throwable $e) {
            error_log("Notificacion email: " . $e->getMessage());
        }
    }

    public static function idsPorRol(string $rol): array
    {
        global $conexion;

        $stmt = $conexion->prepare(
            "SELECT u.id_usuario FROM Usuario u
             JOIN Rol r ON r.id_rol = u.id_rol
             WHERE r.nombre = ? AND u.estado = 'Activo'"
        );
        $stmt->bind_param("s", $rol);
        $stmt->execute();

        $ids = [];
        $res = $stmt->get_result();

        while ($fila = $res->fetch_assoc()) {
            $ids[] = (int)$fila["id_usuario"];
        }

        return $ids;
    }

    public static function idsPorCuadrilla(int $idCuadrilla): array
    {
        global $conexion;

        $stmt = $conexion->prepare(
            "SELECT id_usuario FROM Cuadrilla_Usuario WHERE id_cuadrilla = ?"
        );
        $stmt->bind_param("i", $idCuadrilla);
        $stmt->execute();

        $ids = [];
        $res = $stmt->get_result();

        while ($fila = $res->fetch_assoc()) {
            $ids[] = (int)$fila["id_usuario"];
        }

        return $ids;
    }

    public function listar(int $idUsuario): array
    {
        global $conexion;

        $stmt = $conexion->prepare(
            "SELECT id_notificacion, mensaje, tipo, leida, fecha
             FROM Notificacion
             WHERE id_usuario = ?
             ORDER BY fecha DESC, id_notificacion DESC
             LIMIT 30"
        );
        $stmt->bind_param("i", $idUsuario);
        $stmt->execute();

        $items = [];
        $res = $stmt->get_result();

        while ($fila = $res->fetch_assoc()) {
            $fila["id_notificacion"] = (int)$fila["id_notificacion"];
            $fila["leida"] = (int)$fila["leida"];
            $items[] = $fila;
        }

        $cuenta = $conexion->prepare(
            "SELECT COUNT(*) AS n FROM Notificacion WHERE id_usuario = ? AND leida = 0"
        );
        $cuenta->bind_param("i", $idUsuario);
        $cuenta->execute();
        $noLeidas = (int)$cuenta->get_result()->fetch_assoc()["n"];

        return ["notificaciones" => $items, "no_leidas" => $noLeidas];
    }

    // $id = null marca todas las del usuario como leídas
    public function marcarLeida(int $idUsuario, ?int $id): array
    {
        global $conexion;

        if ($id) {
            $stmt = $conexion->prepare(
                "UPDATE Notificacion SET leida = 1 WHERE id_notificacion = ? AND id_usuario = ?"
            );
            $stmt->bind_param("ii", $id, $idUsuario);
        } else {
            $stmt = $conexion->prepare(
                "UPDATE Notificacion SET leida = 1 WHERE id_usuario = ?"
            );
            $stmt->bind_param("i", $idUsuario);
        }

        $stmt->execute();

        return ["ok" => true, "mensaje" => "Notificaciones actualizadas"];
    }
}
