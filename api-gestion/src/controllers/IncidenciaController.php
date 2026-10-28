<?php

require_once __DIR__ . "/../database.php";
require_once __DIR__ . "/NotificacionController.php";

class IncidenciaController
{
    const ESTADOS = ["Abierta", "En curso", "Resuelta"];
    const TIPOS = ["Rotura", "Desborde", "Falta de recolección", "Otro"];
    const PRIORIDADES = ["Baja", "Media", "Alta"];

    private const SELECT_BASE =
        "SELECT i.id_incidencia, i.fecha, i.tipo, i.descripcion, i.estado, i.prioridad,
                i.fecha_resolucion, i.id_usuario, i.id_contenedor, i.id_cuadrilla,
                c.codigo AS contenedor, c.ubicacion, c.zona,
                cu.nombre AS cuadrilla, u.nombre_completo AS reportante
         FROM Incidencia i
         JOIN Contenedor c ON c.id_contenedor = i.id_contenedor
         JOIN Usuario u ON u.id_usuario = i.id_usuario
         LEFT JOIN Cuadrilla cu ON cu.id_cuadrilla = i.id_cuadrilla";

    private function alcance(array $usuario): array
    {
        $id = (int)$usuario["sub"];

        if ($usuario["rol"] === "Vecino") {
            return ["i.id_usuario = ?", "i", [$id]];
        }

        if ($usuario["rol"] === "Cuadrilla") {
            return [
                "i.id_cuadrilla IN (SELECT id_cuadrilla FROM Cuadrilla_Usuario WHERE id_usuario = ?)",
                "i", [$id]
            ];
        }

        return ["", "", []];
    }

    private function obtener(array $usuario, int $idIncidencia): ?array
    {
        global $conexion;

        $sql = self::SELECT_BASE . " WHERE i.id_incidencia = ?";
        $tipos = "i";
        $vals = [$idIncidencia];

        [$cond, $t, $v] = $this->alcance($usuario);

        if ($cond !== "") {
            $sql .= " AND " . $cond;
            $tipos .= $t;
            $vals = array_merge($vals, $v);
        }

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param($tipos, ...$vals);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    private function registrarHistorial(int $idIncidencia, int $idUsuario, ?string $anterior, string $nuevo, ?string $comentario): void
    {
        global $conexion;

        $stmt = $conexion->prepare(
            "INSERT INTO Historial_Incidencia (id_incidencia, id_usuario, estado_anterior, estado_nuevo, comentario)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iisss", $idIncidencia, $idUsuario, $anterior, $nuevo, $comentario);
        $stmt->execute();
    }

    public function listar(array $usuario, array $f): array
    {
        global $conexion;

        $where = [];
        $tipos = "";
        $vals = [];

        $estado = $f["estado"] ?? "";

        if ($estado === "abiertas") {
            $where[] = "i.estado <> 'Resuelta'";
        } elseif ($estado === "cerradas") {
            $where[] = "i.estado = 'Resuelta'";
        } elseif (in_array($estado, self::ESTADOS, true)) {
            $where[] = "i.estado = ?";
            $tipos .= "s";
            $vals[] = $estado;
        }

        if (!empty($f["zona"])) {
            $where[] = "c.zona = ?";
            $tipos .= "s";
            $vals[] = $f["zona"];
        }

        if (!empty($f["id_contenedor"])) {
            $where[] = "i.id_contenedor = ?";
            $tipos .= "i";
            $vals[] = (int)$f["id_contenedor"];
        }

        if (!empty($f["desde"]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f["desde"])) {
            $where[] = "i.fecha >= ?";
            $tipos .= "s";
            $vals[] = $f["desde"] . " 00:00:00";
        }

        if (!empty($f["hasta"]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f["hasta"])) {
            $where[] = "i.fecha <= ?";
            $tipos .= "s";
            $vals[] = $f["hasta"] . " 23:59:59";
        }

        [$cond, $t, $v] = $this->alcance($usuario);

        if ($cond !== "") {
            $where[] = $cond;
            $tipos .= $t;
            $vals = array_merge($vals, $v);
        }

        $sql = self::SELECT_BASE;

        if ($where) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY i.fecha DESC, i.id_incidencia DESC LIMIT 500";

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            error_log("listar incidencias prepare: " . $conexion->error);
            return [];
        }

        if ($vals) {
            $stmt->bind_param($tipos, ...$vals);
        }

        $stmt->execute();

        $filas = [];
        $res = $stmt->get_result();

        while ($fila = $res->fetch_assoc()) {
            $filas[] = $fila;
        }

        return $filas;
    }

