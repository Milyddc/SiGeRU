<?php

require_once __DIR__ . "/../../DATABASE/database.php";

class GestionController
{

    public function listarContenedores()
    {
        global $conexion;

        $resultado = $conexion->query(
            "SELECT c.*, 
                    t.nombre AS tipo_residuo,
                    r.nombre AS ruta,
                    ca.nombre AS centro_acopio
             FROM contenedor c
             LEFT JOIN tipo_residuo t
             ON c.id_tipo_residuo = t.id_tipo_residuo
             LEFT JOIN ruta r
             ON c.id_ruta = r.id_ruta
             LEFT JOIN centro_acopio ca
             ON c.id_centro_acopio = ca.id_centro_acopio"
        );

        $contenedores = [];

        while ($fila = $resultado->fetch_assoc()) {
            $contenedores[] = $fila;
        }

        return $contenedores;
    }


    public function crearContenedor($datos)
    {
        global $conexion;

        $codigo = $datos["codigo"];
        $ubicacion = $datos["ubicacion"];
        $estado = $datos["estado"];
        $capacidad = $datos["capacidad"];
        $idTipo = $datos["id_tipo_residuo"];
        $idRuta = $datos["id_ruta"] ?? null;
        $idCentro = $datos["id_centro_acopio"] ?? null;

        $sql = "INSERT INTO contenedor
                (codigo, ubicacion, estado, capacidad, id_tipo_residuo, id_ruta, id_centro_acopio)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            return [
                "ok" => false,
                "mensaje" => "Error al preparar el registro",
                "error" => $conexion->error
            ];
        }

        $stmt->bind_param(
            "sssiiii",
            $codigo,
            $ubicacion,
            $estado,
            $capacidad,
            $idTipo,
            $idRuta,
            $idCentro
        );

        if ($stmt->execute()) {
            return [
                "ok" => true,
                "mensaje" => "Contenedor registrado correctamente"
            ];
        }

