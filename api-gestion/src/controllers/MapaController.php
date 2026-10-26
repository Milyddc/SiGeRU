<?php

require_once __DIR__ . "/../database.php";

class MapaController
{
    private function filas($resultado): array
    {
        $filas = [];

        while ($fila = $resultado->fetch_assoc()) {
            $filas[] = $fila;
        }

        return $filas;
    }

    private function aNumeros(array $filas, array $campos = ["latitud", "longitud"]): array
    {
        foreach ($filas as &$fila) {
            foreach ($campos as $campo) {
                if (isset($fila[$campo])) {
                    $fila[$campo] = (float)$fila[$campo];
                }
            }
        }

        return $filas;
    }

    public function datosMapa()
    {
        global $conexion;

        $contenedores = $this->aNumeros($this->filas($conexion->query(
            "SELECT c.id_contenedor, c.codigo, c.ubicacion, c.latitud, c.longitud, c.zona,
                    c.estado, c.id_ruta, t.nombre AS tipo_residuo, r.nombre AS ruta,
                    (SELECT COUNT(*) FROM Incidencia i
                      WHERE i.id_contenedor = c.id_contenedor AND i.estado <> 'Resuelta') AS incidencias_abiertas
             FROM Contenedor c
             LEFT JOIN Tipo_Residuo t ON t.id_tipo_residuo = c.id_tipo_residuo
             LEFT JOIN Ruta r ON r.id_ruta = c.id_ruta
             WHERE c.latitud IS NOT NULL AND c.longitud IS NOT NULL
             ORDER BY c.id_contenedor"
        )));

        $incidencias = $this->aNumeros($this->filas($conexion->query(
            "SELECT i.id_incidencia, i.tipo, i.descripcion, i.estado, i.prioridad, i.fecha,
                    c.codigo AS contenedor, c.latitud, c.longitud, cu.nombre AS cuadrilla
             FROM Incidencia i
             JOIN Contenedor c ON c.id_contenedor = i.id_contenedor
             LEFT JOIN Cuadrilla cu ON cu.id_cuadrilla = i.id_cuadrilla
             WHERE i.estado <> 'Resuelta' AND c.latitud IS NOT NULL AND c.longitud IS NOT NULL
             ORDER BY i.fecha DESC"
        )));

        $centros = $this->aNumeros($this->filas($conexion->query(
            "SELECT id_centro_acopio, nombre, direccion, capacidad, latitud, longitud
             FROM Centro_Acopio
             WHERE latitud IS NOT NULL AND longitud IS NOT NULL"
        )));

        // Rutas: línea que une, en orden, los contenedores que pertenecen a cada ruta
        $rutas = $this->filas($conexion->query(
            "SELECT r.id_ruta, r.nombre, r.zona, cu.nombre AS cuadrilla
             FROM Ruta r
             LEFT JOIN Cuadrilla cu ON cu.id_cuadrilla = r.id_cuadrilla
             ORDER BY r.id_ruta"
        ));

        foreach ($rutas as &$ruta) {
            $ruta["puntos"] = [];

            foreach ($contenedores as $c) {
                if ((int)$c["id_ruta"] === (int)$ruta["id_ruta"]) {
                    $ruta["puntos"][] = [$c["latitud"], $c["longitud"]];
                }
            }
        }

        return [
            "contenedores" => $contenedores,
            "incidencias" => $incidencias,
            "centros" => $centros,
            "rutas" => $rutas
        ];
    }
}