    public function crear(array $usuario, array $datos): array
    {
        global $conexion;

        $idContenedor = (int)$datos["id_contenedor"];
        $tipo = $datos["tipo"] ?? "Otro";
        $descripcion = trim($datos["descripcion"] ?? "");
        $idUsuario = (int)$usuario["sub"];

        $prioridad = $usuario["rol"] === "Vecino" ? "Media" : ($datos["prioridad"] ?? "Media");

        if (!in_array($tipo, self::TIPOS, true)) {
            return ["ok" => false, "mensaje" => "Tipo de incidencia no válido"];
        }

        if (!in_array($prioridad, self::PRIORIDADES, true)) {
            return ["ok" => false, "mensaje" => "Prioridad no válida"];
        }

        if ($descripcion === "" || mb_strlen($descripcion) > 1000) {
            return ["ok" => false, "mensaje" => "La descripción es obligatoria (máximo 1000 caracteres)"];
        }

        $chk = $conexion->prepare("SELECT codigo FROM Contenedor WHERE id_contenedor = ?");
        $chk->bind_param("i", $idContenedor);
        $chk->execute();
        $contenedor = $chk->get_result()->fetch_assoc();

        if (!$contenedor) {
            return ["ok" => false, "mensaje" => "El contenedor indicado no existe"];
        }

        $conexion->begin_transaction();

        $stmt = $conexion->prepare(
            "INSERT INTO Incidencia (tipo, descripcion, estado, prioridad, id_usuario, id_contenedor)
             VALUES (?, ?, 'Abierta', ?, ?, ?)"
        );
        $stmt->bind_param("sssii", $tipo, $descripcion, $prioridad, $idUsuario, $idContenedor);

        if (!$stmt->execute()) {
            $conexion->rollback();
            error_log("crear incidencia: " . $stmt->error);
            return ["ok" => false, "mensaje" => "No se pudo registrar la incidencia"];
        }

        $idIncidencia = $conexion->insert_id;

        $this->registrarHistorial($idIncidencia, $idUsuario, null, "Abierta", "Incidencia creada");

        if ($tipo === "Desborde") {
            $upd = $conexion->prepare(
                "UPDATE Contenedor SET estado = 'Lleno' WHERE id_contenedor = ? AND estado = 'Disponible'"
            );
            $upd->bind_param("i", $idContenedor);
            $upd->execute();
        }

        $conexion->commit();

        $esAlerta = ($tipo === "Desborde" || $prioridad === "Alta");
        $mensaje = "Nueva incidencia #" . $idIncidencia . " (" . $tipo . ") en " . $contenedor["codigo"]
            . " - prioridad " . $prioridad;

        $destinatarios = array_merge(
            NotificacionController::idsPorRol("Administrador"),
            NotificacionController::idsPorRol("Operario")
        );

        $dup = $conexion->prepare(
            "SELECT COUNT(*) AS n FROM Incidencia
             WHERE id_contenedor = ? AND tipo = ? AND estado <> 'Resuelta' AND id_incidencia <> ?"
        );
        $dup->bind_param("isi", $idContenedor, $tipo, $idIncidencia);
        $dup->execute();
        $repetida = (int)$dup->get_result()->fetch_assoc()["n"] > 0;

        NotificacionController::crear(
            $destinatarios, $mensaje, $esAlerta ? "alerta" : "info", $idUsuario, $esAlerta && !$repetida
        );

        return ["ok" => true, "mensaje" => "Incidencia registrada correctamente", "id_incidencia" => $idIncidencia];
    }

