<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

abstract class TestBase extends TestCase
{
    protected function setUp(): void
    {
        reiniciarBaseDeDatos();
    }

    protected function admin(): array
    {
        return ['sub' => 6, 'rol' => 'Administrador', 'nombre' => 'Administrador Prueba'];
    }

    protected function operario(): array
    {
        return ['sub' => 2, 'rol' => 'Operario', 'nombre' => 'Santiago Valenzuela'];
    }

    protected function cuadrilla1(): array
    {
        return ['sub' => 3, 'rol' => 'Cuadrilla', 'nombre' => 'Lautaro Ibáñez'];
    }

    protected function cuadrilla3(): array
    {
        return ['sub' => 5, 'rol' => 'Cuadrilla', 'nombre' => 'Usuario Cuadrilla'];
    }

    protected function vecino(): array
    {
        return ['sub' => 7, 'rol' => 'Vecino', 'nombre' => 'Vecino Prueba'];
    }

    protected function consulta(string $sql, array $params = []): array
    {
        global $conexion;

        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            $this->fail('SQL inválido en el test: ' . $conexion->error);
        }

        if ($params) {
            $tipos = '';

            foreach ($params as $p) {
                $tipos .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
            }

            $stmt->bind_param($tipos, ...$params);
        }

        $stmt->execute();

        $resultado = $stmt->get_result();

        return $resultado ? $resultado->fetch_all(MYSQLI_ASSOC) : [];
    }

    protected function fila(string $sql, array $params = []): ?array
    {
        return $this->consulta($sql, $params)[0] ?? null;
    }

    protected function valor(string $sql, array $params = [])
    {
        $fila = $this->fila($sql, $params);

        return $fila ? array_values($fila)[0] : null;
    }
}
