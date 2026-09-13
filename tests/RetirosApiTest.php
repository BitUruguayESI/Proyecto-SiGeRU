<?php
/**
 * tests/RetirosApiTest.php
 * Solicitud pública de retiro de residuos especiales (poda, residuos
 * electrónicos, escombros, muebles, etc. — no van al contenedor común):
 * alta anónima, listado solo para staff, y marcarla como retirada
 * (solo Administrador).
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RetirosApiTest extends TestCase
{
    private ApiClient $anonimo;
    private ApiClient $admin;

    protected function setUp(): void
    {
        $this->anonimo = new ApiClient();
        $this->admin = new ApiClient();

        $sufijo = (string) random_int(0, 999999);
        $ciAdmin = '7' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $this->admin->post('/api-usuarios/registro.php', [
            'ci' => $ciAdmin, 'contrasena' => 'clave12345',
            'email' => "ret-admin{$sufijo}@sigeru.uy", 'nombre_completo' => 'Admin Retiros',
            'tipo_usuario' => 'ADMINISTRADOR',
        ]);
        $this->admin->post('/api-usuarios/login.php', ['ci' => $ciAdmin, 'contrasena' => 'clave12345']);
    }

    private function solicitarRetiro(string $tipo = 'Poda'): int
    {
        $solicitud = $this->anonimo->post('/api-retiros/registrar.php', [
            'tipo_residuo' => $tipo,
            'direccion' => 'Bulevar Artigas 1234, Montevideo',
            'descripcion' => 'Solicitud de prueba automatizada.',
            'email_vecino' => 'vecino-retiro-test@correo.com',
        ]);
        self::assertSame(201, $solicitud['codigo'], 'La solicitud pública debería aceptarse sin sesión.');
        return $solicitud['cuerpo']['id_solicitud'];
    }

    public function testSolicitudPublicaQuedaPendiente(): void
    {
        $id = $this->solicitarRetiro();

        $listado = $this->admin->get('/api-retiros/listar.php?estado=PENDIENTE');
        self::assertSame(200, $listado['codigo']);
        $encontrada = array_filter($listado['cuerpo']['solicitudes'], fn($s) => (int) $s['id_solicitud'] === $id);
        self::assertNotEmpty($encontrada);
    }

    public function testFaltanDatosObligatoriosDaError(): void
    {
        $respuesta = $this->anonimo->post('/api-retiros/registrar.php', [
            'tipo_residuo' => '', 'direccion' => '', 'email_vecino' => 'no-es-un-email',
        ]);
        self::assertSame(400, $respuesta['codigo']);
        self::assertFalse($respuesta['cuerpo']['exito']);
    }

    public function testListarRequiereSesion(): void
    {
        $respuesta = $this->anonimo->get('/api-retiros/listar.php');
        self::assertSame(401, $respuesta['codigo']);
    }

    public function testSoloAdministradorPuedeMarcarRetirado(): void
    {
        $sufijo = (string) random_int(0, 999999);
        $ciRecolector = '3' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $recolector = new ApiClient();
        $recolector->post('/api-usuarios/registro.php', [
            'ci' => $ciRecolector, 'contrasena' => 'clave12345',
            'email' => "ret-recolector{$sufijo}@sigeru.uy", 'nombre_completo' => 'Recolector Retiros',
            'tipo_usuario' => 'OPERARIO', 'cargo' => 'RECOLECTOR',
        ]);
        $recolector->post('/api-usuarios/login.php', ['ci' => $ciRecolector, 'contrasena' => 'clave12345']);

        $id = $this->solicitarRetiro();

        $rechazado = $recolector->put('/api-retiros/actualizar_estado.php', ['id_solicitud' => $id]);
        self::assertSame(403, $rechazado['codigo']);
        self::assertFalse($rechazado['cuerpo']['exito']);
    }

    public function testMarcarRetiradoQuedaRegistradoYNoSePuedeRepetir(): void
    {
        $id = $this->solicitarRetiro();

        $marcado = $this->admin->put('/api-retiros/actualizar_estado.php', ['id_solicitud' => $id]);
        self::assertSame(200, $marcado['codigo']);
        self::assertTrue($marcado['cuerpo']['exito']);

        $listado = $this->admin->get('/api-retiros/listar.php?estado=RETIRADO');
        $encontrada = current(array_filter($listado['cuerpo']['solicitudes'], fn($s) => (int) $s['id_solicitud'] === $id));
        self::assertTrue($encontrada !== false);
        self::assertTrue(!empty($encontrada['fecha_retiro']));

        $repetido = $this->admin->put('/api-retiros/actualizar_estado.php', ['id_solicitud' => $id]);
        self::assertSame(409, $repetido['codigo']);
        self::assertFalse($repetido['cuerpo']['exito']);
    }
}
