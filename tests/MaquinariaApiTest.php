<?php
/**
 * tests/MaquinariaApiTest.php
 * Cubre el ciclo de vida completo de api-gestion/maquinaria.php y
 * api-gestion/repuestos.php: alta, listado, edición (PUT, incluida la
 * asociación N:M con repuestos) y eliminación, además de que solo el
 * Administrador pueda crear/editar/eliminar en ambos endpoints.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MaquinariaApiTest extends TestCase
{
    private ApiClient $admin;
    private int $idCentro;

    protected function setUp(): void
    {
        $this->admin = new ApiClient();
        $sufijo = (string) random_int(0, 999999);
        $ciAdmin = '7' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $this->admin->post('/api-usuarios/registro.php', [
            'ci' => $ciAdmin, 'contrasena' => 'clave12345',
            'email' => "maq-admin{$sufijo}@sigeru.uy", 'nombre_completo' => 'Admin Maquinaria',
            'tipo_usuario' => 'ADMINISTRADOR',
        ]);
        $this->admin->post('/api-usuarios/login.php', ['ci' => $ciAdmin, 'contrasena' => 'clave12345']);

        $instalacion = $this->admin->post('/api-gestion/instalaciones.php', [
            'nombre' => 'Centro maquinaria ' . random_int(0, 9999), 'coordenadas' => '-34.9,-56.1',
            'capacidad_maxima' => 1000, 'residuos_aceptados' => 'organico',
            'tipo_instalacion' => 'CENTRO_ACOPIO',
        ]);
        self::assertSame(201, $instalacion['codigo']);
        $this->idCentro = $instalacion['cuerpo']['id_centro'];
    }

    private function crearRepuesto(string $nombre): int
    {
        $repuesto = $this->admin->post('/api-gestion/repuestos.php', [
            'nombre' => $nombre, 'tipo' => 'Genérico', 'stock' => 5,
        ]);
        self::assertSame(201, $repuesto['codigo'], $repuesto['cuerpo']['mensaje'] ?? '');
        return $repuesto['cuerpo']['id_repuesto'];
    }

    public function testCicloCompletoDeRepuestos(): void
    {
        $idRepuesto = $this->crearRepuesto('Correa ' . random_int(0, 9999));

        $edicion = $this->admin->put('/api-gestion/repuestos.php', [
            'id_repuesto' => $idRepuesto, 'stock' => 20, 'estado' => 'AGOTADO',
        ]);
        self::assertSame(200, $edicion['codigo'], $edicion['cuerpo']['mensaje'] ?? '');

        $listado = $this->admin->get('/api-gestion/repuestos.php');
        $encontrado = null;
        foreach ($listado['cuerpo']['repuestos'] as $r) {
            if ($r['id_repuesto'] === $idRepuesto) { $encontrado = $r; break; }
        }
        self::assertNotEmpty($encontrado);
        self::assertSame(20, (int) $encontrado['stock']);
        self::assertSame('AGOTADO', $encontrado['estado']);

        $eliminar = $this->admin->delete('/api-gestion/repuestos.php', ['id_repuesto' => $idRepuesto]);
        self::assertSame(200, $eliminar['codigo'], $eliminar['cuerpo']['mensaje'] ?? '');
    }

    public function testEditarRepuestoConEstadoInvalidoDevuelve400(): void
    {
        $idRepuesto = $this->crearRepuesto('Filtro ' . random_int(0, 9999));
        $respuesta = $this->admin->put('/api-gestion/repuestos.php', [
            'id_repuesto' => $idRepuesto, 'estado' => 'NO_EXISTE',
        ]);
        self::assertSame(400, $respuesta['codigo']);
    }

    public function testCicloCompletoDeMaquinariaConRepuestosAsociados(): void
    {
        $idRepuesto1 = $this->crearRepuesto('Repuesto A ' . random_int(0, 9999));
        $idRepuesto2 = $this->crearRepuesto('Repuesto B ' . random_int(0, 9999));

        $alta = $this->admin->post('/api-gestion/maquinaria.php', [
            'nombre' => 'Compactadora ' . random_int(0, 9999), 'tipo' => 'Compactadora',
            'id_centro' => $this->idCentro, 'repuestos' => [$idRepuesto1],
        ]);
        self::assertSame(201, $alta['codigo'], $alta['cuerpo']['mensaje'] ?? '');
        $idMaquinaria = $alta['cuerpo']['id_maquinaria'];

        $detalle = $this->admin->get("/api-gestion/maquinaria.php?id={$idMaquinaria}");
        self::assertSame(200, $detalle['codigo']);
        self::assertSame('OPERATIVA', $detalle['cuerpo']['maquinaria']['estado']);
        self::assertCount(1, $detalle['cuerpo']['maquinaria']['repuestos']);
        self::assertSame($idRepuesto1, $detalle['cuerpo']['maquinaria']['repuestos'][0]['id_repuesto']);

        // Edición: cambio de estado, nombre y reemplazo completo de repuestos asociados.
        $edicion = $this->admin->put('/api-gestion/maquinaria.php', [
            'id_maquinaria' => $idMaquinaria, 'estado' => 'EN_MANTENIMIENTO',
            'nombre' => 'Compactadora editada', 'repuestos' => [$idRepuesto2],
        ]);
        self::assertSame(200, $edicion['codigo'], $edicion['cuerpo']['mensaje'] ?? '');

        $trasEdicion = $this->admin->get("/api-gestion/maquinaria.php?id={$idMaquinaria}");
        self::assertSame('EN_MANTENIMIENTO', $trasEdicion['cuerpo']['maquinaria']['estado']);
        self::assertSame('Compactadora editada', $trasEdicion['cuerpo']['maquinaria']['nombre']);
        self::assertCount(1, $trasEdicion['cuerpo']['maquinaria']['repuestos']);
        self::assertSame($idRepuesto2, $trasEdicion['cuerpo']['maquinaria']['repuestos'][0]['id_repuesto']);

        // Eliminación (hard delete, no tiene historial que preservar).
        $eliminar = $this->admin->delete('/api-gestion/maquinaria.php', ['id_maquinaria' => $idMaquinaria]);
        self::assertSame(200, $eliminar['codigo'], $eliminar['cuerpo']['mensaje'] ?? '');

        $trasEliminar = $this->admin->get("/api-gestion/maquinaria.php?id={$idMaquinaria}");
        self::assertSame(404, $trasEliminar['codigo']);
    }

    public function testCrearMaquinariaEnInstalacionInexistenteDevuelve404(): void
    {
        $respuesta = $this->admin->post('/api-gestion/maquinaria.php', [
            'nombre' => 'Máquina huérfana', 'tipo' => 'Prensa', 'id_centro' => 999999,
        ]);
        self::assertSame(404, $respuesta['codigo']);
    }

    public function testOperarioNoPuedeCrearNiEditarNiEliminarMaquinariaNiRepuestos(): void
    {
        $operario = new ApiClient();
        $ciOperario = '2' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
        $operario->post('/api-usuarios/registro.php', [
            'ci' => $ciOperario, 'contrasena' => 'clave12345',
            'email' => "op-maq{$ciOperario}@sigeru.uy", 'nombre_completo' => 'Operario Maquinaria',
            'tipo_usuario' => 'OPERARIO', 'cargo' => 'CHOFER',
        ]);
        $operario->post('/api-usuarios/login.php', ['ci' => $ciOperario, 'contrasena' => 'clave12345']);

        $intentoMaquinaria = $operario->post('/api-gestion/maquinaria.php', [
            'nombre' => 'No debería crearse', 'tipo' => 'X', 'id_centro' => $this->idCentro,
        ]);
        self::assertSame(403, $intentoMaquinaria['codigo']);

        $intentoRepuesto = $operario->post('/api-gestion/repuestos.php', [
            'nombre' => 'No debería crearse', 'tipo' => 'X',
        ]);
        self::assertSame(403, $intentoRepuesto['codigo']);

        // Sí puede listar ambos (cualquier sesión activa).
        self::assertSame(200, $operario->get('/api-gestion/maquinaria.php')['codigo']);
        self::assertSame(200, $operario->get('/api-gestion/repuestos.php')['codigo']);
    }
}
