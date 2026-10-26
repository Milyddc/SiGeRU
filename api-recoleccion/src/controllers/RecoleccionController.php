<?php

require_once __DIR__ . "/../database.php";
require_once __DIR__ . "/../config/request.php";

class RecoleccionController
{
    public function listarRutas()
    {
        global $conexion;

        $resultado = $conexion->query(
            "SELECT r.id_ruta, r.nombre, r.zona, r.frecuencia, r.id_cuadrilla,
                    cu.nombre AS cuadrilla,
                    COUNT(c.id_contenedor) AS cantidad_contenedores,
                    GROUP_CONCAT(c.codigo ORDER BY c.codigo SEPARATOR ', ') AS contenedores
             FROM Ruta r
             LEFT JOIN Cuadrilla cu ON cu.id_cuadrilla = r.id_cuadrilla
             LEFT JOIN Contenedor c ON c.id_ruta = r.id_ruta
             GROUP BY r.id_ruta, r.nombre, r.zona, r.frecuencia, r.id_cuadrilla, cu.nombre
             ORDER BY r.id_ruta"
        );

        $rutas = [];

        while ($fila = $resultado->fetch_assoc()) {
            $rutas[] = $fila;
        }

        return $rutas;
    }

    public function listarCuadrillas()
    {
        global $conexion;

        $resultado = $conexion->query(
            "SELECT cu.id_cuadrilla, cu.nombre, cu.turno,
                    (SELECT COUNT(*) FROM Camion ca WHERE ca.id_cuadrilla = cu.id_cuadrilla) AS camiones,
                    (SELECT COUNT(*) FROM Cuadrilla_Usuario cuu WHERE cuu.id_cuadrilla = cu.id_cuadrilla) AS integrantes
             FROM Cuadrilla cu
             ORDER BY cu.id_cuadrilla"
        );

        $cuadrillas = [];

        while ($fila = $resultado->fetch_assoc()) {
            $cuadrillas[] = $fila;
        }

        return $cuadrillas;
    }

    public function crearRuta($datos)
    {
        global $conexion;

        $nombre = trim($datos["nombre"]);
        $zona = trim($datos["zona"]);
        $frecuencia = isset($datos["frecuencia"]) ? trim($datos["frecuencia"]) : null;
        $idCuadrilla = enteroONulo($datos["id_cuadrilla"] ?? null);

        $stmt = $conexion->prepare(
            "INSERT INTO Ruta (nombre, zona, frecuencia, id_cuadrilla) VALUES (?, ?, ?, ?)"
        );

        if (!$stmt) {
            error_log("crearRuta prepare: " . $conexion->error);
            return ["ok" => false, "mensaje" => "No se pudo registrar la ruta"];
        }

        $stmt->bind_param("sssi", $nombre, $zona, $frecuencia, $idCuadrilla);

        if ($stmt->execute()) {
            return ["ok" => true, "mensaje" => "Ruta registrada correctamente"];
        }

        if ($stmt->errno === 1452) {
            return ["ok" => false, "mensaje" => "La cuadrilla indicada no existe"];
        }

        error_log("crearRuta execute: " . $stmt->error);
        return ["ok" => false, "mensaje" => "No se pudo registrar la ruta"];
    }

    public function actualizarRuta($datos)
    {
        global $conexion;

        $id = (int)$datos["id_ruta"];
        $nombre = trim($datos["nombre"]);
        $zona = trim($datos["zona"]);
        $frecuencia = isset($datos["frecuencia"]) ? trim($datos["frecuencia"]) : null;
        $idCuadrilla = enteroONulo($datos["id_cuadrilla"] ?? null);

        $stmt = $conexion->prepare(
            "UPDATE Ruta SET nombre = ?, zona = ?, frecuencia = ?, id_cuadrilla = ? WHERE id_ruta = ?"
        );

        if (!$stmt) {
            error_log("actualizarRuta prepare: " . $conexion->error);
            return ["ok" => false, "mensaje" => "No se pudo actualizar la ruta"];
        }

        $stmt->bind_param("sssii", $nombre, $zona, $frecuencia, $idCuadrilla, $id);

        if ($stmt->execute()) {
            return ["ok" => true, "mensaje" => "Ruta actualizada correctamente"];
        }

        if ($stmt->errno === 1452) {
            return ["ok" => false, "mensaje" => "La cuadrilla indicada no existe"];
        }

        error_log("actualizarRuta execute: " . $stmt->error);
        return ["ok" => false, "mensaje" => "No se pudo actualizar la ruta"];
    }

    // Asigna (o quita, con null) la cuadrilla responsable de una ruta
    public function asignarCuadrilla($idRuta, $idCuadrilla)
    {
        global $conexion;

        $idRuta = (int)$idRuta;
        $idCuadrilla = enteroONulo($idCuadrilla);

        $stmt = $conexion->prepare("UPDATE Ruta SET id_cuadrilla = ? WHERE id_ruta = ?");

        if (!$stmt) {
            error_log("asignarCuadrilla prepare: " . $conexion->error);
            return ["ok" => false, "mensaje" => "No se pudo asignar la cuadrilla"];
        }

        $stmt->bind_param("ii", $idCuadrilla, $idRuta);

        if ($stmt->execute()) {
            return ["ok" => true, "mensaje" => "Cuadrilla asignada correctamente"];
        }

        if ($stmt->errno === 1452) {
            return ["ok" => false, "mensaje" => "La cuadrilla indicada no existe"];
        }

        error_log("asignarCuadrilla execute: " . $stmt->error);
        return ["ok" => false, "mensaje" => "No se pudo asignar la cuadrilla"];
    }

    public function eliminarRuta($id)
    {
        global $conexion;

        $id = (int)$id;

        $conexion->begin_transaction();

        // Los contenedores de la ruta quedan sin ruta (no se borran)
        $liberar = $conexion->prepare("UPDATE Contenedor SET id_ruta = NULL WHERE id_ruta = ?");
        $liberar->bind_param("i", $id);

        if (!$liberar->execute()) {
            $conexion->rollback();
            error_log("eliminarRuta liberar: " . $liberar->error);
            return ["ok" => false, "mensaje" => "No se pudo eliminar la ruta"];
        }

        $stmt = $conexion->prepare("DELETE FROM Ruta WHERE id_ruta = ?");
        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            $errno = $stmt->errno;
            $conexion->rollback();

            if ($errno === 1451) {
                return [
                    "ok" => false,
                    "mensaje" => "No se puede eliminar la ruta porque tiene recorridos registrados."
                ];
            }

            error_log("eliminarRuta delete: " . $stmt->error);
            return ["ok" => false, "mensaje" => "No se pudo eliminar la ruta"];
        }

        if ($stmt->affected_rows === 0) {
            $conexion->rollback();
            return ["ok" => false, "mensaje" => "No se encontró la ruta."];
        }

        $conexion->commit();

        return ["ok" => true, "mensaje" => "Ruta eliminada correctamente"];
    }
}
