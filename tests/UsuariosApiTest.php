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
            'cargo' => 'RECOLECTOR',
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
            'cargo' => 'RECOLECTOR',
        ]);

        $baja = $sesionAdmin->delete('/api-usuarios/usuarios.php', ['ci' => $ciBaja]);
        self::assertSame(200, $baja['codigo']);
        self::assertTrue($baja['cuerpo']['exito']);

        $login = $this->api->post('/api-usuarios/login.php', ['ci' => $ciBaja, 'contrasena' => 'clave12345']);
        self::assertSame(403, $login['codigo']);
        self::assertFalse($login['cuerpo']['exito']);
    }

    public function testRegistroDeChoferExigeLicenciaYCarnetDeSalud(): void
    {
        $ci = $this->ciDePrueba();

        // Sin licencia_categoria ni licencia_vencimiento: rechazado.
        $sinLicencia = $this->api->post('/api-usuarios/registro.php', [
            'ci' => $ci,
            'contrasena' => 'clave12345',
            'email' => "chofer{$ci}@sigeru.uy",
            'nombre_completo' => 'Chofer Sin Licencia',
            'tipo_usuario' => 'OPERARIO',
            'cargo' => 'CHOFER',
        ]);
        self::assertSame(400, $sinLicencia['codigo']);
        self::assertFalse($sinLicencia['cuerpo']['exito']);

        // Con fecha de licencia inválida: rechazado.
        $fechaInvalida = $this->api->post('/api-usuarios/registro.php', [
            'ci' => $ci,
            'contrasena' => 'clave12345',
            'email' => "chofer{$ci}@sigeru.uy",
            'nombre_completo' => 'Chofer Fecha Mala',
            'tipo_usuario' => 'OPERARIO',
            'cargo' => 'CHOFER',
            'licencia_categoria' => 'B',
            'licencia_vencimiento' => '2027-02-30',
        ]);
        self::assertSame(400, $fechaInvalida['codigo']);
        self::assertFalse($fechaInvalida['cuerpo']['exito']);

        // Completo y válido, con carnet de salud: aceptado.
        $registro = $this->api->post('/api-usuarios/registro.php', [
            'ci' => $ci,
            'contrasena' => 'clave12345',
            'email' => "chofer{$ci}@sigeru.uy",
            'nombre_completo' => 'Chofer Completo',
            'tipo_usuario' => 'OPERARIO',
            'cargo' => 'CHOFER',
            'licencia_categoria' => 'D',
            'licencia_vencimiento' => '2027-05-01',
            'carnet_salud_vencimiento' => '2026-12-01',
        ]);
        self::assertSame(201, $registro['codigo']);
        self::assertTrue($registro['cuerpo']['exito']);
    }

    public function testRegistroRechazaCarnetDeSaludConFormatoInvalido(): void
    {
        $ci = $this->ciDePrueba();
        $respuesta = $this->api->post('/api-usuarios/registro.php', [
            'ci' => $ci,
            'contrasena' => 'clave12345',
            'email' => "carnet{$ci}@sigeru.uy",
            'nombre_completo' => 'Carnet Malo',
            'tipo_usuario' => 'ADMINISTRADOR',
            'carnet_salud_vencimiento' => '01-12-2026',
        ]);
        self::assertSame(400, $respuesta['codigo']);
        self::assertFalse($respuesta['cuerpo']['exito']);
    }

    public function testRegistroDeRecolectorNoAceptaLicencia(): void
    {
        // Un Recolector no es Chofer: no se le exige licencia y, si no se
        // manda, el alta funciona igual (la licencia queda NULL en OPERARIO).
        $ci = $this->ciDePrueba();
        $registro = $this->api->post('/api-usuarios/registro.php', [
            'ci' => $ci,
            'contrasena' => 'clave12345',
            'email' => "recolector{$ci}@sigeru.uy",
            'nombre_completo' => 'Recolector Sin Licencia',
            'tipo_usuario' => 'OPERARIO',
            'cargo' => 'RECOLECTOR',
        ]);
        self::assertSame(201, $registro['codigo']);
        self::assertTrue($registro['cuerpo']['exito']);
    }

    public function testEdicionDeUsuarioValidaYAplicaLicenciaYCarnetDeSalud(): void
    {
        $ciAdmin = $this->ciDePrueba();
        $this->api->post('/api-usuarios/registro.php', [
            'ci' => $ciAdmin,
            'contrasena' => 'clave12345',
            'email' => "adminlic{$ciAdmin}@sigeru.uy",
            'nombre_completo' => 'Admin Licencias',
            'tipo_usuario' => 'ADMINISTRADOR',
        ]);
        $sesionAdmin = new ApiClient();
        $sesionAdmin->post('/api-usuarios/login.php', ['ci' => $ciAdmin, 'contrasena' => 'clave12345']);

        $ciRecolector = $this->ciDePrueba();
        $this->api->post('/api-usuarios/registro.php', [
            'ci' => $ciRecolector,
            'contrasena' => 'clave12345',
            'email' => "recolic{$ciRecolector}@sigeru.uy",
            'nombre_completo' => 'Recolector Para Editar',
            'tipo_usuario' => 'OPERARIO',
            'cargo' => 'RECOLECTOR',
        ]);

        // No es Chofer: mandar datos de licencia se rechaza.
        $rechazo = $sesionAdmin->put('/api-usuarios/usuarios.php', [
            'ci' => $ciRecolector,
            'licencia_categoria' => 'B',
            'licencia_vencimiento' => '2027-01-01',
        ]);
        self::assertSame(400, $rechazo['codigo']);
        self::assertFalse($rechazo['cuerpo']['exito']);

        // El carnet de salud sí aplica a cualquier usuario.
        $carnet = $sesionAdmin->put('/api-usuarios/usuarios.php', [
            'ci' => $ciRecolector,
            'carnet_salud_vencimiento' => '2026-11-20',
        ]);
        self::assertSame(200, $carnet['codigo']);
        $detalle = $sesionAdmin->get("/api-usuarios/usuarios.php?ci={$ciRecolector}");
        self::assertSame('2026-11-20', $detalle['cuerpo']['usuario']['carnet_salud_vencimiento']);

        // Cambiar el rol a CHOFER y mandar la licencia al mismo tiempo: aceptado y aplicado.
        $aChofer = $sesionAdmin->put('/api-usuarios/usuarios.php', [
            'ci' => $ciRecolector,
            'rol' => 'CHOFER',
            'licencia_categoria' => 'E',
            'licencia_vencimiento' => '2028-03-10',
        ]);
        self::assertSame(200, $aChofer['codigo']);
        $detalleChofer = $sesionAdmin->get("/api-usuarios/usuarios.php?ci={$ciRecolector}");
        self::assertSame('E', $detalleChofer['cuerpo']['usuario']['licencia_categoria']);
        self::assertSame('2028-03-10', $detalleChofer['cuerpo']['usuario']['licencia_vencimiento']);

        // Volver a RECOLECTOR: la licencia se limpia sola (ya no le corresponde).
        $aRecolector = $sesionAdmin->put('/api-usuarios/usuarios.php', [
            'ci' => $ciRecolector,
            'rol' => 'RECOLECTOR',
        ]);
        self::assertSame(200, $aRecolector['codigo']);
        $detalleFinal = $sesionAdmin->get("/api-usuarios/usuarios.php?ci={$ciRecolector}");
        self::assertNull($detalleFinal['cuerpo']['usuario']['licencia_categoria']);
        self::assertNull($detalleFinal['cuerpo']['usuario']['licencia_vencimiento']);
    }
}
