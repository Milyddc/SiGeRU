<?php

namespace Tests;

use CamionController;

require_once __DIR__ . '/../src/controllers/CamionController.php';

class CamionControllerTest extends TestBase
{
    private CamionController $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = new CamionController();
    }

    private function datosCamion(array $cambios = []): array
    {
        return array_merge([
            'matricula' => 'SBC 1111',
            'modelo' => 'Modelo de prueba',
            'capacidad' => 9500.5,
            'estado' => 'Disponible',
            'id_cuadrilla' => 1
        ], $cambios);
    }

    private function ayer(): string
    {
        return date('Y-m-d', strtotime('-1 day'));
    }

    public function testListarCamionesIncluyeLaCuadrilla(): void
    {
        $camiones = $this->c->listar();

        $this->assertCount(3, $camiones);
        $this->assertArrayHasKey('cuadrilla', $camiones[0]);
    }

    public function testCrearCamionConservaLosDecimalesDeLaCapacidad(): void
    {
        $r = $this->c->crear($this->datosCamion());

        $this->assertTrue($r['ok']);

        $capacidad = (float)$this->valor('SELECT capacidad FROM Camion WHERE matricula = ?', ['SBC 1111']);

        $this->assertEqualsWithDelta(9500.5, $capacidad, 0.001);
    }

    public function testMatriculaDuplicada(): void
    {
        $r = $this->c->crear($this->datosCamion(['matricula' => 'SAB 1234']));

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('matrícula', $r['mensaje']);
    }

    public function testEstadoInvalido(): void
    {
        $this->assertFalse($this->c->crear($this->datosCamion(['estado' => 'Roto']))['ok']);
    }

    public function testCapacidadInvalida(): void
    {
        $this->assertFalse($this->c->crear($this->datosCamion(['capacidad' => 0]))['ok']);
        $this->assertFalse($this->c->crear($this->datosCamion(['capacidad' => 'abc']))['ok']);
        $this->assertFalse($this->c->crear($this->datosCamion(['capacidad' => -5]))['ok']);
    }

    public function testCuadrillaInexistente(): void
    {
        $r = $this->c->crear($this->datosCamion(['id_cuadrilla' => 99]));

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('cuadrilla', $r['mensaje']);
    }

    public function testActualizarCamion(): void
    {
        $r = $this->c->actualizar($this->datosCamion([
            'id_camion' => 1, 'matricula' => 'SAB 1234', 'estado' => 'Fuera de Servicio'
        ]));

        $this->assertTrue($r['ok']);
        $this->assertSame('Fuera de Servicio', $this->valor('SELECT estado FROM Camion WHERE id_camion = 1'));
    }

    public function testActualizarConMatriculaDeOtroCamion(): void
    {
        $r = $this->c->actualizar($this->datosCamion(['id_camion' => 1, 'matricula' => 'SAB 5678']));

        $this->assertFalse($r['ok']);
    }

    public function testEliminarCamionSinRecorridos(): void
    {
        $r = $this->c->eliminar(2);

        $this->assertTrue($r['ok']);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM Camion WHERE id_camion = 2'));
    }

    public function testNoSePuedeEliminarUnCamionConRecorridos(): void
    {
        $r = $this->c->eliminar(1);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('recorridos', $r['mensaje']);
    }

    public function testEliminarCamionInexistente(): void
    {
        $this->assertFalse($this->c->eliminar(9999)['ok']);
    }

    public function testListarMantenimientos(): void
    {
        $this->assertCount(2, $this->c->listarMantenimientos(null));
        $this->assertCount(1, $this->c->listarMantenimientos(3));
    }

    public function testMantenimientoVencidoDejaElCamionEnMantenimiento(): void
    {
        $r = $this->c->programarMantenimiento([
            'id_camion' => 1, 'tipo' => 'Preventivo', 'descripcion' => 'Frenos', 'fecha_programada' => $this->ayer()
        ]);

        $this->assertTrue($r['ok']);
        $this->assertSame('En mantenimiento', $this->valor('SELECT estado FROM Camion WHERE id_camion = 1'));
    }

    public function testMantenimientoFuturoNoCambiaElEstadoDelCamion(): void
    {
        $r = $this->c->programarMantenimiento([
            'id_camion' => 2, 'tipo' => 'Preventivo', 'descripcion' => 'Aceite',
            'fecha_programada' => date('Y-m-d', strtotime('+30 days'))
        ]);

        $this->assertTrue($r['ok']);
        $this->assertSame('Disponible', $this->valor('SELECT estado FROM Camion WHERE id_camion = 2'));
    }

    public function testProgramarMantenimientoValidaLosDatos(): void
    {
        $base = ['id_camion' => 1, 'tipo' => 'Preventivo', 'descripcion' => 'Frenos', 'fecha_programada' => $this->ayer()];

        $this->assertFalse($this->c->programarMantenimiento(array_merge($base, ['tipo' => 'Raro']))['ok']);
        $this->assertFalse($this->c->programarMantenimiento(array_merge($base, ['descripcion' => '']))['ok']);
        $this->assertFalse($this->c->programarMantenimiento(array_merge($base, ['fecha_programada' => 'mañana']))['ok']);

        $r = $this->c->programarMantenimiento(array_merge($base, ['id_camion' => 9999]));

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('no existe', $r['mensaje']);
        $this->assertSame(2, (int)$this->valor('SELECT COUNT(*) FROM Mantenimiento_Camion'), 'No debe guardarse nada');
    }

    public function testCompletarMantenimientoDejaElCamionDisponible(): void
    {
        $r = $this->c->completarMantenimiento(1);

        $this->assertTrue($r['ok']);

        $m = $this->fila('SELECT estado, fecha_realizada FROM Mantenimiento_Camion WHERE id_mantenimiento = 1');

        $this->assertSame('Realizado', $m['estado']);
        $this->assertNotNull($m['fecha_realizada']);
        $this->assertSame('Disponible', $this->valor('SELECT estado FROM Camion WHERE id_camion = 3'));
    }

    public function testNoSeLiberaElCamionSiQuedanMantenimientosVencidos(): void
    {
        $this->c->programarMantenimiento([
            'id_camion' => 3, 'tipo' => 'Correctivo', 'descripcion' => 'Otra falla', 'fecha_programada' => $this->ayer()
        ]);

        $this->c->completarMantenimiento(1);

        $this->assertSame('En mantenimiento', $this->valor('SELECT estado FROM Camion WHERE id_camion = 3'));
    }

    public function testCompletarDosVecesEsRechazado(): void
    {
        $this->c->completarMantenimiento(1);

        $r = $this->c->completarMantenimiento(1);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('ya estaba', $r['mensaje']);
    }

    public function testCompletarMantenimientoInexistente(): void
    {
        $this->assertFalse($this->c->completarMantenimiento(9999)['ok']);
    }
}
