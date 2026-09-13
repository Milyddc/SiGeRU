<?php

require_once __DIR__ . "/../../DATABASE/database.php";

class UsuarioController
{

    public function listarUsuarios()
    {
        global $conexion;

        $resultado = $conexion->query("SELECT * FROM Usuario");

        $usuarios = [];

        while ($fila = $resultado->fetch_assoc()) {
            $usuarios[] = $fila;
        }

        return $usuarios;
    }

    public function registrar($datos)
    {
        global $conexion;

        $nombre = $datos["nombre"];
        $apellido = $datos["apellido"];
        $correo = $datos["correo"];
        $password = $datos["password"];
        $rol = $datos["rol"];

        $nombreCompleto = $nombre . " " . $apellido;

        $sql = "INSERT INTO Usuario
                (nombre_completo, email, estado, password, id_rol)
                VALUES (?, ?, 'Activo', ?, ?)";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            return [
                "ok" => false,
                "mensaje" => "Error al preparar el registro",
                "error" => $conexion->error
            ];
        }

        $stmt->bind_param(
            "sssi",
            $nombreCompleto,
            $correo,
            $password,
            $rol
        );

        if ($stmt->execute()) {
            return [
                "ok" => true,
                "mensaje" => "Usuario registrado correctamente"
            ];
        }

        return [
            "ok" => false,
            "mensaje" => "Error al registrar el usuario",
            "error" => $stmt->error
        ];
    }

    public function actualizar($datos)
    {
        global $conexion;

        $id = $datos["id_usuario"];
        $nombre = $datos["nombre"];
        $apellido = $datos["apellido"];
        $correo = $datos["correo"];
        $rol = $datos["rol"];
        $password = $datos["password"] ?? "";

        $nombreCompleto = $nombre . " " . $apellido;

        if ($password !== "") {

            $sql = "UPDATE Usuario
                    SET nombre_completo = ?,
                        email = ?,
                        password = ?,
                        id_rol = ?
                    WHERE id_usuario = ?";

            $stmt = $conexion->prepare($sql);

            if (!$stmt) {
                return [
                    "ok" => false,
                    "mensaje" => "Error al preparar la modificación",
                    "error" => $conexion->error
                ];
            }

            $stmt->bind_param(
                "sssii",
                $nombreCompleto,
                $correo,
                $password,
                $rol,
                $id
            );

        } else {

            $sql = "UPDATE Usuario
                    SET nombre_completo = ?,
                        email = ?,
                        id_rol = ?
                    WHERE id_usuario = ?";

            $stmt = $conexion->prepare($sql);

            if (!$stmt) {
                return [
                    "ok" => false,
                    "mensaje" => "Error al preparar la modificación",
                    "error" => $conexion->error
                ];
            }

            $stmt->bind_param(
                "ssii",
                $nombreCompleto,
                $correo,
                $rol,
                $id
            );
        }

        if ($stmt->execute()) {
            return [
                "ok" => true,
                "mensaje" => "Usuario actualizado correctamente"
            ];
        }

        return [
            "ok" => false,
            "mensaje" => "No se pudo actualizar el usuario",
            "error" => $stmt->error
        ];
    }

    public function login($datos)
    {
        global $conexion;

        $correo = $datos["correo"];
        $password = $datos["password"];

        $sql = "SELECT * FROM Usuario
                WHERE email = ? AND password = ?";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            return [
                "ok" => false,
                "mensaje" => "Error al preparar el login",
                "error" => $conexion->error
            ];
        }

        $stmt->bind_param(
            "ss",
            $correo,
            $password
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {

            $usuario = $resultado->fetch_assoc();

            return [
                "ok" => true,
                "mensaje" => "Login correcto",
                "usuario" => $usuario
            ];
        }

        return [
            "ok" => false,
            "mensaje" => "Correo o contraseña incorrectos"
        ];
    }

    public function eliminar($id)
    {
        global $conexion;

        $sql = "DELETE FROM Usuario WHERE id_usuario = ?";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            return [
                "ok" => false,
                "mensaje" => "Error al preparar la eliminación",
                "error" => $conexion->error
            ];
        }

        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {

            if ($stmt->affected_rows > 0) {
                return [
                    "ok" => true,
                    "mensaje" => "Usuario eliminado correctamente"
                ];
            }

            return [
                "ok" => false,
                "mensaje" => "No se encontró el usuario."
            ];
        }

        if (strpos($stmt->error, "foreign key constraint fails") !== false) {
            return [
                "ok" => false,
                "mensaje" => "No se puede eliminar este usuario porque tiene incidencias o asignaciones de cuadrilla asociadas."
            ];
        }

        return [
            "ok" => false,
            "mensaje" => "No se pudo eliminar el usuario",
            "error" => $stmt->error
        ];
    }
}