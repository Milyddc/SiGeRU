<?php

require_once __DIR__ . "/../database.php";

class CamionController
{
    const ESTADOS = ["Disponible", "En ruta", "En mantenimiento", "Fuera de Servicio"];
    const TIPOS_MANTENIMIENTO = ["Preventivo", "Correctivo"];

    private function filas($resultado): array
    {
        $filas = [];

        while ($fila = $resultado->fetch_assoc()) {
            $filas[] = $fila;
        }

        return $filas;
    }

    public function listar(): array
    {
        global $conexion;

        return $this->filas($conexion->query(
            "SELECT ca.*, cu.nombre AS cuadrilla
             FROM Camion ca
             LEFT JOIN Cuadrilla cu ON cu.id_cuadrilla = ca.id_cuadrilla
             ORDER BY ca.id_camion"
        ));
    }

    private function validar(array $datos): ?string
    {
        if (!in_array($datos["estado"], self::ESTADOS, true)) {
            return "Estado de camión no válido";
        }

        if (!is_numeric($datos["capacidad"]) || (float)$datos["capacidad"] <= 0) {
            return "La capacidad debe ser un número mayor que 0";
        }

        if (mb_strlen(trim($datos["matricula"])) > 20) {
            return "La matrícula es demasiado larga";
        }

        return null;
    }

    public function crear(array $datos): array
    {
        global $conexion;

        $error = $this->validar($datos);
        if ($error) {
            return ["ok" => false, "mensaje" => $error];
        }

        $matricula = trim($datos["matricula"]);
        $modelo = isset($datos["modelo"]) ? trim($datos["modelo"]) : null;
        $capacidad = (float)$datos["capacidad"];
        $estado = $datos["estado"];
        $idCuadrilla = (int)$datos["id_cuadrilla"];

        $stmt = $conexion->prepare(
            "INSERT INTO Camion (matricula, modelo, capacidad, estado, id_cuadrilla) VALUES (?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            error_log("crear camion prepare: " . $conexion->error);
            return ["ok" => false, "mensaje" => "Error al preparar el registro"];
        }

        $stmt->bind_param("ssdsi", $matricula, $modelo, $capacidad, $estado, $idCuadrilla);

        if ($stmt->execute()) {
            return ["ok" => true, "mensaje" => "Camión registrado correctamente"];
        }

        if ($stmt->errno === 1062) {
            return ["ok" => false, "mensaje" => "Ya existe un camión con esa matrícula"];
        }

        if ($stmt->errno === 1452) {
            return ["ok" => false, "mensaje" => "La cuadrilla indicada no existe"];
        }

        error_log("crear camion execute: " . $stmt->error);
        return ["ok" => false, "mensaje" => "No se pudo registrar el camión"];
    }

    public function actualizar(array $datos): array
    {
        global $conexion;

        $error = $this->validar($datos);
        if ($error) {
            return ["ok" => false, "mensaje" => $error];
        }

        $id = (int)$datos["id_camion"];
        $matricula = trim($datos["matricula"]);
        $modelo = isset($datos["modelo"]) ? trim($datos["modelo"]) : null;
        $capacidad = (float)$datos["capacidad"];
        $estado = $datos["estado"];
        $idCuadrilla = (int)$datos["id_cuadrilla"];

        $stmt = $conexion->prepare(
            "UPDATE Camion SET matricula = ?, modelo = ?, capacidad = ?, estado = ?, id_cuadrilla = ?
             WHERE id_camion = ?"
        );

        if (!$stmt) {
            error_log("actualizar camion prepare: " . $conexion->error);
            return ["ok" => false, "mensaje" => "Error al preparar la modificación"];
        }

        $stmt->bind_param("ssdsii", $matricula, $modelo, $capacidad, $estado, $idCuadrilla, $id);

        if ($stmt->execute()) {
            return ["ok" => true, "mensaje" => "Camión actualizado correctamente"];
        }

        if ($stmt->errno === 1062) {
            return ["ok" => false, "mensaje" => "Ya existe un camión con esa matrícula"];
        }

        if ($stmt->errno === 1452) {
            return ["ok" => false, "mensaje" => "La cuadrilla indicada no existe"];
        }

        error_log("actualizar camion execute: " . $stmt->error);
        return ["ok" => false, "mensaje" => "No se pudo actualizar el camión"];
    }

    public function eliminar(int $id): array
    {
        global $conexion;

        $stmt = $conexion->prepare("DELETE FROM Camion WHERE id_camion = ?");

        if (!$stmt) {
            error_log("eliminar camion prepare: " . $conexion->error);
            return ["ok" => false, "mensaje" => "Error al preparar la eliminación"];
        }

        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            return $stmt->affected_rows > 0
                ? ["ok" => true, "mensaje" => "Camión eliminado correctamente"]
                : ["ok" => false, "mensaje" => "No se encontró el camión."];
        }

        if ($stmt->errno === 1451) {
            return [
                "ok" => false,
                "mensaje" => "No se puede eliminar el camión porque tiene recorridos registrados."
            ];
        }

