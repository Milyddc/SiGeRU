<?php

require_once __DIR__ . "/../database.php";

class EstadisticaController
{
    private function filas(string $sql): array
    {
        global $conexion;

        $resultado = $conexion->query($sql);

        if (!$resultado) {
            error_log("Estadistica: " . $conexion->error);
            return [];
        }

        $filas = [];

        while ($fila = $resultado->fetch_assoc()) {
            $filas[] = $fila;
        }

        return $filas;
    }

    public function resumen(): array
    {
        $horas = $this->filas(
            "SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, fecha, fecha_resolucion)) / 60, 1) AS horas
             FROM Incidencia
             WHERE estado = 'Resuelta' AND fecha_resolucion IS NOT NULL"
        );

        return [
            "por_estado" => $this->filas(
                "SELECT estado, COUNT(*) AS cantidad FROM Incidencia GROUP BY estado"
            ),
            "top_desbordados" => $this->filas(
                "SELECT c.codigo, c.ubicacion, COUNT(*) AS cantidad
                 FROM Incidencia i
                 JOIN Contenedor c ON c.id_contenedor = i.id_contenedor
                 WHERE i.tipo = 'Desborde' AND i.fecha >= DATE_FORMAT(NOW(), '%Y-%m-01')
                 GROUP BY c.id_contenedor, c.codigo, c.ubicacion
                 ORDER BY cantidad DESC, c.codigo
                 LIMIT 5"
            ),
            "por_zona" => $this->filas(
                "SELECT COALESCE(c.zona, 'Sin zona') AS zona, COUNT(*) AS cantidad
                 FROM Incidencia i
                 JOIN Contenedor c ON c.id_contenedor = i.id_contenedor
                 GROUP BY COALESCE(c.zona, 'Sin zona')
                 ORDER BY cantidad DESC"
            ),
            "por_dia" => $this->filas(
                "SELECT DATE(fecha) AS dia, COUNT(*) AS cantidad
                 FROM Incidencia
                 WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                 GROUP BY DATE(fecha)
                 ORDER BY dia"
            ),
            "contenedores_por_estado" => $this->filas(
                "SELECT estado, COUNT(*) AS cantidad FROM Contenedor GROUP BY estado"
            ),
            "volumen_simulado" => $this->filas(
                "SELECT COALESCE(zona, 'Sin zona') AS zona, ROUND(SUM(capacidad) * 0.75, 0) AS volumen
                 FROM Contenedor
                 GROUP BY COALESCE(zona, 'Sin zona')
                 ORDER BY volumen DESC"
            ),
            "tiempo_promedio_horas" => $horas && $horas[0]["horas"] !== null ? (float)$horas[0]["horas"] : null
        ];
    }
}
