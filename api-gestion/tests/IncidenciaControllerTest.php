<?php

namespace Tests;

use IncidenciaController;

require_once __DIR__ . '/../src/controllers/IncidenciaController.php';

// Registro y seguimiento de incidencias
class IncidenciaControllerTest extends TestBase
{
    private IncidenciaController $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = new IncidenciaController();
    }

    private function nueva(array $usuario, array $cambios = []): array
    {
        return $this->c->crear($usuario, array_merge([
            'id_contenedor' => 2,
            'tipo' => 'Rotura',
            'descripcion' => 'La tapa está rota',
            'prioridad' => 'Alta'
        ], $cambios));
    }

    // ------------------------------------------------------------ Crear

    public function testCrearIncidenciaComoVecinoUsaElUsuarioDelTokenYPrioridadMedia(): void
    {
        $r = $this->nueva($this->vecino());

        $this->assertTrue($r['ok']);

        $f = $this->fila('SELECT * FROM Incidencia WHERE id_incidencia = ?', [(int)$r['id_incidencia']]);

        $this->assertSame(7, (int)$f['id_usuario']);
        $this->assertSame('Media', $f['prioridad'], 'Un vecino no puede definir la prioridad');
        $this->assertSame('Abierta', $f['estado']);
    }

    public function testCrearIncidenciaComoOperarioRespetaLaPrioridad(): void
    {
        $r = $this->nueva($this->operario(), ['prioridad' => 'Alta']);

        $this->assertSame('Alta', $this->valor(
            'SELECT prioridad FROM Incidencia WHERE id_incidencia = ?', [(int)$r['id_incidencia']]
        ));
    }

    public function testCrearIncidenciaRegistraElHistorialInicial(): void
    {
        $r = $this->nueva($this->vecino());

        $historial = $this->consulta(
            'SELECT estado_anterior, estado_nuevo FROM Historial_Incidencia WHERE id_incidencia = ?',
            [(int)$r['id_incidencia']]
        );

        $this->assertCount(1, $historial);
        $this->assertNull($historial[0]['estado_anterior']);
        $this->assertSame('Abierta', $historial[0]['estado_nuevo']);
    }

    public function testUnDesbordeMarcaElContenedorComoLleno(): void
    {
        $this->nueva($this->operario(), ['id_contenedor' => 2, 'tipo' => 'Desborde']);

        $this->assertSame('Lleno', $this->valor('SELECT estado FROM Contenedor WHERE id_contenedor = 2'));
    }

    public function testUnaRoturaNoCambiaElEstadoDelContenedor(): void
    {
        $this->nueva($this->operario(), ['id_contenedor' => 2, 'tipo' => 'Rotura']);

        $this->assertSame('Disponible', $this->valor('SELECT estado FROM Contenedor WHERE id_contenedor = 2'));
    }

    public function testCrearIncidenciaAvisaAAdministradoresYOperarios(): void
    {
        $this->nueva($this->vecino(), ['tipo' => 'Desborde']);

        // Administradores (1 y 6) y operarios (2 y 4); el vecino que reporta no recibe aviso
        foreach ([1, 6, 2, 4] as $idUsuario) {
            $n = $this->fila(
                "SELECT tipo FROM Notificacion WHERE id_usuario = ? AND mensaje LIKE 'Nueva incidencia%'",
                [$idUsuario]
            );

            $this->assertNotNull($n, "El usuario $idUsuario debía recibir la alerta");
            $this->assertSame('alerta', $n['tipo']);
        }

        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM Notificacion WHERE id_usuario = 7'));
    }

    public function testTipoInvalidoEsRechazado(): void
    {
        $this->assertFalse($this->nueva($this->vecino(), ['tipo' => 'Explosión'])['ok']);
    }

    public function testPrioridadInvalidaEsRechazada(): void
    {
        $this->assertFalse($this->nueva($this->operario(), ['prioridad' => 'Urgentísima'])['ok']);
    }

    public function testDescripcionVaciaEsRechazada(): void
    {
        $this->assertFalse($this->nueva($this->vecino(), ['descripcion' => '   '])['ok']);
    }

    public function testDescripcionDemasiadoLargaEsRechazada(): void
    {
        $this->assertFalse($this->nueva($this->vecino(), ['descripcion' => str_repeat('a', 1001)])['ok']);
    }

    public function testContenedorInexistenteEsRechazado(): void
    {
        $r = $this->nueva($this->vecino(), ['id_contenedor' => 9999]);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('no existe', $r['mensaje']);
    }

    // ------------------------------------------------------------ Cambiar estado

    public function testResolverRegistraFechaHistorialYLiberaElContenedor(): void
    {
        $id = (int)$this->nueva($this->operario(), ['id_contenedor' => 2, 'tipo' => 'Desborde'])['id_incidencia'];

        $r = $this->c->cambiarEstado($this->admin(), $id, 'Resuelta', 'Recolectado');

        $this->assertTrue($r['ok']);

        $f = $this->fila('SELECT estado, fecha_resolucion FROM Incidencia WHERE id_incidencia = ?', [$id]);

        $this->assertSame('Resuelta', $f['estado']);
        $this->assertNotNull($f['fecha_resolucion']);
        $this->assertSame('Disponible', $this->valor('SELECT estado FROM Contenedor WHERE id_contenedor = 2'));

        $historial = $this->consulta(
            'SELECT estado_nuevo, comentario FROM Historial_Incidencia WHERE id_incidencia = ? ORDER BY id_historial',
            [$id]
        );

        $this->assertCount(2, $historial);
        $this->assertSame('Resuelta', $historial[1]['estado_nuevo']);
        $this->assertSame('Recolectado', $historial[1]['comentario']);
    }

    public function testReabrirUnaIncidenciaLimpiaLaFechaDeResolucion(): void
    {
        // La incidencia 3 de la semilla está resuelta
        $r = $this->c->cambiarEstado($this->admin(), 3, 'Abierta', 'Volvió a desbordar');

        $this->assertTrue($r['ok']);
        $this->assertNull($this->valor('SELECT fecha_resolucion FROM Incidencia WHERE id_incidencia = 3'));
    }

    public function testCambiarEstadoAvisaAQuienReporto(): void
    {
        $id = (int)$this->nueva($this->vecino())['id_incidencia'];

        $this->c->cambiarEstado($this->admin(), $id, 'En curso', null);

        $aviso = $this->valor(
            "SELECT mensaje FROM Notificacion WHERE id_usuario = 7 AND mensaje LIKE 'Tu incidencia%'"
        );

        $this->assertNotNull($aviso);
        $this->assertStringContainsString('En curso', $aviso);
    }

    public function testCambiarAlMismoEstadoEsRechazado(): void
    {
        // La incidencia 1 de la semilla está Abierta
        $this->assertFalse($this->c->cambiarEstado($this->admin(), 1, 'Abierta', null)['ok']);
    }

    public function testEstadoInvalidoEsRechazado(): void
    {
        $this->assertFalse($this->c->cambiarEstado($this->admin(), 1, 'Cerrada', null)['ok']);
    }

    public function testCambiarEstadoDeIncidenciaInexistente(): void
    {
        $this->assertFalse($this->c->cambiarEstado($this->admin(), 9999, 'En curso', null)['ok']);
    }

    public function testCuadrillaPuedeCambiarEstadoDeIncidenciasDeSuCuadrilla(): void
    {
        // Incidencia 1: asignada a la Cuadrilla 1 (Lautaro pertenece a ella)
        $this->assertTrue($this->c->cambiarEstado($this->cuadrilla1(), 1, 'En curso', null)['ok']);
    }

    public function testCuadrillaNoPuedeCambiarEstadoDeOtraCuadrilla(): void
    {
        // Incidencia 2: asignada a la Cuadrilla 2
        $r = $this->c->cambiarEstado($this->cuadrilla1(), 2, 'Resuelta', null);

        $this->assertFalse($r['ok']);
        $this->assertSame('En curso', $this->valor('SELECT estado FROM Incidencia WHERE id_incidencia = 2'));
    }

    // ------------------------------------------------------------ Asignar cuadrilla

    public function testAsignarCuadrillaPasaLaIncidenciaAEnCurso(): void
    {
        // Incidencia 5: abierta y sin cuadrilla
        $r = $this->c->asignarCuadrilla($this->admin(), 5, 2);

        $this->assertTrue($r['ok']);

        $f = $this->fila('SELECT id_cuadrilla, estado FROM Incidencia WHERE id_incidencia = 5');

        $this->assertSame(2, (int)$f['id_cuadrilla']);
        $this->assertSame('En curso', $f['estado']);
    }

    public function testAsignarCuadrillaAvisaALosIntegrantes(): void
    {
        $this->c->asignarCuadrilla($this->admin(), 5, 2);

        // La Cuadrilla 2 está integrada por los usuarios 2 y 4
        foreach ([2, 4] as $idUsuario) {
            $this->assertSame(1, (int)$this->valor(
                "SELECT COUNT(*) FROM Notificacion WHERE id_usuario = ? AND mensaje LIKE '%asignada a tu cuadrilla%'",
                [$idUsuario]
            ));
        }
    }

    public function testAsignarCuadrillaInexistenteEsRechazado(): void
    {
        $this->assertFalse($this->c->asignarCuadrilla($this->admin(), 5, 99)['ok']);
    }

    // ------------------------------------------------------------ Listar y filtros

    public function testAdministradorVeTodasLasIncidencias(): void
    {
        $this->assertCount(5, $this->c->listar($this->admin(), []));
    }

    public function testVecinoSoloVeLasSuyas(): void
    {
        $lista = $this->c->listar($this->vecino(), []);

        $this->assertCount(1, $lista);
        $this->assertSame(5, (int)$lista[0]['id_incidencia']);
    }

    public function testCuadrillaSoloVeLasDeSuCuadrilla(): void
    {
        $ids = array_map('intval', array_column($this->c->listar($this->cuadrilla1(), []), 'id_incidencia'));
        sort($ids);

        $this->assertSame([1, 4], $ids);
    }

    public function testFiltroAbiertasYCerradas(): void
    {
        $this->assertCount(3, $this->c->listar($this->admin(), ['estado' => 'abiertas']));
        $this->assertCount(2, $this->c->listar($this->admin(), ['estado' => 'cerradas']));
        $this->assertCount(1, $this->c->listar($this->admin(), ['estado' => 'En curso']));
    }

    public function testFiltroPorZona(): void
    {
        $this->assertCount(3, $this->c->listar($this->admin(), ['zona' => 'Centro']));
        $this->assertCount(1, $this->c->listar($this->admin(), ['zona' => 'Norte']));
    }

    public function testFiltroPorRangoDeFechas(): void
    {
        $lista = $this->c->listar($this->admin(), ['desde' => '2026-10-05', 'hasta' => '2026-10-08']);

        $this->assertCount(2, $lista);
    }

    public function testFiltroConFechaInvalidaSeIgnora(): void
    {
        $this->assertCount(5, $this->c->listar($this->admin(), ['desde' => "1'; DROP TABLE Incidencia; --"]));
        $this->assertSame(5, (int)$this->valor('SELECT COUNT(*) FROM Incidencia'));
    }

    public function testFiltroPorContenedor(): void
    {
        $this->assertCount(2, $this->c->listar($this->admin(), ['id_contenedor' => 1]));
    }

    // ------------------------------------------------------------ Historial

    public function testHistorialDeUnaIncidencia(): void
    {
        $r = $this->c->historial($this->admin(), 2);

        $this->assertTrue($r['ok']);
        $this->assertCount(2, $r['historial']);
        $this->assertArrayHasKey('usuario', $r['historial'][0]);
    }

    public function testVecinoNoPuedeVerElHistorialDeOtraPersona(): void
    {
        // La incidencia 1 la reportó el usuario 3
        $this->assertFalse($this->c->historial($this->vecino(), 1)['ok']);
    }
}