        error_log("eliminar camion execute: " . $stmt->error);
        return ["ok" => false, "mensaje" => "No se pudo eliminar el camión"];
    }

    // ---------------- Mantenimiento ----------------

    public function listarMantenimientos(?int $idCamion): array
    {
        global $conexion;

        $sql = "SELECT m.id_mantenimiento, m.id_camion, ca.matricula, m.tipo, m.descripcion,
                       m.fecha_programada, m.fecha_realizada, m.estado
                FROM Mantenimiento_Camion m
                JOIN Camion ca ON ca.id_camion = m.id_camion";

        if ($idCamion) {
            $stmt = $conexion->prepare($sql . " WHERE m.id_camion = ? ORDER BY m.fecha_programada DESC");
            $stmt->bind_param("i", $idCamion);
            $stmt->execute();
            return $this->filas($stmt->get_result());
        }

        return $this->filas($conexion->query($sql . " ORDER BY m.estado, m.fecha_programada DESC"));
    }

    public function programarMantenimiento(array $datos): array
    {
        global $conexion;

        $idCamion = (int)$datos["id_camion"];
        $tipo = $datos["tipo"] ?? "Preventivo";
        $descripcion = trim($datos["descripcion"]);
        $fecha = $datos["fecha_programada"];

        if (!in_array($tipo, self::TIPOS_MANTENIMIENTO, true)) {
            return ["ok" => false, "mensaje" => "Tipo de mantenimiento no válido"];
        }

        if ($descripcion === "" || mb_strlen($descripcion) > 255) {
            return ["ok" => false, "mensaje" => "La descripción es obligatoria (máximo 255 caracteres)"];
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return ["ok" => false, "mensaje" => "La fecha no es válida"];
        }

        $conexion->begin_transaction();

        $stmt = $conexion->prepare(
            "INSERT INTO Mantenimiento_Camion (id_camion, tipo, descripcion, fecha_programada)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("isss", $idCamion, $tipo, $descripcion, $fecha);

        if (!$stmt->execute()) {
            $conexion->rollback();

            if ($stmt->errno === 1452) {
                return ["ok" => false, "mensaje" => "El camión indicado no existe"];
            }

            error_log("programar mantenimiento: " . $stmt->error);
            return ["ok" => false, "mensaje" => "No se pudo programar el mantenimiento"];
        }

        // Si el mantenimiento es para hoy o ya venció, el camión queda fuera de servicio operativo
        $upd = $conexion->prepare(
            "UPDATE Camion SET estado = 'En mantenimiento'
             WHERE id_camion = ? AND estado = 'Disponible' AND ? <= CURDATE()"
        );
        $upd->bind_param("is", $idCamion, $fecha);
        $upd->execute();

        $conexion->commit();

        return ["ok" => true, "mensaje" => "Mantenimiento programado correctamente"];
    }

    public function completarMantenimiento(int $idMantenimiento): array
    {
        global $conexion;

        $chk = $conexion->prepare(
            "SELECT id_camion, estado FROM Mantenimiento_Camion WHERE id_mantenimiento = ?"
        );
        $chk->bind_param("i", $idMantenimiento);
        $chk->execute();
        $m = $chk->get_result()->fetch_assoc();

        if (!$m) {
            return ["ok" => false, "mensaje" => "Mantenimiento no encontrado"];
        }

        if ($m["estado"] === "Realizado") {
            return ["ok" => false, "mensaje" => "Ese mantenimiento ya estaba realizado"];
        }

        $idCamion = (int)$m["id_camion"];

        $conexion->begin_transaction();

        $stmt = $conexion->prepare(
            "UPDATE Mantenimiento_Camion SET estado = 'Realizado', fecha_realizada = CURDATE()
             WHERE id_mantenimiento = ?"
        );
        $stmt->bind_param("i", $idMantenimiento);

        if (!$stmt->execute()) {
            $conexion->rollback();
            error_log("completar mantenimiento: " . $stmt->error);
            return ["ok" => false, "mensaje" => "No se pudo completar el mantenimiento"];
        }

        // Si no quedan mantenimientos vencidos pendientes, el camión vuelve a estar disponible
        $pend = $conexion->prepare(
            "SELECT COUNT(*) AS n FROM Mantenimiento_Camion
             WHERE id_camion = ? AND estado = 'Programado' AND fecha_programada <= CURDATE()"
        );
        $pend->bind_param("i", $idCamion);
        $pend->execute();

        if ((int)$pend->get_result()->fetch_assoc()["n"] === 0) {
            $upd = $conexion->prepare(
                "UPDATE Camion SET estado = 'Disponible' WHERE id_camion = ? AND estado = 'En mantenimiento'"
            );
            $upd->bind_param("i", $idCamion);
            $upd->execute();
        }

        $conexion->commit();

        return ["ok" => true, "mensaje" => "Mantenimiento registrado como realizado"];
    }
}
