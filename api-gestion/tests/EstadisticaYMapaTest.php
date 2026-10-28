<?php

namespace Tests;

use EstadisticaController;
use MapaController;

require_once __DIR__ . '/../src/controllers/EstadisticaController.php';
require_once __DIR__ . '/../src/controllers/MapaController.php';

class EstadisticaYMapaTest extends TestBase
{
    private function porClave(array $filas, string $clave, string $valor): array
    {
        $r = [];

        foreach ($filas as $f) {
            $r[$f[$clave]] = $f[$valor];
        }

        return $r;
    }

    public function testElResumenTieneTodasLasSecciones(): void
    {
        $r = (new EstadisticaController())->resumen();

        foreach (['por_estado', 'top_desbordados', 'por_zona', 'por_dia', 'contenedores_por_estado',
                  'volumen_simulado', 'tiempo_promedio_horas'] as $clave) {
            $this->assertArrayHasKey($clave, $r);
        }
    }

    public function testIncidenciasPorEstado(): void
    {
        $r = (new EstadisticaController())->resumen();
        $estados = $this->porClave($r['por_estado'], 'estado', 'cantidad');

        $this->assertEquals(2, $estados['Abierta']);
        $this->assertEquals(1, $estados['En curso']);
        $this->assertEquals(2, $estados['Resuelta']);
    }

    public function testTiempoPromedioDeResolucion(): void
    {
        $r = (new EstadisticaController())->resumen();

        $this->assertEqualsWithDelta(5.1, $r['tiempo_promedio_horas'], 0.1);
    }

    public function testSinIncidenciasResueltasElTiempoPromedioEsNulo(): void
    {
        $this->consulta("UPDATE Incidencia SET estado = 'Abierta', fecha_resolucion = NULL");

        $this->assertNull((new EstadisticaController())->resumen()['tiempo_promedio_horas']);
    }

    public function testZonasCriticasOrdenadasPorCantidad(): void
    {
        $zonas = (new EstadisticaController())->resumen()['por_zona'];

        $this->assertSame('Centro', $zonas[0]['zona']);
        $this->assertEquals(3, $zonas[0]['cantidad']);
    }

    public function testTopDeContenedoresDesbordadosDelMes(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->consulta("INSERT INTO Incidencia (tipo, descripcion, id_usuario, id_contenedor) VALUES ('Desborde', 't', 1, 3)");
        }
        $this->consulta("INSERT INTO Incidencia (tipo, descripcion, id_usuario, id_contenedor) VALUES ('Desborde', 't', 1, 4)");

        $top = (new EstadisticaController())->resumen()['top_desbordados'];

        $this->assertSame('CONT-003', $top[0]['codigo']);
        $this->assertEquals(3, $top[0]['cantidad']);
        $this->assertLessThanOrEqual(5, count($top));
    }

    public function testVolumenSimuladoEsElSetentaYCincoPorCientoDeLaCapacidad(): void
    {
        $volumen = $this->porClave(
            (new EstadisticaController())->resumen()['volumen_simulado'], 'zona', 'volumen'
        );

        $this->assertEquals(4275, $volumen['Centro']);
    }

    public function testContenedoresPorEstado(): void
    {
        $estados = $this->porClave(
            (new EstadisticaController())->resumen()['contenedores_por_estado'], 'estado', 'cantidad'
        );

        $this->assertEquals(4, $estados['Disponible']);
        $this->assertEquals(1, $estados['Lleno']);
    }

    public function testElMapaDevuelveSusCuatroCapas(): void
    {
        $datos = (new MapaController())->datosMapa();

        $this->assertCount(6, $datos['contenedores']);
        $this->assertCount(3, $datos['centros']);
        $this->assertCount(3, $datos['rutas']);
        $this->assertArrayHasKey('incidencias', $datos);
    }

    public function testLasCoordenadasSonNumericas(): void
    {
        $c = (new MapaController())->datosMapa()['contenedores'][0];

        $this->assertIsFloat($c['latitud']);
        $this->assertIsFloat($c['longitud']);
    }

    public function testSoloAparecenIncidenciasAbiertas(): void
    {
        $incidencias = (new MapaController())->datosMapa()['incidencias'];

        $this->assertCount(3, $incidencias);

        foreach ($incidencias as $i) {
            $this->assertNotSame('Resuelta', $i['estado']);
        }
    }

    public function testUnContenedorSinCoordenadasNoAparece(): void
    {
        $this->consulta('UPDATE Contenedor SET latitud = NULL, longitud = NULL WHERE id_contenedor = 1');

        $this->assertCount(5, (new MapaController())->datosMapa()['contenedores']);
    }

    public function testLaRutaUneLosContenedoresQueLePertenecen(): void
    {
        $rutas = (new MapaController())->datosMapa()['rutas'];

        $porId = array_column($rutas, null, 'id_ruta');

        $this->assertCount(4, $porId[1]['puntos']);
        $this->assertCount(1, $porId[2]['puntos']);
    }

    public function testCadaContenedorIndicaCuantasIncidenciasAbiertasTiene(): void
    {
        $contenedores = array_column((new MapaController())->datosMapa()['contenedores'], null, 'codigo');

        $this->assertEquals(1, $contenedores['CONT-001']['incidencias_abiertas']);
        $this->assertEquals(0, $contenedores['CONT-002']['incidencias_abiertas']);
    }
}
