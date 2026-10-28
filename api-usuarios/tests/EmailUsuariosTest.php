<?php

namespace Tests;

use Mailer;
use UsuarioController;

require_once __DIR__ . '/../src/controllers/UsuarioController.php';

class EmailUsuariosTest extends TestBase
{
    private array $enviados = [];
    private UsuarioController $c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = new UsuarioController();
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

    private function datos(): array
    {
        return ['nombre' => 'Ana', 'apellido' => 'Pérez', 'correo' => 'ana@test.com', 'password' => 'Clave1234'];
    }

    public function testUnRegistroPublicoAvisaPorEmailYEnLaAppALosAdministradores(): void
    {
        $this->c->registrar($this->datos(), false);

        $this->assertCount(1, $this->enviados);

        $emails = $this->enviados[0]['emails'];
        sort($emails);

        $this->assertSame(['admin@gmail.com', 'emily@sigeru.com'], $emails);
        $this->assertStringContainsString('ana@test.com', $this->enviados[0]['cuerpo']);

        foreach ([1, 6] as $idAdmin) {
            $this->assertSame(1, (int)$this->valor(
                "SELECT COUNT(*) FROM Notificacion WHERE id_usuario = ? AND mensaje LIKE 'Nuevo registro%'",
                [$idAdmin]
            ));
        }
    }

    public function testUnRegistroHechoPorUnAdministradorNoAvisa(): void
    {
        $this->c->registrar($this->datos(), true);

        $this->assertCount(0, $this->enviados);
    }

    public function testUnRegistroRechazadoNoAvisa(): void
    {
        $this->c->registrar(array_merge($this->datos(), ['password' => 'x']), false);

        $this->assertCount(0, $this->enviados);
    }

    public function testAprobarUnaCuentaLeAvisaAlUsuarioPorEmail(): void
    {
        $this->c->registrar($this->datos(), false);
        $id = (int)$this->valor('SELECT id_usuario FROM Usuario WHERE email = ?', ['ana@test.com']);

        $this->c->aprobar($id);

        $this->assertCount(2, $this->enviados);
        $this->assertSame(['ana@test.com'], $this->enviados[1]['emails']);
        $this->assertStringContainsString('aprobada', $this->enviados[1]['asunto']);
    }

    public function testAprobarUnUsuarioInexistenteNoEnviaNada(): void
    {
        $this->c->aprobar(9999);

        $this->assertCount(0, $this->enviados);
    }

    public function testUnFalloDelEnvioNoImpideElRegistro(): void
    {
        Mailer::$enviadorPrueba = function () {
            throw new \RuntimeException('Servidor SMTP caído');
        };

        $r = $this->c->registrar($this->datos(), false);

        $this->assertTrue($r['ok']);
        $this->assertSame(1, (int)$this->valor('SELECT COUNT(*) FROM Usuario WHERE email = ?', ['ana@test.com']));
    }
}
