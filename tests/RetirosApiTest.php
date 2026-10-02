<?php
/**
 * tests/RetirosApiTest.php
 * Solicitud pública de retiro de residuos especiales (poda, residuos
 * electrónicos, escombros, muebles, etc. — no van al contenedor común):
 * alta anónima, listado solo para staff, y marcarla como retirada
 * (Administrador o el Chofer de la cuadrilla — el Recolector no puede).
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

    /** Da de alta un Chofer, lo loguea, y arma una cuadrilla estable con él. Devuelve [ApiClient del chofer, id_cuadrilla]. */
    private function crearChoferConCuadrilla(): array
    {
        $sufijo = (string) random_int(0, 999999);
        $ciChofer = '5' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $chofer = new ApiClient();
        $chofer->post('/api-usuarios/registro.php', [
            'ci' => $ciChofer, 'contrasena' => 'clave12345',
            'email' => "ret-cuad-chofer{$sufijo}@sigeru.uy", 'nombre_completo' => 'Chofer Cuadrilla Retiros',
            'tipo_usuario' => 'OPERARIO', 'cargo' => 'CHOFER',
            'licencia_categoria' => 'D', 'licencia_vencimiento' => '2027-01-01',
        ]);
        $chofer->post('/api-usuarios/login.php', ['ci' => $ciChofer, 'contrasena' => 'clave12345']);

        $operarios = $this->admin->get('/api-usuarios/listar_operarios.php?cargo=CHOFER');
        $idOperarioChofer = null;
        foreach ($operarios['cuerpo']['operarios'] as $o) {
            if ($o['ci'] === $ciChofer) {
                $idOperarioChofer = (int) $o['id_operario'];
            }
        }
        self::assertTrue($idOperarioChofer !== null, 'No se encontró el chofer recién creado.');

        $camion = $this->admin->post('/api-gestion/camiones.php', [
            'matricula' => 'RET' . random_int(1000, 9999),
            'marca' => 'Test', 'modelo' => 'Test', 'capacidad_carga' => 1000,
        ]);
        self::assertSame(201, $camion['codigo']);

        $cuadrilla = $this->admin->post('/api-gestion/cuadrillas.php', [
            'nombre_cuadrilla' => 'Cuadrilla retiros ' . random_int(0, 9999),
            'cantidad_integrantes' => 1,
            'id_camion' => $camion['cuerpo']['id_camion'],
            'id_chofer' => $idOperarioChofer,
            'recolectores' => [],
        ]);
        self::assertSame(201, $cuadrilla['codigo'], $cuadrilla['cuerpo']['mensaje'] ?? '');

        return [$chofer, (int) $cuadrilla['cuerpo']['id_cuadrilla']];
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

    public function testRecolectorNoPuedeMarcarRetiradoPeroChoferSi(): void
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

        $ciChofer = '4' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $chofer = new ApiClient();
        $chofer->post('/api-usuarios/registro.php', [
            'ci' => $ciChofer, 'contrasena' => 'clave12345',
            'email' => "ret-chofer{$sufijo}@sigeru.uy", 'nombre_completo' => 'Chofer Retiros',
            'tipo_usuario' => 'OPERARIO', 'cargo' => 'CHOFER',
            'licencia_categoria' => 'D', 'licencia_vencimiento' => '2027-01-01',
        ]);
        $chofer->post('/api-usuarios/login.php', ['ci' => $ciChofer, 'contrasena' => 'clave12345']);

        $idRecolector = $this->solicitarRetiro();
        $rechazado = $recolector->put('/api-retiros/actualizar_estado.php', ['id_solicitud' => $idRecolector]);
        self::assertSame(403, $rechazado['codigo']);
        self::assertFalse($rechazado['cuerpo']['exito']);

        $idChofer = $this->solicitarRetiro();
        $aceptado = $chofer->put('/api-retiros/actualizar_estado.php', ['id_solicitud' => $idChofer]);
        self::assertSame(200, $aceptado['codigo']);
        self::assertTrue($aceptado['cuerpo']['exito']);
    }

    public function testAsignarCuadrillaSoloAdministradorYSoloDesdePendiente(): void
    {
        [$chofer, $idCuadrilla] = $this->crearChoferConCuadrilla();
        $id = $this->solicitarRetiro();

        // El Chofer no puede asignar cuadrillas (es una decisión del Administrador).
        $rechazado = $chofer->put('/api-retiros/asignar_cuadrilla.php', [
            'id_solicitud' => $id, 'id_cuadrilla' => $idCuadrilla,
        ]);
        self::assertSame(403, $rechazado['codigo']);

        $asignado = $this->admin->put('/api-retiros/asignar_cuadrilla.php', [
            'id_solicitud' => $id, 'id_cuadrilla' => $idCuadrilla,
        ]);
        self::assertSame(200, $asignado['codigo'], $asignado['cuerpo']['mensaje'] ?? '');
        self::assertTrue($asignado['cuerpo']['exito']);

        // Ya retirada: no se le puede (re)asignar cuadrilla.
        $this->admin->put('/api-retiros/actualizar_estado.php', ['id_solicitud' => $id]);
        $tardio = $this->admin->put('/api-retiros/asignar_cuadrilla.php', [
            'id_solicitud' => $id, 'id_cuadrilla' => $idCuadrilla,
        ]);
        self::assertSame(409, $tardio['codigo']);
    }

    public function testChoferVeElRetiroAsignadoASuCuadrillaEnListarYLoConfirmaSinPerderLaCuadrilla(): void
    {
        [$chofer, $idCuadrilla] = $this->crearChoferConCuadrilla();
        $id = $this->solicitarRetiro('Residuos eléctricos');

        $this->admin->put('/api-retiros/asignar_cuadrilla.php', [
            'id_solicitud' => $id, 'id_cuadrilla' => $idCuadrilla,
        ]);

        // El filtro por id_cuadrilla (el que usa "Mi día") tiene que devolvérsela.
        $listado = $chofer->get("/api-retiros/listar.php?id_cuadrilla={$idCuadrilla}");
        self::assertSame(200, $listado['codigo']);
        $encontrada = current(array_filter($listado['cuerpo']['solicitudes'], fn($s) => (int) $s['id_solicitud'] === $id));
        self::assertTrue($encontrada !== false);
        self::assertSame('PENDIENTE', $encontrada['estado']);

        // El Chofer la confirma sin mandar id_cuadrilla explícito: no se pierde la asignación.
        $confirmado = $chofer->put('/api-retiros/actualizar_estado.php', ['id_solicitud' => $id]);
        self::assertSame(200, $confirmado['codigo']);

        $detalle = $this->admin->get("/api-retiros/listar.php?id_cuadrilla={$idCuadrilla}");
        $final = current(array_filter($detalle['cuerpo']['solicitudes'], fn($s) => (int) $s['id_solicitud'] === $id));
        self::assertSame('RETIRADO', $final['estado']);
        self::assertSame($idCuadrilla, (int) $final['id_cuadrilla']);
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

    public function testSoloAdministradorPuedeResponderYElEnvioDeEmailEsObligatorio(): void
    {
        [$chofer] = $this->crearChoferConCuadrilla();
        $id = $this->solicitarRetiro();

        // El Chofer no puede dejar la respuesta al vecino (es tarea del Administrador).
        $rechazado = $chofer->put('/api-retiros/responder.php', [
            'id_solicitud' => $id, 'respuesta' => 'Coordinamos el jueves 10hs.',
        ]);
        self::assertSame(403, $rechazado['codigo']);

        $vacia = $this->admin->put('/api-retiros/responder.php', ['id_solicitud' => $id, 'respuesta' => '']);
        self::assertSame(400, $vacia['codigo']);

        // En el entorno de tests no hay SMTP configurado (SIGERU_SMTP_*), así
        // que responder.php debe fallar de forma clara en vez de guardar una
        // respuesta que en realidad nunca se le llegó a mandar al vecino.
        $sinSmtp = $this->admin->put('/api-retiros/responder.php', [
            'id_solicitud' => $id, 'respuesta' => 'Coordinamos el jueves 10hs.',
        ]);
        self::assertSame(502, $sinSmtp['codigo']);
        self::assertFalse($sinSmtp['cuerpo']['exito']);
        self::assertStringContainsString('no está configurado', $sinSmtp['cuerpo']['mensaje']);

        // Como el envío falló, no debe haber quedado nada guardado.
        $listado = $this->admin->get('/api-retiros/listar.php?estado=PENDIENTE');
        $encontrada = current(array_filter($listado['cuerpo']['solicitudes'], fn($s) => (int) $s['id_solicitud'] === $id));
        self::assertTrue(empty($encontrada['respuesta_admin']));
        self::assertTrue(empty($encontrada['fecha_respuesta']));
    }
}