        return [
            "ok" => false,
            "mensaje" => "No se pudo registrar el contenedor",
            "error" => $stmt->error
        ];
    }


    public function actualizarContenedor($datos)
    {
        global $conexion;

        $id = $datos["id_contenedor"];
        $codigo = $datos["codigo"];
        $ubicacion = $datos["ubicacion"];
        $estado = $datos["estado"];
        $capacidad = $datos["capacidad"];
        $idTipo = $datos["id_tipo_residuo"];
        $idRuta = $datos["id_ruta"] ?? null;
        $idCentro = $datos["id_centro_acopio"] ?? null;

        $sql = "UPDATE contenedor
                SET codigo = ?,
                    ubicacion = ?,
                    estado = ?,
                    capacidad = ?,
                    id_tipo_residuo = ?,
                    id_ruta = ?,
                    id_centro_acopio = ?
                WHERE id_contenedor = ?";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            return [
                "ok" => false,
                "mensaje" => "Error al preparar la modificación",
                "error" => $conexion->error
            ];
        }

        $stmt->bind_param(
            "sssiiiii",
            $codigo,
            $ubicacion,
            $estado,
            $capacidad,
            $idTipo,
            $idRuta,
            $idCentro,
            $id
        );

        if ($stmt->execute()) {
            return [
                "ok" => true,
                "mensaje" => "Contenedor actualizado correctamente"
            ];
        }

        return [
            "ok" => false,
            "mensaje" => "No se pudo actualizar el contenedor",
            "error" => $stmt->error
        ];
    }


    public function eliminarContenedor($id)
    {
        global $conexion;

        $stmt = $conexion->prepare(
            "DELETE FROM contenedor WHERE id_contenedor = ?"
        );

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
                    "mensaje" => "Contenedor eliminado correctamente"
                ];
            }

            return [
                "ok" => false,
                "mensaje" => "No se encontró el contenedor."
            ];
        }

        if (strpos($stmt->error, "foreign key constraint fails") !== false) {
            return [
                "ok" => false,
                "mensaje" => "No se puede eliminar este contenedor porque tiene incidencias asociadas."
            ];
        }

        return [
            "ok" => false,
            "mensaje" => "No se pudo eliminar el contenedor",
            "error" => $stmt->error
        ];
    }


    public function listarCamiones()
    {
        global $conexion;

        $resultado = $conexion->query(
            "SELECT * FROM camion"
        );

        $camiones = [];

        while ($fila = $resultado->fetch_assoc()) {
            $camiones[] = $fila;
        }

        return $camiones;
    }


    public function crearCamion($datos)
    {
        global $conexion;

        $matricula = $datos["matricula"];
        $modelo = $datos["modelo"];
        $capacidad = $datos["capacidad"];
        $estado = $datos["estado"];
        $idCuadrilla = $datos["id_cuadrilla"];

        $sql = "INSERT INTO camion
                (matricula, modelo, capacidad, estado, id_cuadrilla)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            return [
                "ok" => false,
                "mensaje" => "Error al preparar el registro",
                "error" => $conexion->error
            ];
        }

        $stmt->bind_param(
            "ssisi",
            $matricula,
            $modelo,
            $capacidad,
            $estado,
            $idCuadrilla
        );

        if ($stmt->execute()) {
            return [
                "ok" => true,
                "mensaje" => "Camión registrado correctamente"
            ];
        }

        return [
            "ok" => false,
            "mensaje" => "No se pudo registrar el camión",
            "error" => $stmt->error
        ];
    }


    public function actualizarCamion($datos)
    {
        global $conexion;

        $id = $datos["id_camion"];
        $matricula = $datos["matricula"];
        $modelo = $datos["modelo"];
        $capacidad = $datos["capacidad"];
        $estado = $datos["estado"];
        $idCuadrilla = $datos["id_cuadrilla"];

        $sql = "UPDATE camion
                SET matricula = ?,
                    modelo = ?,
                    capacidad = ?,
                    estado = ?,
                    id_cuadrilla = ?
                WHERE id_camion = ?";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            return [
                "ok" => false,
                "mensaje" => "Error al preparar la modificación",
                "error" => $conexion->error
            ];
        }

        $stmt->bind_param(
            "ssisii",
            $matricula,
            $modelo,
            $capacidad,
            $estado,
            $idCuadrilla,
            $id
        );

        if ($stmt->execute()) {
            return [
                "ok" => true,
                "mensaje" => "Camión actualizado correctamente"
            ];
        }

        return [
            "ok" => false,
            "mensaje" => "No se pudo actualizar el camión",
            "error" => $stmt->error
        ];
    }


    public function eliminarCamion($id)
    {
        global $conexion;

        $stmt = $conexion->prepare(
            "DELETE FROM camion WHERE id_camion = ?"
        );

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
                    "mensaje" => "Camión eliminado correctamente"
                ];
            }

            return [
                "ok" => false,
                "mensaje" => "No se encontró el camión."
            ];
        }

        return [
            "ok" => false,
            "mensaje" => "No se pudo eliminar el camión",
            "error" => $stmt->error
        ];
    }


    public function listarCentros()
    {
        global $conexion;

        $resultado = $conexion->query(
            "SELECT * FROM centro_acopio"
        );

        $centros = [];

        while ($fila = $resultado->fetch_assoc()) {
            $centros[] = $fila;
        }

        return $centros;
    }


    public function crearCentro($datos)
    {
        global $conexion;

        $nombre = $datos["nombre"];
        $direccion = $datos["direccion"];
        $capacidad = $datos["capacidad"];

        $sql = "INSERT INTO centro_acopio
                (nombre, direccion, capacidad)
                VALUES (?, ?, ?)";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            return [
                "ok" => false,
                "mensaje" => "Error al preparar el registro",
                "error" => $conexion->error
            ];
        }

        $stmt->bind_param(
            "ssi",
            $nombre,
            $direccion,
            $capacidad
        );

        if ($stmt->execute()) {
            return [
                "ok" => true,
                "mensaje" => "Centro de acopio registrado correctamente"
            ];
        }

        return [
            "ok" => false,
            "mensaje" => "No se pudo registrar el centro",
            "error" => $stmt->error
        ];
    }


    public function actualizarCentro($datos)
    {
        global $conexion;

        $id = $datos["id_centro_acopio"];
        $nombre = $datos["nombre"];
        $direccion = $datos["direccion"];
        $capacidad = $datos["capacidad"];

        $sql = "UPDATE centro_acopio
                SET nombre = ?,
                    direccion = ?,
                    capacidad = ?
                WHERE id_centro_acopio = ?";

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
            $nombre,
            $direccion,
            $capacidad,
            $id
        );

        if ($stmt->execute()) {
            return [
                "ok" => true,
                "mensaje" => "Centro actualizado correctamente"
            ];
        }

        return [
            "ok" => false,
            "mensaje" => "No se pudo actualizar el centro",
            "error" => $stmt->error
        ];
    }


    public function eliminarCentro($id)
    {
        global $conexion;

        $stmt = $conexion->prepare(
            "DELETE FROM centro_acopio WHERE id_centro_acopio = ?"
        );

        if (!$stmt) {
            return [
                "ok" => false,
                "mensaje" => "No se pudo eliminar el centro.",
                "error" => $conexion->error
            ];
        }

        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {

            if ($stmt->affected_rows > 0) {
                return [
                    "ok" => true,
                    "mensaje" => "Centro eliminado correctamente."
                ];
            }

            return [
                "ok" => false,
                "mensaje" => "No se encontró el centro de acopio."
            ];
        }

        if (strpos($stmt->error, "foreign key constraint fails") !== false) {
            return [
                "ok" => false,
                "mensaje" => "No se puede eliminar este centro porque tiene usuarios asignados a él."
            ];
        }

        return [
            "ok" => false,
            "mensaje" => "No se pudo eliminar el centro.",
            "error" => $stmt->error
        ];
    }
}