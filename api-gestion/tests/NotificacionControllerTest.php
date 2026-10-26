<?php

namespace Tests;

use NotificacionController;

require_once __DIR__ . '/../src/controllers/NotificacionController.php';

class NotificacionControllerTest extends TestBase
{
    private NotificacionController $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = new NotificacionController();
    }

    public function testCrearGeneraUnaNotificacionPorDestinatarioSinRepetidos(): void
    {
        NotificacionController::crear([1, 2, 2, 0], 'Hola', 'info');

        $this->assertSame(1, (int)$this->valor('SELECT COUNT(*) FROM Notificacion WHERE id_usuario = 1'));
        $this->assertSame(1, (int)$this->valor('SELECT COUNT(*) FROM Notificacion WHERE id_usuario = 2'));
        $this->assertSame(2, (int)$this->valor('SELECT COUNT(*) FROM Notificacion'));
    }

    public function testUnTipoDesconocidoSeConvierteEnInfo(): void
    {
        NotificacionController::crear([1], 'Mensaje', 'otra-cosa');

        $this->assertSame('info', $this->valor('SELECT tipo FROM Notificacion WHERE id_usuario = 1'));
    }

    public function testNoNotificaAlUsuarioExcluido(): void
    {
        NotificacionController::crear([1, 2], 'Mensaje', 'info', 1);

        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM Notificacion WHERE id_usuario = 1'));
        $this->assertSame(1, (int)$this->valor('SELECT COUNT(*) FROM Notificacion WHERE id_usuario = 2'));
    }

    public function testListarDevuelveLasDelUsuarioYCuentaLasNoLeidas(): void
    {
        NotificacionController::crear([1], 'Primera', 'info');
        NotificacionController::crear([1], 'Segunda', 'alerta');
        NotificacionController::crear([2], 'De otro usuario', 'info');

        $r = $this->c->listar(1);

        $this->assertCount(2, $r['notificaciones']);
        $this->assertSame(2, $r['no_leidas']);
        $this->assertSame('Segunda', $r['notificaciones'][0]['mensaje'], 'Las más nuevas primero');
    }

    public function testMarcarUnaComoLeida(): void
    {
        NotificacionController::crear([1], 'A', 'info');
        NotificacionController::crear([1], 'B', 'info');
        $id = (int)$this->valor("SELECT id_notificacion FROM Notificacion WHERE mensaje = 'A'");

        $this->c->marcarLeida(1, $id);

        $this->assertSame(1, $this->c->listar(1)['no_leidas']);
    }

    public function testMarcarTodasComoLeidas(): void
    {
        NotificacionController::crear([1], 'A', 'info');
        NotificacionController::crear([1], 'B', 'info');

        $this->c->marcarLeida(1, null);

        $this->assertSame(0, $this->c->listar(1)['no_leidas']);
    }

    public function testNoSePuedeMarcarLaNotificacionDeOtroUsuario(): void
    {
        NotificacionController::crear([2], 'Para el usuario 2', 'info');
        $id = (int)$this->valor('SELECT id_notificacion FROM Notificacion WHERE id_usuario = 2');

        $this->c->marcarLeida(1, $id);

        $this->assertSame(1, $this->c->listar(2)['no_leidas']);
    }

    public function testDestinatariosPorRolSoloIncluyeUsuariosActivos(): void
    {
        $ids = NotificacionController::idsPorRol('Administrador');
        sort($ids);

        $this->assertSame([1, 6], $ids);

        $this->consulta("UPDATE Usuario SET estado = 'Inactivo' WHERE id_usuario = 1");

        $this->assertSame([6], NotificacionController::idsPorRol('Administrador'));
    }

    public function testDestinatariosPorCuadrilla(): void
    {
        $ids = NotificacionController::idsPorCuadrilla(1);
        sort($ids);

        $this->assertSame([1, 3], $ids);
    }
}
