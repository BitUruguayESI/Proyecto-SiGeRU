<?php
/**
 * tests/UsuariosApiTest.php
 * Cubre api-usuarios/registro.php y login.php: alta con y sin
 * teléfonos, rechazo de CI/email duplicado, login correcto e
 * incorrecto, y que un usuario dado de baja no pueda loguearse.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UsuariosApiTest extends TestCase
{
    private ApiClient $api;

    protected function setUp(): void
    {
        $this->api = new ApiClient();
    }

    private function ciDePrueba(): string
    {
        // CI de 8 dígitos, distinta en cada corrida para no chocar con
        // datos que haya dejado una corrida anterior de los tests.
        return '9' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    }

    public function testRegistroYLoginDeOperarioConTelefonos(): void
    {
        $ci = $this->ciDePrueba();

        $registro = $this->api->post('/api-usuarios/registro.php', [
            'ci' => $ci,
            'contrasena' => 'clave12345',
            'email' => "test{$ci}@sigeru.uy",
            'nombre_completo' => 'Test Operario',
            'tipo_usuario' => 'OPERARIO',
            'cargo' => 'Recolector',
            'telefonos' => ['099111222', '26005555'],
        ]);
        self::assertSame(201, $registro['codigo']);
        self::assertTrue($registro['cuerpo']['exito']);

        $login = $this->api->post('/api-usuarios/login.php', [
            'ci' => $ci,
            'contrasena' => 'clave12345',
        ]);
        self::assertSame(200, $login['codigo']);
        self::assertTrue($login['cuerpo']['exito']);
        self::assertSame('OPERARIO', $login['cuerpo']['tipo_usuario']);
        self::assertSame('Test Operario', $login['cuerpo']['nombre_completo']);
    }

    public function testRegistroRechazaCiOEmailDuplicado(): void
    {
        $ci = $this->ciDePrueba();
        $datos = [
            'ci' => $ci,
            'contrasena' => 'clave12345',
            'email' => "dup{$ci}@sigeru.uy",
            'nombre_completo' => 'Duplicado Uno',
            'tipo_usuario' => 'ADMINISTRADOR',
        ];

        $primero = $this->api->post('/api-usuarios/registro.php', $datos);
        self::assertSame(201, $primero['codigo']);

        $segundo = $this->api->post('/api-usuarios/registro.php', $datos);
        self::assertSame(409, $segundo['codigo']);
        self::assertFalse($segundo['cuerpo']['exito']);
    }

    public function testRegistroValidaCargoObligatorioParaOperario(): void
    {
        $ci = $this->ciDePrueba();
        $respuesta = $this->api->post('/api-usuarios/registro.php', [
            'ci' => $ci,
            'contrasena' => 'clave12345',
            'email' => "sincargo{$ci}@sigeru.uy",
            'nombre_completo' => 'Sin Cargo',
            'tipo_usuario' => 'OPERARIO',
            // sin "cargo" a propósito
        ]);
        self::assertSame(400, $respuesta['codigo']);
        self::assertFalse($respuesta['cuerpo']['exito']);
    }

    public function testLoginConContrasenaIncorrectaEsRechazado(): void
    {
        $ci = $this->ciDePrueba();
        $this->api->post('/api-usuarios/registro.php', [
            'ci' => $ci,
            'contrasena' => 'clave12345',
            'email' => "malaclave{$ci}@sigeru.uy",
            'nombre_completo' => 'Test Clave',
            'tipo_usuario' => 'ADMINISTRADOR',
        ]);

        $login = $this->api->post('/api-usuarios/login.php', [
            'ci' => $ci,
            'contrasena' => 'clave-equivocada',
        ]);
        self::assertSame(401, $login['codigo']);
        self::assertFalse($login['cuerpo']['exito']);
    }

    public function testUsuarioDadoDeBajaNoPuedeLoguearse(): void
    {
        $ciAdmin = $this->ciDePrueba();
        $this->api->post('/api-usuarios/registro.php', [
            'ci' => $ciAdmin,
            'contrasena' => 'clave12345',
            'email' => "admin{$ciAdmin}@sigeru.uy",
            'nombre_completo' => 'Admin Baja',
            'tipo_usuario' => 'ADMINISTRADOR',
        ]);
        $sesionAdmin = new ApiClient();
        $sesionAdmin->post('/api-usuarios/login.php', ['ci' => $ciAdmin, 'contrasena' => 'clave12345']);

        $ciBaja = $this->ciDePrueba();
        $this->api->post('/api-usuarios/registro.php', [
            'ci' => $ciBaja,
            'contrasena' => 'clave12345',
            'email' => "baja{$ciBaja}@sigeru.uy",
            'nombre_completo' => 'Para Dar De Baja',
            'tipo_usuario' => 'OPERARIO',
            'cargo' => 'Recolector',
        ]);

        $baja = $sesionAdmin->delete('/api-usuarios/usuarios.php', ['ci' => $ciBaja]);
        self::assertSame(200, $baja['codigo']);
        self::assertTrue($baja['cuerpo']['exito']);

        $login = $this->api->post('/api-usuarios/login.php', ['ci' => $ciBaja, 'contrasena' => 'clave12345']);
        self::assertSame(403, $login['codigo']);
        self::assertFalse($login['cuerpo']['exito']);
    }
}
