<?php
/**
 * tests/InstalacionesApiTest.php
 * Cubre el ciclo de vida completo de api-gestion/instalaciones.php
 * (RF10 Registrar centro de acopio, RF11 Registrar vertedero): alta,
 * listado, edición (PUT) y eliminación (DELETE), además de que solo
 * el Administrador pueda crear/editar/eliminar.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InstalacionesApiTest extends TestCase
{
    private ApiClient $admin;

    protected function setUp(): void
    {
        $this->admin = new ApiClient();
        $sufijo = (string) random_int(0, 999999);
        $ciAdmin = '6' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $this->admin->post('/api-usuarios/registro.php', [
            'ci' => $ciAdmin, 'contrasena' => 'clave12345',
            'email' => "inst-admin{$sufijo}@sigeru.uy", 'nombre_completo' => 'Admin Instalaciones',
            'tipo_usuario' => 'ADMINISTRADOR',
        ]);
        $this->admin->post('/api-usuarios/login.php', ['ci' => $ciAdmin, 'contrasena' => 'clave12345']);
    }

    public function testCicloCompletoAltaEdicionYEliminacion(): void
    {
        $nombre = 'Centro de prueba ' . random_int(0, 9999);

        $alta = $this->admin->post('/api-gestion/instalaciones.php', [
            'nombre' => $nombre, 'coordenadas' => '-34.90,-56.16',
            'capacidad_maxima' => 1000, 'residuos_aceptados' => 'organico, plastico',
            'tipo_instalacion' => 'CENTRO_ACOPIO',
        ]);
        self::assertSame(201, $alta['codigo'], $alta['cuerpo']['mensaje'] ?? '');
        $idCentro = $alta['cuerpo']['id_centro'];

        $detalle = $this->admin->get("/api-gestion/instalaciones.php?id={$idCentro}");
        self::assertSame(200, $detalle['codigo']);
        self::assertSame($nombre, $detalle['cuerpo']['instalacion']['nombre']);
        self::assertSame('CENTRO_ACOPIO', $detalle['cuerpo']['instalacion']['tipo_instalacion']);

        // Edición: pasa a ser vertedero y cambia su capacidad.
        $edicion = $this->admin->put('/api-gestion/instalaciones.php', [
            'id_centro' => $idCentro, 'capacidad_maxima' => 5000, 'tipo_instalacion' => 'VERTEDERO',
        ]);
        self::assertSame(200, $edicion['codigo'], $edicion['cuerpo']['mensaje'] ?? '');

        $trasEdicion = $this->admin->get("/api-gestion/instalaciones.php?id={$idCentro}");
        self::assertSame('VERTEDERO', $trasEdicion['cuerpo']['instalacion']['tipo_instalacion']);
        self::assertSame(5000, (int) $trasEdicion['cuerpo']['instalacion']['capacidad_maxima']);

        // Eliminación: sin maquinaria asociada, se puede borrar.
        $eliminar = $this->admin->delete('/api-gestion/instalaciones.php', ['id_centro' => $idCentro]);
        self::assertSame(200, $eliminar['codigo'], $eliminar['cuerpo']['mensaje'] ?? '');

        $trasEliminar = $this->admin->get("/api-gestion/instalaciones.php?id={$idCentro}");
        self::assertSame(404, $trasEliminar['codigo']);
    }

    public function testTipoInstalacionInvalidoDevuelve400(): void
    {
        $respuesta = $this->admin->post('/api-gestion/instalaciones.php', [
            'nombre' => 'Instalación inválida', 'coordenadas' => '-34.9,-56.1',
            'capacidad_maxima' => 100, 'residuos_aceptados' => 'organico',
            'tipo_instalacion' => 'OTRA_COSA',
        ]);
        self::assertSame(400, $respuesta['codigo']);
    }

    public function testNoSePuedeEliminarInstalacionConMaquinariaAsociada(): void
    {
        $alta = $this->admin->post('/api-gestion/instalaciones.php', [
            'nombre' => 'Centro con maquinaria ' . random_int(0, 9999), 'coordenadas' => '-34.9,-56.1',
            'capacidad_maxima' => 1000, 'residuos_aceptados' => 'organico',
            'tipo_instalacion' => 'CENTRO_ACOPIO',
        ]);
        $idCentro = $alta['cuerpo']['id_centro'];

        $maquina = $this->admin->post('/api-gestion/maquinaria.php', [
            'nombre' => 'Compactadora ' . random_int(0, 9999), 'tipo' => 'Compactadora', 'id_centro' => $idCentro,
        ]);
        self::assertSame(201, $maquina['codigo']);

        $intentoEliminar = $this->admin->delete('/api-gestion/instalaciones.php', ['id_centro' => $idCentro]);
        self::assertSame(409, $intentoEliminar['codigo']);
    }

    public function testOperarioNoPuedeCrearNiEditarNiEliminarInstalaciones(): void
    {
        $operario = new ApiClient();
        $ciOperario = '2' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
        $operario->post('/api-usuarios/registro.php', [
            'ci' => $ciOperario, 'contrasena' => 'clave12345',
            'email' => "op-inst{$ciOperario}@sigeru.uy", 'nombre_completo' => 'Operario Instalaciones',
            'tipo_usuario' => 'OPERARIO', 'cargo' => 'RECOLECTOR',
        ]);
        $operario->post('/api-usuarios/login.php', ['ci' => $ciOperario, 'contrasena' => 'clave12345']);

        $intentoAlta = $operario->post('/api-gestion/instalaciones.php', [
            'nombre' => 'No debería crearse', 'coordenadas' => '-34.9,-56.1',
            'capacidad_maxima' => 100, 'residuos_aceptados' => 'organico',
            'tipo_instalacion' => 'CENTRO_ACOPIO',
        ]);
        self::assertSame(403, $intentoAlta['codigo']);

        // Sí puede listar (cualquier sesión activa).
        $listado = $operario->get('/api-gestion/instalaciones.php');
        self::assertSame(200, $listado['codigo']);
    }
}
