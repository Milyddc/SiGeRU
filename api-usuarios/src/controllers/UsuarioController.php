<?php

require_once __DIR__ . "/../database.php";
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/NotificacionController.php";

class UsuarioController
{
    const PASSWORD_MIN = 8;
    const MAX_INTENTOS = 5;
    const MINUTOS_BLOQUEO = 15;
    const ROL_VECINO = 4;

    private function validarPassword(string $password): ?string
    {
        if (strlen($password) < self::PASSWORD_MIN) {
            return "La contraseña debe tener al menos " . self::PASSWORD_MIN . " caracteres";
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return "La contraseña debe incluir letras y números";
        }
        return null;
    }

    private function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ["cost" => 12]);
    }

    public function listarUsuarios()
    {
        global $conexion;

        $resultado = $conexion->query(
            "SELECT id_usuario, nombre_completo, telefono, email, estado,
                    id_rol, id_centro_acopio, fecha_registro
             FROM Usuario
             ORDER BY id_usuario"
        );

        $usuarios = [];

        while ($fila = $resultado->fetch_assoc()) {
            $usuarios[] = $fila;
        }

        return $usuarios;
    }

    public function registrar($datos, bool $esAdmin = false)
    {
        global $conexion;

        $nombre   = trim($datos["nombre"] ?? "");
        $apellido = trim($datos["apellido"] ?? "");
        $correo   = trim($datos["correo"] ?? "");
        $password = (string)($datos["password"] ?? "");

        if ($nombre === "" || $apellido === "") {
            return ["ok" => false, "mensaje" => "Nombre y apellido son obligatorios"];
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ["ok" => false, "mensaje" => "El correo no es válido"];
        }

        $errorPassword = $this->validarPassword($password);
        if ($errorPassword) {
            return ["ok" => false, "mensaje" => $errorPassword];
        }

        $rol    = $esAdmin ? (int)($datos["rol"] ?? self::ROL_VECINO) : self::ROL_VECINO;
        $estado = $esAdmin ? "Activo" : "Pendiente";

        $nombreCompleto = $nombre . " " . $apellido;
        $hash = $this->hashPassword($password);

        $stmt = $conexion->prepare(
            "INSERT INTO Usuario (nombre_completo, email, estado, password, id_rol)
             VALUES (?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            error_log("registrar prepare: " . $conexion->error);
            return ["ok" => false, "mensaje" => "Error al registrar el usuario"];
        }

        $stmt->bind_param("ssssi", $nombreCompleto, $correo, $estado, $hash, $rol);

        if ($stmt->execute()) {

            if (!$esAdmin) {
                $this->avisarNuevoRegistro($nombreCompleto, $correo);
            }

            return [
                "ok" => true,
                "mensaje" => $esAdmin
                    ? "Usuario registrado correctamente"
                    : "Registro recibido. Un administrador debe aprobar tu cuenta."
            ];
        }

        if ($stmt->errno === 1062) {
            return ["ok" => false, "mensaje" => "Ese correo ya está registrado"];
        }

        error_log("registrar execute: " . $stmt->error);
        return ["ok" => false, "mensaje" => "Error al registrar el usuario"];
    }

    public function actualizar($datos)
    {
        global $conexion;

        $id       = (int)$datos["id_usuario"];
        $nombre   = trim($datos["nombre"]);
        $apellido = trim($datos["apellido"]);
        $correo   = trim($datos["correo"]);
        $rol      = (int)$datos["rol"];
        $password = (string)($datos["password"] ?? "");
        $estado   = $datos["estado"] ?? null;

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ["ok" => false, "mensaje" => "El correo no es válido"];
        }

        $nombreCompleto = $nombre . " " . $apellido;

        $campos = "nombre_completo = ?, email = ?, id_rol = ?";
        $tipos  = "ssi";
        $valores = [$nombreCompleto, $correo, $rol];

        if ($password !== "") {
            $errorPassword = $this->validarPassword($password);
            if ($errorPassword) {
                return ["ok" => false, "mensaje" => $errorPassword];
            }
            $campos .= ", password = ?";
            $tipos .= "s";
            $valores[] = $this->hashPassword($password);
        }

        if ($estado !== null && in_array($estado, ["Pendiente", "Activo", "Inactivo"], true)) {
            $campos .= ", estado = ?";
            $tipos .= "s";
            $valores[] = $estado;
        }

        $tipos .= "i";
        $valores[] = $id;

        $stmt = $conexion->prepare("UPDATE Usuario SET $campos WHERE id_usuario = ?");

        if (!$stmt) {
            error_log("actualizar prepare: " . $conexion->error);
            return ["ok" => false, "mensaje" => "No se pudo actualizar el usuario"];
        }

        $stmt->bind_param($tipos, ...$valores);

        if ($stmt->execute()) {
            return ["ok" => true, "mensaje" => "Usuario actualizado correctamente"];
        }

        if ($stmt->errno === 1062) {
            return ["ok" => false, "mensaje" => "Ese correo ya está registrado"];
        }

        error_log("actualizar execute: " . $stmt->error);
        return ["ok" => false, "mensaje" => "No se pudo actualizar el usuario"];
    }

    public function aprobar($id)
    {
        global $conexion;

        $stmt = $conexion->prepare("UPDATE Usuario SET estado = 'Activo' WHERE id_usuario = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $this->avisarAprobacion((int)$id);

            return ["ok" => true, "mensaje" => "Usuario aprobado"];
        }

        return ["ok" => false, "mensaje" => "No se encontró el usuario o ya estaba activo"];
    }

    private function avisarNuevoRegistro(string $nombre, string $correo): void
    {
        try {
            NotificacionController::crear(
                NotificacionController::idsPorRol("Administrador"),
                "Nuevo registro pendiente de aprobación: " . $nombre . " (" . $correo . ")",
                "info",
                null,
                true
            );
        } catch (Throwable $e) {
            error_log("avisarNuevoRegistro: " . $e->getMessage());
        }
    }

    private function avisarAprobacion(int $id): void
    {
        global $conexion;

        try {
            $stmt = $conexion->prepare("SELECT email, nombre_completo FROM Usuario WHERE id_usuario = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();

            if ($u) {
                Mailer::enviar(
                    [$u["email"]],
                    "[SiGeRU] Tu cuenta fue aprobada",
                    "Hola " . $u["nombre_completo"] . ",\n\nTu cuenta de SiGeRU fue aprobada. Ya podés iniciar sesión."
                );
            }
        } catch (Throwable $e) {
            error_log("avisarAprobacion: " . $e->getMessage());
        }
    }

    public function login($datos)
    {
        global $conexion;

        $correo   = trim($datos["correo"] ?? "");
        $password = (string)($datos["password"] ?? "");
        $generico = ["ok" => false, "mensaje" => "Correo o contraseña incorrectos"];

        $stmt = $conexion->prepare(
            "SELECT u.id_usuario, u.nombre_completo, u.email, u.estado, u.password,
                    u.intentos_fallidos, u.bloqueado_hasta, r.nombre AS rol
             FROM Usuario u
             JOIN Rol r ON r.id_rol = u.id_rol
             WHERE u.email = ?"
        );

        if (!$stmt) {
            error_log("login prepare: " . $conexion->error);
            return ["ok" => false, "mensaje" => "Error interno del servidor"];
        }

        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();

        if (!$usuario) {
            return $generico;
        }

        if ($usuario["bloqueado_hasta"] && strtotime($usuario["bloqueado_hasta"]) > time()) {
            return [
                "ok" => false,
                "mensaje" => "Cuenta bloqueada temporalmente por intentos fallidos. Probá más tarde."
            ];
        }

        if (!password_verify($password, $usuario["password"])) {

            $intentos = (int)$usuario["intentos_fallidos"] + 1;

            if ($intentos >= self::MAX_INTENTOS) {
                $upd = $conexion->prepare(
                    "UPDATE Usuario
                     SET intentos_fallidos = 0,
                         bloqueado_hasta = DATE_ADD(NOW(), INTERVAL " . self::MINUTOS_BLOQUEO . " MINUTE)
                     WHERE id_usuario = ?"
                );
                $upd->bind_param("i", $usuario["id_usuario"]);
            } else {
                $upd = $conexion->prepare(
                    "UPDATE Usuario SET intentos_fallidos = ? WHERE id_usuario = ?"
                );
                $upd->bind_param("ii", $intentos, $usuario["id_usuario"]);
            }

            $upd->execute();

            return $generico;
        }

        if ($usuario["estado"] !== "Activo") {
            return [
                "ok" => false,
                "mensaje" => $usuario["estado"] === "Pendiente"
                    ? "Tu cuenta está pendiente de aprobación"
                    : "Tu cuenta está inactiva"
            ];
        }

        $reset = $conexion->prepare(
            "UPDATE Usuario SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_usuario = ?"
        );
        $reset->bind_param("i", $usuario["id_usuario"]);
        $reset->execute();

        if (password_needs_rehash($usuario["password"], PASSWORD_BCRYPT, ["cost" => 12])) {
            $nuevoHash = $this->hashPassword($password);
            $rh = $conexion->prepare("UPDATE Usuario SET password = ? WHERE id_usuario = ?");
            $rh->bind_param("si", $nuevoHash, $usuario["id_usuario"]);
            $rh->execute();
        }

        $token = jwtCrear([
            "sub"    => (int)$usuario["id_usuario"],
            "nombre" => $usuario["nombre_completo"],
            "rol"    => $usuario["rol"]
        ]);

        return [
            "ok" => true,
            "mensaje" => "Login correcto",
            "token" => $token,
            "usuario" => [
                "id_usuario" => (int)$usuario["id_usuario"],
                "nombre_completo" => $usuario["nombre_completo"],
                "email" => $usuario["email"],
                "rol" => $usuario["rol"]
            ]
        ];
    }

    public function eliminar($id)
    {
        global $conexion;

        $stmt = $conexion->prepare("DELETE FROM Usuario WHERE id_usuario = ?");

        if (!$stmt) {
            error_log("eliminar prepare: " . $conexion->error);
            return ["ok" => false, "mensaje" => "No se pudo eliminar el usuario"];
        }

        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            return $stmt->affected_rows > 0
                ? ["ok" => true, "mensaje" => "Usuario eliminado correctamente"]
                : ["ok" => false, "mensaje" => "No se encontró el usuario."];
        }

        if (strpos($stmt->error, "foreign key constraint fails") !== false) {
            return [
                "ok" => false,
                "mensaje" => "No se puede eliminar este usuario porque tiene incidencias o asignaciones de cuadrilla asociadas."
            ];
        }

        error_log("eliminar execute: " . $stmt->error);
        return ["ok" => false, "mensaje" => "No se pudo eliminar el usuario"];
    }
}
