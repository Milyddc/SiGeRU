<?php

namespace Tests;

use IncidenciaController;
use Mailer;
use NotificacionController;

require_once __DIR__ . '/../src/controllers/IncidenciaController.php';

class EmailTest extends TestBase
{
    private array $enviados = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->enviados = [];

        Mailer::$enviadorPrueba = function (array $emails, string $asunto, string $cuerpo) {
            $this->enviados[] = ['emails' => $emails, 'asunto' => $asunto, 'cuerpo' => $cuerpo];
            return true;
        };
    }

    protected function tearDown(): void
    {
        Mailer::$enviadorPrueba = null;
    }

    private function emailsDelEnvio(int $indice): array
    {
        $emails = $this->enviados[$indice]['emails'];
        sort($emails);

        return $emails;
    }

    public function testUnaAlertaSeEnviaPorEmail(): void
    {
        NotificacionController::crear([1, 2], 'Mensaje de prueba', 'alerta');

        $this->assertCount(1, $this->enviados);
        $this->assertSame(['emily@sigeru.com', 'santiago@sigeru.com'], $this->emailsDelEnvio(0));
        $this->assertStringContainsString('Alerta', $this->enviados[0]['asunto']);
        $this->assertStringContainsString('Mensaje de prueba', $this->enviados[0]['cuerpo']);
    }

    public function testUnaNotificacionInformativaNoSeEnviaPorEmail(): void
    {
        NotificacionController::crear([1, 2], 'Solo informativa', 'info');

        $this->assertCount(0, $this->enviados);
    }

    public function testSePuedeForzarElEmailEnUnaInformativa(): void
    {
        NotificacionController::crear([1], 'Importante', 'info', null, true);

        $this->assertCount(1, $this->enviados);
    }

    public function testSePuedeEvitarElEmailEnUnaAlerta(): void
    {
        NotificacionController::crear([1], 'Sin email', 'alerta', null, false);

        $this->assertCount(0, $this->enviados);
    }

    public function testNoSeEnviaAlAutorExcluido(): void
    {
        NotificacionController::crear([1, 2], 'Mensaje', 'alerta', 1);

        $this->assertSame(['santiago@sigeru.com'], $this->emailsDelEnvio(0));
    }

    public function testNoSeEnviaAUsuariosInactivos(): void
    {
        $this->consulta("UPDATE Usuario SET estado = 'Inactivo' WHERE id_usuario = 2");

        NotificacionController::crear([1, 2], 'Mensaje', 'alerta');

        $this->assertSame(['emily@sigeru.com'], $this->emailsDelEnvio(0));
    }

    public function testUnFalloDelEnvioNoRompeLaNotificacionInterna(): void
    {
        Mailer::$enviadorPrueba = function () {
            throw new \RuntimeException('Servidor SMTP caído');
        };

        NotificacionController::crear([1], 'Mensaje', 'alerta');

        $this->assertSame(1, (int)$this->valor('SELECT COUNT(*) FROM Notificacion WHERE id_usuario = 1'));
    }

    public function testElEnviadorQueDevuelveFalseTampocoRompeNada(): void
    {
        Mailer::$enviadorPrueba = function () {
            return false;
        };

        NotificacionController::crear([1], 'Mensaje', 'alerta');

        $this->assertSame(1, (int)$this->valor('SELECT COUNT(*) FROM Notificacion WHERE id_usuario = 1'));
    }

    public function testSinDestinatariosValidosNoSeEnvia(): void
    {
        $this->assertFalse(Mailer::enviar(['no-es-un-email', ''], 'Asunto', 'Cuerpo'));
        $this->assertCount(0, $this->enviados);
    }

    public function testLosDestinatariosRepetidosSeUnifican(): void
    {
        Mailer::enviar(['a@test.com', 'a@test.com', 'b@test.com'], 'Asunto', 'Cuerpo');

        $this->assertSame(['a@test.com', 'b@test.com'], $this->emailsDelEnvio(0));
    }

    public function testElCuerpoIncluyeElEnlaceAlSistema(): void
    {
        Mailer::enviar(['a@test.com'], 'Asunto', 'Cuerpo');

        $this->assertStringContainsString('/login.html', $this->enviados[0]['cuerpo']);
    }

    public function testSinConfiguracionSmtpElEnvioQuedaDeshabilitado(): void
    {
        Mailer::$enviadorPrueba = null;

        $this->assertFalse(Mailer::habilitado());
        $this->assertFalse(Mailer::enviar(['a@test.com'], 'Asunto', 'Cuerpo'));
        $this->assertStringContainsString('deshabilitado', Mailer::ultimoError());
    }

    private function nueva(array $usuario, array $cambios = []): array
    {
        return (new IncidenciaController())->crear($usuario, array_merge([
            'id_contenedor' => 2,
            'tipo' => 'Desborde',
            'descripcion' => 'DESCRIPCION-CON-DATOS-PRIVADOS',
            'prioridad' => 'Media'
        ], $cambios));
    }

    public function testUnDesbordeAvisaPorEmailAAdministradoresYOperarios(): void
    {
        $this->nueva($this->vecino());

        $this->assertCount(1, $this->enviados);
        $this->assertSame(
            ['admin@gmail.com', 'emily@sigeru.com', 'operador@sigeru.com', 'santiago@sigeru.com'],
            $this->emailsDelEnvio(0)
        );
        $this->assertStringContainsString('[SiGeRU]', $this->enviados[0]['asunto']);
        $this->assertStringContainsString('Desborde', $this->enviados[0]['asunto']);
    }

    public function testElEmailNoIncluyeLaDescripcionEscritaPorElUsuario(): void
    {
        $this->nueva($this->vecino());

        $this->assertStringNotContainsString('DESCRIPCION-CON-DATOS-PRIVADOS', $this->enviados[0]['cuerpo']);
    }

    public function testUnaIncidenciaInformativaNoEnviaEmail(): void
    {
        $this->nueva($this->operario(), ['tipo' => 'Rotura', 'prioridad' => 'Baja']);

        $this->assertCount(0, $this->enviados);
    }

    public function testUnaIncidenciaDePrioridadAltaSiEnviaEmail(): void
    {
        $this->nueva($this->operario(), ['tipo' => 'Rotura', 'prioridad' => 'Alta']);

        $this->assertCount(1, $this->enviados);
    }

    public function testUnDesbordeRepetidoEnElMismoContenedorNoRepiteElEmail(): void
    {
        $this->nueva($this->vecino());
        $this->nueva($this->vecino());

        $this->assertCount(1, $this->enviados, 'Solo el primero genera email');
        $this->assertSame(2, (int)$this->valor('SELECT COUNT(*) FROM Notificacion WHERE id_usuario = 6'),
            'Pero el aviso interno se genera en ambos');
    }

    public function testAsignarUnaCuadrillaAvisaPorEmailASusIntegrantes(): void
    {
        (new IncidenciaController())->asignarCuadrilla($this->admin(), 5, 2);

        $this->assertCount(1, $this->enviados);
        $this->assertSame(['operador@sigeru.com', 'santiago@sigeru.com'], $this->emailsDelEnvio(0));
    }

    public function testResolverAvisaPorEmailAQuienReporto(): void
    {
        $c = new IncidenciaController();

        $c->cambiarEstado($this->admin(), 5, 'En curso', null);
        $this->assertCount(0, $this->enviados, 'Pasar a En curso no genera email');

        $c->cambiarEstado($this->admin(), 5, 'Resuelta', null);

        $this->assertCount(1, $this->enviados);
        $this->assertSame(['vecino@sigeru.com'], $this->emailsDelEnvio(0));
    }
}