    public function cambiarEstado(array $usuario, int $idIncidencia, string $estado, ?string $comentario): array
    {
        global $conexion;

        if (!in_array($estado, self::ESTADOS, true)) {
            return ["ok" => false, "mensaje" => "Estado no válido"];
        }

        $inc = $this->obtener($usuario, $idIncidencia);

        if (!$inc) {
            return ["ok" => false, "mensaje" => "Incidencia no encontrada o sin permiso"];
        }

        if ($inc["estado"] === $estado) {
            return ["ok" => false, "mensaje" => "La incidencia ya está en ese estado"];
        }

        $idUsuario = (int)$usuario["sub"];
        $comentario = $comentario !== null ? mb_substr(trim($comentario), 0, 250) : null;

        $conexion->begin_transaction();

        if ($estado === "Resuelta") {
            $stmt = $conexion->prepare(
                "UPDATE Incidencia SET estado = ?, fecha_resolucion = NOW() WHERE id_incidencia = ?"
            );
        } else {
            $stmt = $conexion->prepare(
                "UPDATE Incidencia SET estado = ?, fecha_resolucion = NULL WHERE id_incidencia = ?"
            );
        }

        $stmt->bind_param("si", $estado, $idIncidencia);

        if (!$stmt->execute()) {
            $conexion->rollback();
            error_log("cambiarEstado: " . $stmt->error);
            return ["ok" => false, "mensaje" => "No se pudo cambiar el estado"];
        }

        $this->registrarHistorial($idIncidencia, $idUsuario, $inc["estado"], $estado, $comentario);

        if ($estado === "Resuelta" && $inc["tipo"] === "Desborde") {
            $otras = $conexion->prepare(
                "SELECT COUNT(*) AS n FROM Incidencia
                 WHERE id_contenedor = ? AND tipo = 'Desborde' AND estado <> 'Resuelta'"
            );
            $otras->bind_param("i", $inc["id_contenedor"]);
            $otras->execute();

            if ((int)$otras->get_result()->fetch_assoc()["n"] === 0) {
                $upd = $conexion->prepare(
                    "UPDATE Contenedor SET estado = 'Disponible' WHERE id_contenedor = ? AND estado = 'Lleno'"
                );
                $upd->bind_param("i", $inc["id_contenedor"]);
                $upd->execute();
            }
        }

        $conexion->commit();

        NotificacionController::crear(
            [(int)$inc["id_usuario"]],
            "Tu incidencia #" . $idIncidencia . " en " . $inc["contenedor"] . " pasó a: " . $estado,
            "info",
            $idUsuario,
            $estado === "Resuelta"
        );

        return ["ok" => true, "mensaje" => "Estado actualizado a " . $estado];
    }

    public function asignarCuadrilla(array $usuario, int $idIncidencia, int $idCuadrilla): array
    {
        global $conexion;

        $inc = $this->obtener($usuario, $idIncidencia);

        if (!$inc) {
            return ["ok" => false, "mensaje" => "Incidencia no encontrada"];
        }

        $chk = $conexion->prepare("SELECT nombre FROM Cuadrilla WHERE id_cuadrilla = ?");
        $chk->bind_param("i", $idCuadrilla);
        $chk->execute();
        $cuadrilla = $chk->get_result()->fetch_assoc();

        if (!$cuadrilla) {
            return ["ok" => false, "mensaje" => "La cuadrilla indicada no existe"];
        }

        $nuevoEstado = $inc["estado"] === "Abierta" ? "En curso" : $inc["estado"];

        $conexion->begin_transaction();

        $stmt = $conexion->prepare(
            "UPDATE Incidencia SET id_cuadrilla = ?, estado = ? WHERE id_incidencia = ?"
        );
        $stmt->bind_param("isi", $idCuadrilla, $nuevoEstado, $idIncidencia);

        if (!$stmt->execute()) {
            $conexion->rollback();
            error_log("asignarCuadrilla incidencia: " . $stmt->error);
            return ["ok" => false, "mensaje" => "No se pudo asignar la cuadrilla"];
        }

        $this->registrarHistorial(
            $idIncidencia, (int)$usuario["sub"], $inc["estado"], $nuevoEstado,
            "Asignada a " . $cuadrilla["nombre"]
        );

        $conexion->commit();

        NotificacionController::crear(
            NotificacionController::idsPorCuadrilla($idCuadrilla),
            "Nueva incidencia asignada a tu cuadrilla: #" . $idIncidencia . " en " . $inc["contenedor"]
                . " (" . $inc["tipo"] . ")",
            $inc["prioridad"] === "Alta" ? "alerta" : "info",
            (int)$usuario["sub"],
            true
        );

        return ["ok" => true, "mensaje" => "Cuadrilla asignada correctamente"];
    }

    public function historial(array $usuario, int $idIncidencia): array
    {
        global $conexion;

        if (!$this->obtener($usuario, $idIncidencia)) {
            return ["ok" => false, "mensaje" => "Incidencia no encontrada o sin permiso"];
        }

        $stmt = $conexion->prepare(
            "SELECT h.fecha, h.estado_anterior, h.estado_nuevo, h.comentario, u.nombre_completo AS usuario
             FROM Historial_Incidencia h
             JOIN Usuario u ON u.id_usuario = h.id_usuario
             WHERE h.id_incidencia = ?
             ORDER BY h.fecha, h.id_historial"
        );
        $stmt->bind_param("i", $idIncidencia);
        $stmt->execute();

        $filas = [];
        $res = $stmt->get_result();

        while ($fila = $res->fetch_assoc()) {
            $filas[] = $fila;
        }

        return ["ok" => true, "historial" => $filas];
    }
}
