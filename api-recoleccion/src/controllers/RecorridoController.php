<?php

require_once __DIR__ . "/../database.php";

// Recorridos de recolección: un camión sale a hacer una ruta, se registran los
// recursos que se usan (combustible, bolsas, etc.) y al final el volumen recolectado.
class RecorridoController
{
    private function cuadrillasDe(int $idUsuario): array
    {
        global $conexion;

        $stmt = $conexion->prepare("SELECT id_cuadrilla FROM Cuadrilla_Usuario WHERE id_usuario = ?");
        $stmt->bind_param("i", $idUsuario);
        $stmt->execute();

        $ids = [];
        $res = $stmt->get_result();

        while ($fila = $res->fetch_assoc()) {
            $ids[] = (int)$fila["id_cuadrilla"];
        }

        return $ids;
    }

    // Una cuadrilla solo opera sobre rutas de su cuadrilla; administración y operarios, sobre todas
    private function puedeOperarRuta(array $usuario, ?int $idCuadrillaRuta): bool
    {
        if ($usuario["rol"] !== "Cuadrilla") {
            return true;
        }

        return $idCuadrillaRuta !== null
            && in_array($idCuadrillaRuta, $this->cuadrillasDe((int)$usuario["sub"]), true);
    }

    private function obtenerRecorrido(int $id): ?array
    {
        global $conexion;

        $stmt = $conexion->prepare(
            "SELECT r.id_recorrido, r.id_camion, r.estado, ru.id_cuadrilla
             FROM Recorrido r
             JOIN Ruta ru ON ru.id_ruta = r.id_ruta
             WHERE r.id_recorrido = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public function listar(array $usuario): array
    {
        global $conexion;

        $sql = "SELECT r.id_recorrido, r.id_ruta, ru.nombre AS ruta, ru.id_cuadrilla,
                       r.id_camion, ca.matricula AS camion, u.nombre_completo AS usuario,
                       r.fecha_inicio, r.fecha_fin, r.estado, r.volumen_kg, r.observaciones
                FROM Recorrido r
                JOIN Ruta ru ON ru.id_ruta = r.id_ruta
                JOIN Camion ca ON ca.id_camion = r.id_camion
                JOIN Usuario u ON u.id_usuario = r.id_usuario";

        if ($usuario["rol"] === "Cuadrilla") {
            $stmt = $conexion->prepare(
                $sql . " WHERE ru.id_cuadrilla IN
                         (SELECT id_cuadrilla FROM Cuadrilla_Usuario WHERE id_usuario = ?)
                         ORDER BY r.fecha_inicio DESC LIMIT 200"
            );
            $idUsuario = (int)$usuario["sub"];
            $stmt->bind_param("i", $idUsuario);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $conexion->query($sql . " ORDER BY r.fecha_inicio DESC LIMIT 200");
        }

        $recorridos = [];
        $porId = [];

        while ($fila = $res->fetch_assoc()) {
            $fila["recursos"] = [];
            $recorridos[] = $fila;
        }

        if (!$recorridos) {
            return [];
        }

        foreach ($recorridos as $i => $r) {
            $porId[(int)$r["id_recorrido"]] = $i;
        }

        $ids = array_keys($porId);
        $marcas = implode(",", array_fill(0, count($ids), "?"));

        $stmt = $conexion->prepare(
            "SELECT id_recurso, id_recorrido, recurso, cantidad, unidad
             FROM Recorrido_Recurso WHERE id_recorrido IN ($marcas) ORDER BY id_recurso"
        );
        $stmt->bind_param(str_repeat("i", count($ids)), ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($rec = $res->fetch_assoc()) {
            $recorridos[$porId[(int)$rec["id_recorrido"]]]["recursos"][] = $rec;
        }

        return $recorridos;
    }

    public function iniciar(array $usuario, int $idRuta, int $idCamion): array
    {
        global $conexion;

        $stmt = $conexion->prepare("SELECT id_cuadrilla FROM Ruta WHERE id_ruta = ?");
        $stmt->bind_param("i", $idRuta);
        $stmt->execute();
        $ruta = $stmt->get_result()->fetch_assoc();

        if (!$ruta) {
            return ["ok" => false, "mensaje" => "La ruta indicada no existe"];
        }

        $idCuadrillaRuta = $ruta["id_cuadrilla"] !== null ? (int)$ruta["id_cuadrilla"] : null;

        if (!$this->puedeOperarRuta($usuario, $idCuadrillaRuta)) {
            return ["ok" => false, "mensaje" => "Esa ruta no pertenece a tu cuadrilla"];
        }

        $stmt = $conexion->prepare("SELECT estado FROM Camion WHERE id_camion = ?");
        $stmt->bind_param("i", $idCamion);
        $stmt->execute();
        $camion = $stmt->get_result()->fetch_assoc();

        if (!$camion) {
            return ["ok" => false, "mensaje" => "El camión indicado no existe"];
        }

        if ($camion["estado"] !== "Disponible") {
            return ["ok" => false, "mensaje" => "El camión no está disponible (estado: " . $camion["estado"] . ")"];
        }

        $stmt = $conexion->prepare(
            "SELECT COUNT(*) AS n FROM Recorrido WHERE id_ruta = ? AND estado = 'En curso'"
        );
        $stmt->bind_param("i", $idRuta);
        $stmt->execute();

        if ((int)$stmt->get_result()->fetch_assoc()["n"] > 0) {
            return ["ok" => false, "mensaje" => "Esa ruta ya tiene un recorrido en curso"];
        }

        $idUsuario = (int)$usuario["sub"];

        $conexion->begin_transaction();

        $stmt = $conexion->prepare(
            "INSERT INTO Recorrido (id_ruta, id_camion, id_usuario) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("iii", $idRuta, $idCamion, $idUsuario);

        if (!$stmt->execute()) {
            $conexion->rollback();
            error_log("iniciar recorrido: " . $stmt->error);
            return ["ok" => false, "mensaje" => "No se pudo iniciar el recorrido"];
        }

        $idRecorrido = $conexion->insert_id;

        $upd = $conexion->prepare("UPDATE Camion SET estado = 'En ruta' WHERE id_camion = ?");
        $upd->bind_param("i", $idCamion);
        $upd->execute();

        $conexion->commit();

        return ["ok" => true, "mensaje" => "Recorrido iniciado", "id_recorrido" => $idRecorrido];
    }

    public function registrarRecurso(array $usuario, array $datos): array
    {
        global $conexion;

        $recorrido = $this->obtenerRecorrido((int)$datos["id_recorrido"]);

        if (!$recorrido) {
            return ["ok" => false, "mensaje" => "Recorrido no encontrado"];
        }

        $idCuadrilla = $recorrido["id_cuadrilla"] !== null ? (int)$recorrido["id_cuadrilla"] : null;

        if (!$this->puedeOperarRuta($usuario, $idCuadrilla)) {
            return ["ok" => false, "mensaje" => "Ese recorrido no pertenece a tu cuadrilla"];
        }

        if ($recorrido["estado"] !== "En curso") {
            return ["ok" => false, "mensaje" => "El recorrido ya fue finalizado"];
        }

        $recurso = trim($datos["recurso"]);
        $unidad = isset($datos["unidad"]) ? trim($datos["unidad"]) : null;

        if ($recurso === "" || mb_strlen($recurso) > 100) {
            return ["ok" => false, "mensaje" => "El recurso es obligatorio (máximo 100 caracteres)"];
        }

        if (!is_numeric($datos["cantidad"]) || (float)$datos["cantidad"] <= 0) {
            return ["ok" => false, "mensaje" => "La cantidad debe ser un número mayor que 0"];
        }

        if ($unidad !== null && mb_strlen($unidad) > 20) {
            return ["ok" => false, "mensaje" => "La unidad es demasiado larga"];
        }

        $cantidad = (float)$datos["cantidad"];
        $idRecorrido = (int)$datos["id_recorrido"];

        $stmt = $conexion->prepare(
            "INSERT INTO Recorrido_Recurso (id_recorrido, recurso, cantidad, unidad) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("isds", $idRecorrido, $recurso, $cantidad, $unidad);

        if ($stmt->execute()) {
            return ["ok" => true, "mensaje" => "Recurso registrado"];
        }

        error_log("registrar recurso: " . $stmt->error);
        return ["ok" => false, "mensaje" => "No se pudo registrar el recurso"];
    }

    public function finalizar(array $usuario, array $datos): array
    {
        global $conexion;

        $idRecorrido = (int)$datos["id_recorrido"];
        $recorrido = $this->obtenerRecorrido($idRecorrido);

        if (!$recorrido) {
            return ["ok" => false, "mensaje" => "Recorrido no encontrado"];
        }

        $idCuadrilla = $recorrido["id_cuadrilla"] !== null ? (int)$recorrido["id_cuadrilla"] : null;

        if (!$this->puedeOperarRuta($usuario, $idCuadrilla)) {
            return ["ok" => false, "mensaje" => "Ese recorrido no pertenece a tu cuadrilla"];
        }

        if ($recorrido["estado"] !== "En curso") {
            return ["ok" => false, "mensaje" => "El recorrido ya estaba finalizado"];
        }

        $volumen = null;

        if (isset($datos["volumen_kg"]) && $datos["volumen_kg"] !== "") {
            if (!is_numeric($datos["volumen_kg"]) || (float)$datos["volumen_kg"] < 0) {
                return ["ok" => false, "mensaje" => "El volumen debe ser un número mayor o igual a 0"];
            }
            $volumen = (float)$datos["volumen_kg"];
        }

        $observaciones = isset($datos["observaciones"]) ? mb_substr(trim($datos["observaciones"]), 0, 250) : null;
        $idCamion = (int)$recorrido["id_camion"];

        $conexion->begin_transaction();

        $stmt = $conexion->prepare(
            "UPDATE Recorrido SET estado = 'Finalizado', fecha_fin = NOW(), volumen_kg = ?, observaciones = ?
             WHERE id_recorrido = ?"
        );
        $stmt->bind_param("dsi", $volumen, $observaciones, $idRecorrido);

        if (!$stmt->execute()) {
            $conexion->rollback();
            error_log("finalizar recorrido: " . $stmt->error);
            return ["ok" => false, "mensaje" => "No se pudo finalizar el recorrido"];
        }

        $upd = $conexion->prepare(
            "UPDATE Camion SET estado = 'Disponible' WHERE id_camion = ? AND estado = 'En ruta'"
        );
        $upd->bind_param("i", $idCamion);
        $upd->execute();

        $conexion->commit();

        return ["ok" => true, "mensaje" => "Recorrido finalizado"];
    }
}
