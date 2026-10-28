<?php

namespace Tests;

use UsuarioController;

require_once __DIR__ . '/../src/controllers/UsuarioController.php';

class UsuarioControllerTest extends TestBase
{
    private UsuarioController $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = new UsuarioController();
    }

    private function datosRegistro(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Ana',
            'apellido' => 'Pérez',
            'correo' => 'ana@test.com',
            'password' => 'Clave1234',
            'rol' => 1
        ], $cambios);
    }

    public function testRegistroPublicoCreaVecinoPendiente(): void
    {
        $r = $this->c->registrar($this->datosRegistro(), false);

        $this->assertTrue($r['ok']);

        $u = $this->fila('SELECT estado, id_rol, nombre_completo FROM Usuario WHERE email = ?', ['ana@test.com']);

        $this->assertSame('Pendiente', $u['estado']);
        $this->assertSame(4, (int)$u['id_rol'], 'El registro público ignora el rol pedido y crea un Vecino');
        $this->assertSame('Ana Pérez', $u['nombre_completo']);
    }

    public function testLaContrasenaSeGuardaHasheada(): void
    {
        $this->c->registrar($this->datosRegistro(), false);

        $hash = $this->valor('SELECT password FROM Usuario WHERE email = ?', ['ana@test.com']);

        $this->assertNotSame('Clave1234', $hash);
        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertTrue(password_verify('Clave1234', $hash));
    }

    public function testRegistroComoAdministradorPermiteElegirRolYQuedaActivo(): void
    {
        $r = $this->c->registrar($this->datosRegistro(['rol' => 2]), true);

        $this->assertTrue($r['ok']);

        $u = $this->fila('SELECT estado, id_rol FROM Usuario WHERE email = ?', ['ana@test.com']);

        $this->assertSame('Activo', $u['estado']);
        $this->assertSame(2, (int)$u['id_rol']);
    }

    public function testContrasenaCortaEsRechazada(): void
    {
        $r = $this->c->registrar($this->datosRegistro(['password' => 'a1']), false);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('contraseña', $r['mensaje']);
    }

    public function testContrasenaSinNumerosEsRechazada(): void
    {
        $r = $this->c->registrar($this->datosRegistro(['password' => 'soloLetrasAqui']), false);

        $this->assertFalse($r['ok']);
    }

    public function testContrasenaSinLetrasEsRechazada(): void
    {
        $r = $this->c->registrar($this->datosRegistro(['password' => '123456789']), false);

        $this->assertFalse($r['ok']);
    }

    public function testCorreoInvalidoEsRechazado(): void
    {
        $r = $this->c->registrar($this->datosRegistro(['correo' => 'no-es-un-correo']), false);

        $this->assertFalse($r['ok']);
    }

    public function testCorreoDuplicadoEsRechazado(): void
    {
        $r = $this->c->registrar($this->datosRegistro(['correo' => 'admin@gmail.com']), false);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('ya está registrado', $r['mensaje']);
    }

    public function testNombreVacioEsRechazado(): void
    {
        $r = $this->c->registrar($this->datosRegistro(['nombre' => '  ']), false);

        $this->assertFalse($r['ok']);
    }

    public function testListarUsuariosNoExponeContrasenas(): void
    {
        $usuarios = $this->c->listarUsuarios();

        $this->assertCount(7, $usuarios);

        foreach ($usuarios as $u) {
            $this->assertArrayNotHasKey('password', $u);
            $this->assertArrayHasKey('email', $u);
        }
    }

    public function testLoginCorrectoDevuelveTokenYUsuario(): void
    {
        $r = $this->c->login(['correo' => 'admin@gmail.com', 'password' => 'Sigeru2026!']);

        $this->assertTrue($r['ok']);
        $this->assertNotEmpty($r['token']);
        $this->assertSame('Administrador', $r['usuario']['rol']);
        $this->assertArrayNotHasKey('password', $r['usuario']);

        $claims = jwtVerificar($r['token']);

        $this->assertSame(6, $claims['sub']);
        $this->assertSame('Administrador', $claims['rol']);
    }

    public function testLoginConContrasenaIncorrecta(): void
    {
        $r = $this->c->login(['correo' => 'admin@gmail.com', 'password' => 'incorrecta1']);

        $this->assertFalse($r['ok']);
        $this->assertArrayNotHasKey('token', $r);
    }

    public function testLoginNoRevelaSiElCorreoExiste(): void
    {
        $conCorreoInexistente = $this->c->login(['correo' => 'nadie@test.com', 'password' => 'Sigeru2026!']);
        $conPasswordMala = $this->c->login(['correo' => 'admin@gmail.com', 'password' => 'mala']);

        $this->assertSame($conPasswordMala['mensaje'], $conCorreoInexistente['mensaje']);
    }

    public function testCuentaPendienteNoPuedeIngresar(): void
    {
        $this->c->registrar($this->datosRegistro(), false);

        $r = $this->c->login(['correo' => 'ana@test.com', 'password' => 'Clave1234']);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('pendiente', $r['mensaje']);
    }

    public function testAprobarUsuarioPermiteIngresar(): void
    {
        $this->c->registrar($this->datosRegistro(), false);
        $id = (int)$this->valor('SELECT id_usuario FROM Usuario WHERE email = ?', ['ana@test.com']);

        $aprobado = $this->c->aprobar($id);
        $login = $this->c->login(['correo' => 'ana@test.com', 'password' => 'Clave1234']);

        $this->assertTrue($aprobado['ok']);
        $this->assertTrue($login['ok']);
    }

    public function testAprobarUsuarioInexistente(): void
    {
        $this->assertFalse($this->c->aprobar(9999)['ok']);
    }

    public function testCuentaInactivaNoPuedeIngresar(): void
    {
        $this->c->actualizar([
            'id_usuario' => 7, 'nombre' => 'Vecino', 'apellido' => 'Prueba',
            'correo' => 'vecino@sigeru.com', 'rol' => 4, 'estado' => 'Inactivo'
        ]);

        $r = $this->c->login(['correo' => 'vecino@sigeru.com', 'password' => 'Sigeru2026!']);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('inactiva', $r['mensaje']);
    }

    public function testBloqueoTrasCincoIntentosFallidos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->c->login(['correo' => 'vecino@sigeru.com', 'password' => 'mala-' . $i]);
        }

        $r = $this->c->login(['correo' => 'vecino@sigeru.com', 'password' => 'Sigeru2026!']);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('bloqueada', $r['mensaje']);
    }

    public function testLoginExitosoReiniciaLosIntentosFallidos(): void
    {
        $this->c->login(['correo' => 'vecino@sigeru.com', 'password' => 'mala1']);
        $this->c->login(['correo' => 'vecino@sigeru.com', 'password' => 'mala2']);

        $this->assertSame(2, (int)$this->valor('SELECT intentos_fallidos FROM Usuario WHERE id_usuario = 7'));

        $r = $this->c->login(['correo' => 'vecino@sigeru.com', 'password' => 'Sigeru2026!']);

        $this->assertTrue($r['ok']);
        $this->assertSame(0, (int)$this->valor('SELECT intentos_fallidos FROM Usuario WHERE id_usuario = 7'));
    }

    public function testActualizarCambiaLosDatosYHasheaLaContrasena(): void
    {
        $r = $this->c->actualizar([
            'id_usuario' => 7, 'nombre' => 'Vera', 'apellido' => 'Gómez',
            'correo' => 'vera@test.com', 'rol' => 4, 'password' => 'NuevaClave99'
        ]);

        $this->assertTrue($r['ok']);

        $u = $this->fila('SELECT nombre_completo, email, password FROM Usuario WHERE id_usuario = 7');

        $this->assertSame('Vera Gómez', $u['nombre_completo']);
        $this->assertSame('vera@test.com', $u['email']);
        $this->assertTrue(password_verify('NuevaClave99', $u['password']));
    }

    public function testActualizarSinContrasenaNoLaModifica(): void
    {
        $antes = $this->valor('SELECT password FROM Usuario WHERE id_usuario = 7');

        $this->c->actualizar([
            'id_usuario' => 7, 'nombre' => 'Vera', 'apellido' => 'Gómez',
            'correo' => 'vera@test.com', 'rol' => 4
        ]);

        $this->assertSame($antes, $this->valor('SELECT password FROM Usuario WHERE id_usuario = 7'));
    }

    public function testActualizarConContrasenaDebilEsRechazado(): void
    {
        $r = $this->c->actualizar([
            'id_usuario' => 7, 'nombre' => 'Vera', 'apellido' => 'Gómez',
            'correo' => 'vera@test.com', 'rol' => 4, 'password' => 'abc'
        ]);

        $this->assertFalse($r['ok']);
    }

    public function testActualizarConCorreoDeOtroUsuarioFalla(): void
    {
        $r = $this->c->actualizar([
            'id_usuario' => 7, 'nombre' => 'Vecino', 'apellido' => 'Prueba',
            'correo' => 'admin@gmail.com', 'rol' => 4
        ]);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('ya está registrado', $r['mensaje']);
    }

    public function testEliminarUsuarioSinRelaciones(): void
    {
        $this->c->registrar($this->datosRegistro(), false);
        $id = (int)$this->valor('SELECT id_usuario FROM Usuario WHERE email = ?', ['ana@test.com']);

        $r = $this->c->eliminar($id);

        $this->assertTrue($r['ok']);
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM Usuario WHERE id_usuario = ?', [$id]));
    }

    public function testEliminarUsuarioConIncidenciasDevuelveMensajeClaro(): void
    {
        $r = $this->c->eliminar(3);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('No se puede eliminar', $r['mensaje']);
        $this->assertSame(1, (int)$this->valor('SELECT COUNT(*) FROM Usuario WHERE id_usuario = 3'));
    }

    public function testEliminarUsuarioInexistente(): void
    {
        $r = $this->c->eliminar(9999);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('No se encontró', $r['mensaje']);
    }
}
