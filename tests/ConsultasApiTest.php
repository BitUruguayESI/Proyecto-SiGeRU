<?php
/**
 * tests/ConsultasApiTest.php
 * Consulta pública (RN21, formulario "Realizar una consulta" de la
 * Landing Page): alta anónima, listado y marcado como respondida —
 * ambos últimos solo para staff / Administrador respectivamente.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ConsultasApiTest extends TestCase
{
    private ApiClient $anonimo;
    private ApiClient $admin;

    protected function setUp(): void
    {
        $this->anonimo = new ApiClient();
        $this->admin = new ApiClient();

        $sufijo = (string) random_int(0, 999999);
        $ciAdmin = '8' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $this->admin->post('/api-usuarios/registro.php', [
            'ci' => $ciAdmin, 'contrasena' => 'clave12345',
            'email' => "con-admin{$sufijo}@sigeru.uy", 'nombre_completo' => 'Admin Consultas',
            'tipo_usuario' => 'ADMINISTRADOR',
        ]);
        $this->admin->post('/api-usuarios/login.php', ['ci' => $ciAdmin, 'contrasena' => 'clave12345']);
    }

    private function enviarConsulta(string $mensaje = 'Mensaje de prueba automatizada.'): int
    {
        $respuesta = $this->anonimo->post('/api-gestion/registrar_consulta.php', [
            'nombre' => 'Vecino de prueba',
            'email' => 'vecino-consulta-test@correo.com',
            'mensaje' => $mensaje,
        ]);
        self::assertSame(201, $respuesta['codigo']);
        return $respuesta['cuerpo']['id_consulta'] ?? $this->buscarUltimoId($mensaje);
    }

    /** registrar_consulta.php no devuelve el id (solo un mensaje de éxito), así que lo buscamos en el listado. */
    private function buscarUltimoId(string $mensaje): int
    {
        $listado = $this->admin->get('/api-gestion/listar_consultas.php');
        foreach ($listado['cuerpo']['consultas'] as $c) {
            if ($c['mensaje'] === $mensaje) {
                return (int) $c['id_consulta'];
            }
        }
        self::fail('No se encontró la consulta recién creada en el listado.');
    }

    public function testConsultaPublicaQuedaPendienteYListaSinCuenta(): void
    {
        $mensaje = 'Consulta pendiente ' . random_int(0, 999999);
        $this->enviarConsulta($mensaje);

        $listado = $this->admin->get('/api-gestion/listar_consultas.php?estado=PENDIENTE');
        self::assertSame(200, $listado['codigo']);
        $encontrada = array_filter($listado['cuerpo']['consultas'], fn($c) => $c['mensaje'] === $mensaje);
        self::assertNotEmpty($encontrada);
    }

    public function testFaltanDatosObligatoriosDaError(): void
    {
        $respuesta = $this->anonimo->post('/api-gestion/registrar_consulta.php', [
            'nombre' => '', 'email' => 'no-es-un-email', 'mensaje' => '',
        ]);
        self::assertSame(400, $respuesta['codigo']);
        self::assertFalse($respuesta['cuerpo']['exito']);
    }

    public function testListarRequiereSesion(): void
    {
        $respuesta = $this->anonimo->get('/api-gestion/listar_consultas.php');
        self::assertSame(401, $respuesta['codigo']);
    }

    public function testResponderSoloAdministradorYElEnvioDeEmailEsObligatorio(): void
    {
        $sufijo = (string) random_int(0, 999999);
        $ciOperario = '6' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $operario = new ApiClient();
        $operario->post('/api-usuarios/registro.php', [
            'ci' => $ciOperario, 'contrasena' => 'clave12345',
            'email' => "con-operario{$sufijo}@sigeru.uy", 'nombre_completo' => 'Operario Consultas',
            'tipo_usuario' => 'OPERARIO', 'cargo' => 'RECOLECTOR',
        ]);
        $operario->post('/api-usuarios/login.php', ['ci' => $ciOperario, 'contrasena' => 'clave12345']);

        $mensaje = 'Consulta a responder ' . random_int(0, 999999);
        $id = $this->enviarConsulta($mensaje);

        // Un Operario no puede responderla (es tarea del Administrador).
        $rechazado = $operario->put('/api-gestion/responder_consulta.php', [
            'id_consulta' => $id, 'respuesta' => 'Gracias por escribirnos.',
        ]);
        self::assertSame(403, $rechazado['codigo']);

        $vacia = $this->admin->put('/api-gestion/responder_consulta.php', ['id_consulta' => $id, 'respuesta' => '']);
        self::assertSame(400, $vacia['codigo']);

        // En el entorno de tests no hay SMTP configurado (SIGERU_SMTP_*), así
        // que responder_consulta.php debe fallar de forma clara en vez de
        // marcarla como respondida sin haberle mandado nada al vecino.
        $sinSmtp = $this->admin->put('/api-gestion/responder_consulta.php', [
            'id_consulta' => $id, 'respuesta' => 'Gracias por escribirnos.',
        ]);
        self::assertSame(502, $sinSmtp['codigo']);
        self::assertFalse($sinSmtp['cuerpo']['exito']);
        self::assertStringContainsString('no está configurado', $sinSmtp['cuerpo']['mensaje']);

        // Como el envío falló, la consulta debe seguir pendiente.
        $listado = $this->admin->get('/api-gestion/listar_consultas.php?estado=PENDIENTE');
        $encontrada = current(array_filter($listado['cuerpo']['consultas'], fn($c) => (int) $c['id_consulta'] === $id));
        self::assertTrue($encontrada !== false);
        self::assertTrue(empty($encontrada['respuesta_admin']));
    }
}
