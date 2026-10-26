<?php

namespace Tests;

use RecorridoController;

require_once __DIR__ . '/../src/controllers/RecorridoController.php';

// Recorridos de recolección: inicio, recursos utilizados y cierre
class RecorridoControllerTest extends TestBase
{
    private RecorridoController $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = new RecorridoController();
    }

    private function iniciar(array $usuario, int $ruta = 1, int $camion = 1): array
    {
        return $this->c->iniciar($usuario, $ruta, $camion);
    }

    // ------------------------------------------------------------ Iniciar

    public function testIniciarRecorridoMarcaElCamionEnRuta(): void
    {
        $r = $this->iniciar($this->admin());

        $this->assertTrue($r['ok']);

        $f = $this->fila('SELECT estado, id_usuario FROM Recorrido WHERE id_recorrido = ?', [(int)$r['id_recorrido']]);

        $this->assertSame('En curso', $f['estado']);
        $this->assertSame(6, (int)$f['id_usuario']);
        $this->assertSame('En ruta', $this->valor('SELECT estado FROM Camion WHERE id_camion = 1'));
    }

    public function testNoSePuedeUsarUnCamionQueNoEstaDisponible(): void
    {
        // El camión 3 está en mantenimiento
        $r = $this->iniciar($this->admin(), 3, 3);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('no está disponible', $r['mensaje']);
    }

    public function testUnaRutaNoPuedeTenerDosRecorridosEnCurso(): void
    {
        $this->iniciar($this->admin(), 1, 1);

        $r = $this->iniciar($this->admin(), 1, 2);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('en curso', $r['mensaje']);
    }

    public function testUnCamionEnRutaNoPuedeSalirDeNuevo(): void
    {
        $this->iniciar($this->admin(), 1, 1);

        $this->assertFalse($this->iniciar($this->admin(), 2, 1)['ok']);
    }

    public function testRutaOCamionInexistentes(): void
    {
        $this->assertFalse($this->iniciar($this->admin(), 9999, 1)['ok']);
        $this->assertFalse($this->iniciar($this->admin(), 1, 9999)['ok']);
    }

    public function testUnaCuadrillaPuedeIniciarLaRutaDeSuCuadrilla(): void
    {
        // Lautaro (Cuadrilla 1) y la ruta 1 (Cuadrilla 1)
        $this->assertTrue($this->iniciar($this->cuadrilla1(), 1, 1)['ok']);
    }

    public function testUnaCuadrillaNoPuedeIniciarLaRutaDeOtra(): void
    {
        // La ruta 2 pertenece a la Cuadrilla 2
        $r = $this->iniciar($this->cuadrilla1(), 2, 2);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('no pertenece', $r['mensaje']);
        $this->assertSame('Disponible', $this->valor('SELECT estado FROM Camion WHERE id_camion = 2'));
    }

    // ------------------------------------------------------------ Recursos

    public function testRegistrarRecursoUtilizado(): void
    {
        $id = (int)$this->iniciar($this->admin())['id_recorrido'];

        $r = $this->c->registrarRecurso($this->admin(), [
            'id_recorrido' => $id, 'recurso' => 'Combustible', 'cantidad' => 40, 'unidad' => 'L'
        ]);

        $this->assertTrue($r['ok']);

        $f = $this->fila('SELECT recurso, cantidad, unidad FROM Recorrido_Recurso WHERE id_recorrido = ?', [$id]);

        $this->assertSame('Combustible', $f['recurso']);
        $this->assertEquals(40, $f['cantidad']);
        $this->assertSame('L', $f['unidad']);
    }

    public function testRecursoConDatosInvalidos(): void
    {
        $id = (int)$this->iniciar($this->admin())['id_recorrido'];

        $this->assertFalse($this->c->registrarRecurso($this->admin(), ['id_recorrido' => $id, 'recurso' => 'X', 'cantidad' => 0])['ok']);
        $this->assertFalse($this->c->registrarRecurso($this->admin(), ['id_recorrido' => $id, 'recurso' => 'X', 'cantidad' => 'abc'])['ok']);
        $this->assertFalse($this->c->registrarRecurso($this->admin(), ['id_recorrido' => $id, 'recurso' => '', 'cantidad' => 5])['ok']);
    }

    public function testNoSePuedenAgregarRecursosAUnRecorridoFinalizado(): void
    {
        // El recorrido 1 de la semilla ya está finalizado
        $r = $this->c->registrarRecurso($this->admin(), ['id_recorrido' => 1, 'recurso' => 'Bolsas', 'cantidad' => 10]);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('finalizado', $r['mensaje']);
    }

    public function testUnaCuadrillaNoPuedeCargarRecursosEnElRecorridoDeOtra(): void
    {
        $id = (int)$this->iniciar($this->admin(), 2, 2)['id_recorrido'];

        $r = $this->c->registrarRecurso($this->cuadrilla1(), ['id_recorrido' => $id, 'recurso' => 'Bolsas', 'cantidad' => 10]);

        $this->assertFalse($r['ok']);
    }

    public function testRegistrarRecursoEnRecorridoInexistente(): void
    {
        $this->assertFalse($this->c->registrarRecurso($this->admin(), ['id_recorrido' => 9999, 'recurso' => 'X', 'cantidad' => 1])['ok']);
    }

    // ------------------------------------------------------------ Finalizar

    public function testFinalizarRecorridoLiberaElCamionYGuardaElVolumen(): void
    {
        $id = (int)$this->iniciar($this->admin())['id_recorrido'];

        $r = $this->c->finalizar($this->admin(), [
            'id_recorrido' => $id, 'volumen_kg' => 1500.5, 'observaciones' => 'Sin novedades'
        ]);

        $this->assertTrue($r['ok']);

        $f = $this->fila('SELECT estado, fecha_fin, volumen_kg, observaciones FROM Recorrido WHERE id_recorrido = ?', [$id]);

        $this->assertSame('Finalizado', $f['estado']);
        $this->assertNotNull($f['fecha_fin']);
        $this->assertEqualsWithDelta(1500.5, (float)$f['volumen_kg'], 0.001);
        $this->assertSame('Disponible', $this->valor('SELECT estado FROM Camion WHERE id_camion = 1'));
    }

    public function testFinalizarSinVolumenEsValido(): void
    {
        $id = (int)$this->iniciar($this->admin())['id_recorrido'];

        $this->assertTrue($this->c->finalizar($this->admin(), ['id_recorrido' => $id])['ok']);
    }

    public function testFinalizarDosVecesEsRechazado(): void
    {
        $id = (int)$this->iniciar($this->admin())['id_recorrido'];
        $this->c->finalizar($this->admin(), ['id_recorrido' => $id]);

        $this->assertFalse($this->c->finalizar($this->admin(), ['id_recorrido' => $id])['ok']);
    }

    public function testVolumenNegativoEsRechazado(): void
    {
        $id = (int)$this->iniciar($this->admin())['id_recorrido'];

        $r = $this->c->finalizar($this->admin(), ['id_recorrido' => $id, 'volumen_kg' => -3]);

        $this->assertFalse($r['ok']);
        $this->assertSame('En curso', $this->valor('SELECT estado FROM Recorrido WHERE id_recorrido = ?', [$id]));
    }

    public function testUnaCuadrillaNoPuedeFinalizarElRecorridoDeOtra(): void
    {
        $id = (int)$this->iniciar($this->admin(), 2, 2)['id_recorrido'];

        $this->assertFalse($this->c->finalizar($this->cuadrilla1(), ['id_recorrido' => $id])['ok']);
    }

    // ------------------------------------------------------------ Listar

    public function testListarRecorridosIncluyeSusRecursos(): void
    {
        $lista = $this->c->listar($this->admin());

        $this->assertCount(1, $lista);
        $this->assertSame('Finalizado', $lista[0]['estado']);
        $this->assertCount(2, $lista[0]['recursos']);
    }

    public function testUnaCuadrillaSoloVeLosRecorridosDeSusRutas(): void
    {
        $this->assertCount(1, $this->c->listar($this->cuadrilla1()));

        // El usuario 5 pertenece a la Cuadrilla 3, que no hizo ningún recorrido
        $this->assertSame([], $this->c->listar($this->cuadrilla3()));
    }
}
