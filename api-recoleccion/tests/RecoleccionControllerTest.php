<?php

namespace Tests;

use RecoleccionController;

require_once __DIR__ . '/../src/controllers/RecoleccionController.php';

class RecoleccionControllerTest extends TestBase
{
    private RecoleccionController $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = new RecoleccionController();
    }

    public function testListarRutasIncluyeCuadrillaYContenedores(): void
    {
        $rutas = array_column($this->c->listarRutas(), null, 'id_ruta');

        $this->assertCount(3, $rutas);
        $this->assertSame('Cuadrilla 1', $rutas[1]['cuadrilla']);
        $this->assertEquals(4, $rutas[1]['cantidad_contenedores']);
        $this->assertStringContainsString('CONT-001', $rutas[1]['contenedores']);
    }

    public function testListarCuadrillasConCamionesEIntegrantes(): void
    {
        $cuadrillas = array_column($this->c->listarCuadrillas(), null, 'id_cuadrilla');

        $this->assertCount(3, $cuadrillas);
        $this->assertEquals(1, $cuadrillas[1]['camiones']);
        $this->assertEquals(2, $cuadrillas[1]['integrantes']);
    }

    public function testCrearRuta(): void
    {
        $r = $this->c->crearRuta([
            'nombre' => 'Ruta Sur', 'zona' => 'Sur', 'frecuencia' => 'Diaria', 'id_cuadrilla' => 1
        ]);

        $this->assertTrue($r['ok']);
        $this->assertCount(4, $this->c->listarRutas());
    }

    public function testCrearRutaSinCuadrilla(): void
    {
        $r = $this->c->crearRuta(['nombre' => 'Ruta Libre', 'zona' => 'Sur', 'id_cuadrilla' => null]);

        $this->assertTrue($r['ok']);
        $this->assertNull($this->valor("SELECT id_cuadrilla FROM Ruta WHERE nombre = 'Ruta Libre'"));
    }

    public function testCrearRutaConCuadrillaInexistente(): void
    {
        $r = $this->c->crearRuta(['nombre' => 'Ruta X', 'zona' => 'Sur', 'id_cuadrilla' => 99]);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('cuadrilla', $r['mensaje']);
    }

    public function testActualizarRuta(): void
    {
        $r = $this->c->actualizarRuta([
            'id_ruta' => 1, 'nombre' => 'Ruta Centro Nueva', 'zona' => 'Centro', 'frecuencia' => 'Lunes', 'id_cuadrilla' => 2
        ]);

        $this->assertTrue($r['ok']);

        $f = $this->fila('SELECT nombre, id_cuadrilla FROM Ruta WHERE id_ruta = 1');

        $this->assertSame('Ruta Centro Nueva', $f['nombre']);
        $this->assertSame(2, (int)$f['id_cuadrilla']);
    }

    public function testAsignarYQuitarCuadrilla(): void
    {
        $this->assertTrue($this->c->asignarCuadrilla(1, 2)['ok']);
        $this->assertSame(2, (int)$this->valor('SELECT id_cuadrilla FROM Ruta WHERE id_ruta = 1'));

        $this->assertTrue($this->c->asignarCuadrilla(1, null)['ok']);
        $this->assertNull($this->valor('SELECT id_cuadrilla FROM Ruta WHERE id_ruta = 1'));
    }

    public function testAsignarCuadrillaInexistente(): void
    {
        $this->assertFalse($this->c->asignarCuadrilla(1, 99)['ok']);
    }

    public function testEliminarRutaLiberaSusContenedores(): void
    {
        $r = $this->c->eliminarRuta(2);

        $this->assertTrue($r['ok']);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM Ruta WHERE id_ruta = 2'));
        $this->assertNull($this->valor('SELECT id_ruta FROM Contenedor WHERE id_contenedor = 5'));
    }

    public function testNoSePuedeEliminarUnaRutaConRecorridosYNadaSeModifica(): void
    {
        $r = $this->c->eliminarRuta(1);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('recorridos', $r['mensaje']);
        $this->assertSame(1, (int)$this->valor('SELECT COUNT(*) FROM Ruta WHERE id_ruta = 1'));
        $this->assertSame(1, (int)$this->valor('SELECT id_ruta FROM Contenedor WHERE id_contenedor = 1'),
            'La transacción debe deshacerse y los contenedores conservan su ruta');
    }

    public function testEliminarRutaInexistente(): void
    {
        $r = $this->c->eliminarRuta(9999);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('No se encontró', $r['mensaje']);
    }
}
