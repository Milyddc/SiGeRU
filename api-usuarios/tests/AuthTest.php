<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/config/auth.php';

class AuthTest extends TestCase
{
    public function testCrearYVerificarToken(): void
    {
        $token = jwtCrear(['sub' => 1, 'rol' => 'Administrador']);

        $datos = jwtVerificar($token);

        $this->assertNotNull($datos);
        $this->assertSame(1, $datos['sub']);
        $this->assertSame('Administrador', $datos['rol']);
        $this->assertArrayHasKey('exp', $datos);
        $this->assertGreaterThan(time(), $datos['exp']);
    }

    public function testElTokenTieneTresPartes(): void
    {
        $this->assertCount(3, explode('.', jwtCrear(['sub' => 1])));
    }

    public function testPayloadAlteradoEsRechazado(): void
    {
        $partes = explode('.', jwtCrear(['sub' => 7, 'rol' => 'Vecino']));

        $partes[1] = b64urlEncode(json_encode(['sub' => 7, 'rol' => 'Administrador', 'exp' => time() + 1000]));

        $this->assertNull(jwtVerificar(implode('.', $partes)));
    }

    public function testFirmaAlteradaEsRechazada(): void
    {
        $partes = explode('.', jwtCrear(['sub' => 1]));
        $partes[2] = b64urlEncode('firma-falsa');

        $this->assertNull(jwtVerificar(implode('.', $partes)));
    }

    public function testTokenVencidoEsRechazado(): void
    {
        $token = jwtCrear(['sub' => 1], -10);

        $this->assertNull(jwtVerificar($token));
    }

    public function testAlgoritmoNoneEsRechazado(): void
    {
        $cabecera = b64urlEncode(json_encode(['alg' => 'none', 'typ' => 'JWT']));
        $payload = b64urlEncode(json_encode(['sub' => 1, 'rol' => 'Administrador', 'exp' => time() + 1000]));

        $this->assertNull(jwtVerificar("$cabecera.$payload."));
    }

    public function testTokensMalFormadosSeRechazan(): void
    {
        $this->assertNull(jwtVerificar(''));
        $this->assertNull(jwtVerificar('abc'));
        $this->assertNull(jwtVerificar('a.b.c'));
    }

    public function testBase64Url(): void
    {
        $original = "datos con +/= caracteres raros \xFF\xFE";

        $this->assertSame($original, b64urlDecode(b64urlEncode($original)));
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', b64urlEncode($original));
    }
}
