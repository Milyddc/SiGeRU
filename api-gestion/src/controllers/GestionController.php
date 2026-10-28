<?php

require_once __DIR__ . "/../database.php";

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
             FROM Contenedor c
             LEFT JOIN Tipo_Residuo t
             ON c.id_tipo_residuo = t.id_tipo_residuo
             LEFT JOIN Ruta r
             ON c.id_ruta = r.id_ruta
             LEFT JOIN Centro_Acopio ca
             ON c.id_centro_acopio = ca.id_centro_acopio"
        );

        $contenedores = [];

        while ($fila = $resultado->fetch_assoc()) {
            $contenedores[] = $fila;
        }

        return $contenedores;
    }

    private function datosContenedor($datos)
    {
        $latitud = (isset($datos["latitud"]) && $datos["latitud"] !== "") ? (float)$datos["latitud"] : null;
        $longitud = (isset($datos["longitud"]) && $datos["longitud"] !== "") ? (float)$datos["longitud"] : null;
        $zona = (isset($datos["zona"]) && trim($datos["zona"]) !== "") ? trim($datos["zona"]) : null;

        if ($latitud !== null && ($latitud < -90 || $latitud > 90)) {
            return ["error" => "La latitud debe estar entre -90 y 90"];
        }

        if ($longitud !== null && ($longitud < -180 || $longitud > 180)) {
            return ["error" => "La longitud debe estar entre -180 y 180"];
        }

        if (($latitud === null) !== ($longitud === null)) {
            return ["error" => "Indicá latitud y longitud juntas (o ninguna)"];
        }

        return [
            "codigo" => $datos["codigo"],
            "ubicacion" => $datos["ubicacion"],
            "estado" => $datos["estado"],
            "capacidad" => (float)$datos["capacidad"],
            "idTipo" => (int)$datos["id_tipo_residuo"],
            "idRuta" => !empty($datos["id_ruta"]) ? (int)$datos["id_ruta"] : null,
            "idCentro" => !empty($datos["id_centro_acopio"]) ? (int)$datos["id_centro_acopio"] : null,
            "latitud" => $latitud,
            "longitud" => $longitud,
            "zona" => $zona
        ];
    }

    public function crearContenedor($datos)
    {
        global $conexion;

        $d = $this->datosContenedor($datos);

        if (isset($d["error"])) {
            return ["ok" => false, "mensaje" => $d["error"]];
        }

        $stmt = $conexion->prepare(
            "INSERT INTO Contenedor
             (codigo, ubicacion, estado, capacidad, id_tipo_residuo, id_ruta, id_centro_acopio, latitud, longitud, zona)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            error_log("crearContenedor prepare: " . $conexion->error);
            return ["ok" => false, "mensaje" => "Error al preparar el registro"];
        }

        $stmt->bind_param(
            "sssdiiidds",
            $d["codigo"], $d["ubicacion"], $d["estado"], $d["capacidad"],
            $d["idTipo"], $d["idRuta"], $d["idCentro"],
            $d["latitud"], $d["longitud"], $d["zona"]
        );

        if ($stmt->execute()) {
            return ["ok" => true, "mensaje" => "Contenedor registrado correctamente"];
        }

        if ($stmt->errno === 1062) {
            return ["ok" => false, "mensaje" => "Ya existe un contenedor con ese código"];
        }

        error_log("crearContenedor execute: " . $stmt->error);
        return ["ok" => false, "mensaje" => "No se pudo registrar el contenedor"];
    }

    public function actualizarContenedor($datos)
    {
        global $conexion;

        $id = (int)$datos["id_contenedor"];
        $d = $this->datosContenedor($datos);

        if (isset($d["error"])) {
            return ["ok" => false, "mensaje" => $d["error"]];
        }

        $stmt = $conexion->prepare(
            "UPDATE Contenedor
             SET codigo = ?, ubicacion = ?, estado = ?, capacidad = ?,
                 id_tipo_residuo = ?, id_ruta = ?, id_centro_acopio = ?,
                 latitud = ?, longitud = ?, zona = ?
             WHERE id_contenedor = ?"
        );

        if (!$stmt) {
            error_log("actualizarContenedor prepare: " . $conexion->error);
            return ["ok" => false, "mensaje" => "Error al preparar la modificación"];
        }

        $stmt->bind_param(
            "sssdiiiddsi",
            $d["codigo"], $d["ubicacion"], $d["estado"], $d["capacidad"],
            $d["idTipo"], $d["idRuta"], $d["idCentro"],
            $d["latitud"], $d["longitud"], $d["zona"], $id
        );

        if ($stmt->execute()) {
            return ["ok" => true, "mensaje" => "Contenedor actualizado correctamente"];
        }

        if ($stmt->errno === 1062) {
            return ["ok" => false, "mensaje" => "Ya existe un contenedor con ese código"];
        }

        error_log("actualizarContenedor execute: " . $stmt->error);
        return ["ok" => false, "mensaje" => "No se pudo actualizar el contenedor"];
    }

    public function eliminarContenedor($id)
    {
        global $conexion;

        $stmt = $conexion->prepare(
            "DELETE FROM Contenedor WHERE id_contenedor = ?"
        );

        if (!$stmt) {
            return [
                "ok" => false,
                "mensaje" => "Error al preparar la eliminación",
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
        ];
    }

    public function listarCentros()
    {
        global $conexion;

        $resultado = $conexion->query(
            "SELECT * FROM Centro_Acopio"
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

        $sql = "INSERT INTO Centro_Acopio
                (nombre, direccion, capacidad)
                VALUES (?, ?, ?)";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            return [
                "ok" => false,
                "mensaje" => "Error al preparar el registro",
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
        ];
    }

    public function actualizarCentro($datos)
    {
        global $conexion;

        $id = $datos["id_centro_acopio"];
        $nombre = $datos["nombre"];
        $direccion = $datos["direccion"];
        $capacidad = $datos["capacidad"];

        $sql = "UPDATE Centro_Acopio
                SET nombre = ?,
                    direccion = ?,
                    capacidad = ?
                WHERE id_centro_acopio = ?";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            return [
                "ok" => false,
                "mensaje" => "Error al preparar la modificación",
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
        ];
    }

    public function eliminarCentro($id)
    {
        global $conexion;

        $stmt = $conexion->prepare(
            "DELETE FROM Centro_Acopio WHERE id_centro_acopio = ?"
        );

        if (!$stmt) {
            return [
                "ok" => false,
                "mensaje" => "No se pudo eliminar el centro.",
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
        ];
    }
}
