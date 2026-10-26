<?php

namespace Tests;

use GestionController;

require_once __DIR__ . '/../src/controllers/GestionController.php';

// CRUD de contenedores y centros de acopio
class GestionControllerTest extends TestBase
{
    private GestionController $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = new GestionController();
    }

    private function datosContenedor(array $cambios = []): array
    {
        return array_merge([
            'codigo' => 'CONT-100',
            'ubicacion' => 'Calle Test 123',
            'estado' => 'Disponible',
            'capacidad' => 1100,
            'id_tipo_residuo' => 1,
            'id_ruta' => 1,
            'id_centro_acopio' => 1,
            'latitud' => '-34.9011',
            'longitud' => '-56.1645',
            'zona' => 'Centro'
        ], $cambios);
    }

    // ------------------------------------------------------------ Contenedores

    public function testListarContenedoresIncluyeDatosRelacionados(): void
    {
        $lista = $this->c->listarContenedores();

        $this->assertCount(6, $lista);

        $codigos = array_column($lista, null, 'codigo');

        $this->assertArrayHasKey('CONT-001', $codigos);

        $primero = $codigos['CONT-001'];

        $this->assertArrayHasKey('tipo_residuo', $primero);
        $this->assertArrayHasKey('ruta', $primero);
        $this->assertArrayHasKey('centro_acopio', $primero);
        $this->assertArrayHasKey('latitud', $primero);
        $this->assertSame('Orgánico', $primero['tipo_residuo']);
    }

    public function testCrearContenedorConCoordenadasYZona(): void
    {
        $r = $this->c->crearContenedor($this->datosContenedor());

        $this->assertTrue($r['ok']);

        $f = $this->fila('SELECT * FROM Contenedor WHERE codigo = ?', ['CONT-100']);

        $this->assertNotNull($f);
        $this->assertEqualsWithDelta(-34.9011, (float)$f['latitud'], 0.0001);
        $this->assertEqualsWithDelta(-56.1645, (float)$f['longitud'], 0.0001);
        $this->assertSame('Centro', $f['zona']);
    }

    public function testCrearContenedorSinRutaNiCoordenadas(): void
    {
        $r = $this->c->crearContenedor($this->datosContenedor([
            'id_ruta' => null, 'id_centro_acopio' => '', 'latitud' => '', 'longitud' => '', 'zona' => ''
        ]));

        $this->assertTrue($r['ok']);

        $f = $this->fila('SELECT * FROM Contenedor WHERE codigo = ?', ['CONT-100']);

        $this->assertNull($f['id_ruta']);
        $this->assertNull($f['id_centro_acopio']);
        $this->assertNull($f['latitud']);
        $this->assertNull($f['zona']);
    }

    public function testCodigoDeContenedorDuplicado(): void
    {
        $r = $this->c->crearContenedor($this->datosContenedor(['codigo' => 'CONT-001']));

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('código', $r['mensaje']);
    }

    public function testLatitudFueraDeRangoEsRechazada(): void
    {
        $r = $this->c->crearContenedor($this->datosContenedor(['latitud' => '95']));

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('latitud', $r['mensaje']);
    }

    public function testLongitudFueraDeRangoEsRechazada(): void
    {
        $r = $this->c->crearContenedor($this->datosContenedor(['longitud' => '-200']));

        $this->assertFalse($r['ok']);
    }

    public function testLatitudSinLongitudEsRechazada(): void
    {
        $r = $this->c->crearContenedor($this->datosContenedor(['longitud' => '']));

        $this->assertFalse($r['ok']);
    }

    public function testActualizarContenedor(): void
    {
        $datos = $this->datosContenedor([
            'id_contenedor' => 2, 'codigo' => 'CONT-002', 'estado' => 'Lleno', 'zona' => 'Norte'
        ]);

        $r = $this->c->actualizarContenedor($datos);

        $this->assertTrue($r['ok']);

        $f = $this->fila('SELECT estado, zona FROM Contenedor WHERE id_contenedor = 2');

        $this->assertSame('Lleno', $f['estado']);
        $this->assertSame('Norte', $f['zona']);
    }

    public function testActualizarContenedorConCodigoDeOtroFalla(): void
    {
        $datos = $this->datosContenedor(['id_contenedor' => 2, 'codigo' => 'CONT-001']);

        $this->assertFalse($this->c->actualizarContenedor($datos)['ok']);
    }

    public function testEliminarContenedorSinIncidencias(): void
    {
        $r = $this->c->eliminarContenedor(4);

        $this->assertTrue($r['ok']);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM Contenedor WHERE id_contenedor = 4'));
    }

    public function testEliminarContenedorConIncidenciasDevuelveMensajeClaro(): void
    {
        $r = $this->c->eliminarContenedor(1);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('incidencias', $r['mensaje']);
    }

    public function testEliminarContenedorInexistente(): void
    {
        $r = $this->c->eliminarContenedor(9999);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('No se encontró', $r['mensaje']);
    }

    // ------------------------------------------------------------ Centros de acopio

    public function testListarCentros(): void
    {
        $this->assertCount(3, $this->c->listarCentros());
    }

    public function testCrearCentro(): void
    {
        $r = $this->c->crearCentro(['nombre' => 'Centro Test', 'direccion' => 'Calle 1', 'capacidad' => 3000]);

        $this->assertTrue($r['ok']);
        $this->assertCount(4, $this->c->listarCentros());
    }

    public function testActualizarCentro(): void
    {
        $r = $this->c->actualizarCentro([
            'id_centro_acopio' => 1, 'nombre' => 'Renombrado', 'direccion' => 'Otra calle 2', 'capacidad' => 7000
        ]);

        $this->assertTrue($r['ok']);

        $f = $this->fila('SELECT nombre, capacidad FROM Centro_Acopio WHERE id_centro_acopio = 1');

        $this->assertSame('Renombrado', $f['nombre']);
        $this->assertEquals(7000, $f['capacidad']);
    }

    public function testEliminarCentroSinRelaciones(): void
    {
        $this->c->crearCentro(['nombre' => 'Centro Test', 'direccion' => 'Calle 1', 'capacidad' => 3000]);
        $id = (int)$this->valor('SELECT id_centro_acopio FROM Centro_Acopio WHERE nombre = ?', ['Centro Test']);

        $r = $this->c->eliminarCentro($id);

        $this->assertTrue($r['ok']);
        $this->assertCount(3, $this->c->listarCentros());
    }

    public function testEliminarCentroConContenedoresOUsuariosFalla(): void
    {
        $r = $this->c->eliminarCentro(1);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('No se puede eliminar', $r['mensaje']);
    }

    public function testEliminarCentroInexistente(): void
    {
        $this->assertFalse($this->c->eliminarCentro(9999)['ok']);
    }
}
