<?php
/**
 * tests/MaquinariaAvanzadaApiTest.php
 * Nivel avanzado 3.2 — historial de reparaciones/mantenimiento de
 * MAQUINARIA (mantenimiento_maquinaria.php) y traslados entre
 * instalaciones (traslados_maquinaria.php), incluido que el traslado
 * actualice MAQUINARIA.id_centro.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MaquinariaAvanzadaApiTest extends TestCase
{
    private ApiClient $admin;
    private ApiClient $operario;
    private int $idCentroOrigen;
    private int $idCentroDestino;
    private int $idMaquinaria;

    protected function setUp(): void
    {
        $this->admin = new ApiClient();
        $this->operario = new ApiClient();
        $sufijo = (string) random_int(0, 999999);

        $ciAdmin = '8' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $this->admin->post('/api-usuarios/registro.php', [
            'ci' => $ciAdmin, 'contrasena' => 'clave12345',
            'email' => "maqav-admin{$sufijo}@sigeru.uy", 'nombre_completo' => 'Admin Maquinaria Avanzada',
            'tipo_usuario' => 'ADMINISTRADOR',
        ]);
        $this->admin->post('/api-usuarios/login.php', ['ci' => $ciAdmin, 'contrasena' => 'clave12345']);

        $ciOperario = '7' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $this->operario->post('/api-usuarios/registro.php', [
            'ci' => $ciOperario, 'contrasena' => 'clave12345',
            'email' => "maqav-oper{$sufijo}@sigeru.uy", 'nombre_completo' => 'Operario Maquinaria Avanzada',
            'tipo_usuario' => 'OPERARIO', 'cargo' => 'RECOLECTOR',
        ]);
        $this->operario->post('/api-usuarios/login.php', ['ci' => $ciOperario, 'contrasena' => 'clave12345']);

        $origen = $this->admin->post('/api-gestion/instalaciones.php', [
            'nombre' => 'Centro origen ' . random_int(0, 9999), 'coordenadas' => '-34.9,-56.1',
            'capacidad_maxima' => 1000, 'residuos_aceptados' => 'organico', 'tipo_instalacion' => 'CENTRO_ACOPIO',
        ]);
        $this->idCentroOrigen = $origen['cuerpo']['id_centro'];

        $destino = $this->admin->post('/api-gestion/instalaciones.php', [
            'nombre' => 'Centro destino ' . random_int(0, 9999), 'coordenadas' => '-34.8,-56.2',
            'capacidad_maxima' => 1000, 'residuos_aceptados' => 'organico', 'tipo_instalacion' => 'VERTEDERO',
        ]);
        $this->idCentroDestino = $destino['cuerpo']['id_centro'];

        $maquinaria = $this->admin->post('/api-gestion/maquinaria.php', [
            'nombre' => 'Prensa test ' . random_int(0, 9999), 'tipo' => 'Prensa', 'id_centro' => $this->idCentroOrigen,
        ]);
        $this->idMaquinaria = $maquinaria['cuerpo']['id_maquinaria'];
    }

    public function testCicloDeMantenimientoDeMaquinaria(): void
    {
        self::assertSame(403, $this->operario->post('/api-gestion/mantenimiento_maquinaria.php', [
            'id_maquinaria' => $this->idMaquinaria, 'fecha' => '2026-05-01', 'tipo_mantenimiento' => 'Correctivo',
        ])['codigo']);

        $sinTipo = $this->admin->post('/api-gestion/mantenimiento_maquinaria.php', [
            'id_maquinaria' => $this->idMaquinaria, 'fecha' => '2026-05-01',
        ]);
        self::assertSame(400, $sinTipo['codigo']);

        $registro = $this->admin->post('/api-gestion/mantenimiento_maquinaria.php', [
            'id_maquinaria' => $this->idMaquinaria, 'fecha' => '2026-05-01',
            'tipo_mantenimiento' => 'Correctivo', 'descripcion' => 'Cambio de rodamientos', 'costo' => 1500.50,
        ]);
        self::assertSame(201, $registro['codigo'], $registro['cuerpo']['mensaje'] ?? '');

        $listado = $this->admin->get("/api-gestion/mantenimiento_maquinaria.php?id_maquinaria={$this->idMaquinaria}");
        self::assertSame(200, $listado['codigo']);
        self::assertCount(1, $listado['cuerpo']['mantenimientos']);
        self::assertSame('Correctivo', $listado['cuerpo']['mantenimientos'][0]['tipo_mantenimiento']);

        // Registrar el mantenimiento no cambia el estado de la máquina solo.
        $detalle = $this->admin->get("/api-gestion/maquinaria.php?id={$this->idMaquinaria}");
        self::assertSame('OPERATIVA', $detalle['cuerpo']['maquinaria']['estado']);
    }

    public function testTrasladoDeMaquinariaActualizaSuInstalacion(): void
    {
        self::assertSame(403, $this->operario->post('/api-gestion/traslados_maquinaria.php', [
            'id_maquinaria' => $this->idMaquinaria, 'id_centro_destino' => $this->idCentroDestino, 'fecha' => '2026-05-02',
        ])['codigo']);

        $mismaInstalacion = $this->admin->post('/api-gestion/traslados_maquinaria.php', [
            'id_maquinaria' => $this->idMaquinaria, 'id_centro_destino' => $this->idCentroOrigen, 'fecha' => '2026-05-02',
        ]);
        self::assertSame(400, $mismaInstalacion['codigo']);

        $traslado = $this->admin->post('/api-gestion/traslados_maquinaria.php', [
            'id_maquinaria' => $this->idMaquinaria, 'id_centro_destino' => $this->idCentroDestino,
            'fecha' => '2026-05-02', 'observaciones' => 'Se necesita en el otro centro.',
        ]);
        self::assertSame(201, $traslado['codigo'], $traslado['cuerpo']['mensaje'] ?? '');

        $detalle = $this->admin->get("/api-gestion/maquinaria.php?id={$this->idMaquinaria}");
        self::assertSame($this->idCentroDestino, (int) $detalle['cuerpo']['maquinaria']['id_centro']);

        $historial = $this->admin->get("/api-gestion/traslados_maquinaria.php?id_maquinaria={$this->idMaquinaria}");
        self::assertSame(200, $historial['codigo']);
        self::assertCount(1, $historial['cuerpo']['traslados']);
        self::assertSame($this->idCentroOrigen, (int) $historial['cuerpo']['traslados'][0]['id_centro_origen']);
        self::assertSame($this->idCentroDestino, (int) $historial['cuerpo']['traslados'][0]['id_centro_destino']);
    }

    public function testTrasladoAInstalacionInexistenteDevuelve404(): void
    {
        $respuesta = $this->admin->post('/api-gestion/traslados_maquinaria.php', [
            'id_maquinaria' => $this->idMaquinaria, 'id_centro_destino' => 999999, 'fecha' => '2026-05-03',
        ]);
        self::assertSame(404, $respuesta['codigo']);
    }
}
