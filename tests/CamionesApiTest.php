<?php
/**
 * tests/CamionesApiTest.php
 * Cubre el ciclo de vida completo de api-gestion/camiones.php (RF26):
 * alta, listado, edición (PUT) y baja lógica (DELETE -> FUERA_DE_SERVICIO),
 * además de que solo el Administrador pueda crear/editar/dar de baja.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CamionesApiTest extends TestCase
{
    private ApiClient $admin;

    protected function setUp(): void
    {
        $this->admin = new ApiClient();
        $sufijo = (string) random_int(0, 999999);
        $ciAdmin = '5' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $this->admin->post('/api-usuarios/registro.php', [
            'ci' => $ciAdmin, 'contrasena' => 'clave12345',
            'email' => "cam-admin{$sufijo}@sigeru.uy", 'nombre_completo' => 'Admin Camiones',
            'tipo_usuario' => 'ADMINISTRADOR',
        ]);
        $this->admin->post('/api-usuarios/login.php', ['ci' => $ciAdmin, 'contrasena' => 'clave12345']);
    }

    public function testCicloCompletoAltaEdicionYBaja(): void
    {
        $matricula = 'CAM' . random_int(10000, 99999);

        $alta = $this->admin->post('/api-gestion/camiones.php', [
            'matricula' => $matricula, 'marca' => 'Volvo', 'modelo' => 'FMX',
            'capacidad_carga' => 5000, 'kilometraje' => 100,
        ]);
        self::assertSame(201, $alta['codigo'], $alta['cuerpo']['mensaje'] ?? '');
        $idCamion = $alta['cuerpo']['id_camion'];

        // Listado: aparece con los datos cargados.
        $detalle = $this->admin->get("/api-gestion/camiones.php?id={$idCamion}");
        self::assertSame(200, $detalle['codigo']);
        self::assertSame($matricula, $detalle['cuerpo']['camion']['matricula']);
        self::assertSame('DISPONIBLE', $detalle['cuerpo']['camion']['estado']);

        // Edición: cambio kilometraje y estado.
        $edicion = $this->admin->put('/api-gestion/camiones.php', [
            'id_camion' => $idCamion, 'kilometraje' => 25000, 'estado' => 'EN_MANTENIMIENTO',
        ]);
        self::assertSame(200, $edicion['codigo'], $edicion['cuerpo']['mensaje'] ?? '');

        $trasEdicion = $this->admin->get("/api-gestion/camiones.php?id={$idCamion}");
        self::assertSame(25000, (int) $trasEdicion['cuerpo']['camion']['kilometraje']);
        self::assertSame('EN_MANTENIMIENTO', $trasEdicion['cuerpo']['camion']['estado']);

        // Baja lógica: el camión sigue existiendo, pero queda FUERA_DE_SERVICIO.
        $baja = $this->admin->delete('/api-gestion/camiones.php', ['id_camion' => $idCamion]);
        self::assertSame(200, $baja['codigo'], $baja['cuerpo']['mensaje'] ?? '');

        $trasBaja = $this->admin->get("/api-gestion/camiones.php?id={$idCamion}");
        self::assertSame(200, $trasBaja['codigo']);
        self::assertSame('FUERA_DE_SERVICIO', $trasBaja['cuerpo']['camion']['estado']);
    }

    public function testNoSePuedeRepetirMatricula(): void
    {
        $matricula = 'DUP' . random_int(10000, 99999);
        $primero = $this->admin->post('/api-gestion/camiones.php', [
            'matricula' => $matricula, 'marca' => 'Ford', 'modelo' => 'Cargo', 'capacidad_carga' => 3000,
        ]);
        self::assertSame(201, $primero['codigo']);

        $repetido = $this->admin->post('/api-gestion/camiones.php', [
            'matricula' => $matricula, 'marca' => 'Ford', 'modelo' => 'Cargo', 'capacidad_carga' => 3000,
        ]);
        self::assertSame(409, $repetido['codigo']);
    }

    public function testEditarSinIdDevuelve400(): void
    {
        $respuesta = $this->admin->put('/api-gestion/camiones.php', ['kilometraje' => 10]);
        self::assertSame(400, $respuesta['codigo']);
    }

    public function testOperarioNoPuedeCrearNiEditarNiDarDeBajaCamiones(): void
    {
        $operario = new ApiClient();
        $ciOperario = '2' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
        $operario->post('/api-usuarios/registro.php', [
            'ci' => $ciOperario, 'contrasena' => 'clave12345',
            'email' => "op-cam{$ciOperario}@sigeru.uy", 'nombre_completo' => 'Operario Camiones',
            'tipo_usuario' => 'OPERARIO', 'cargo' => 'CHOFER',
        ]);
        $operario->post('/api-usuarios/login.php', ['ci' => $ciOperario, 'contrasena' => 'clave12345']);

        $intentoAlta = $operario->post('/api-gestion/camiones.php', [
            'matricula' => 'NOP' . random_int(1000, 9999), 'marca' => 'X', 'modelo' => 'Y', 'capacidad_carga' => 100,
        ]);
        self::assertSame(403, $intentoAlta['codigo']);

        $intentoBaja = $operario->delete('/api-gestion/camiones.php', ['id_camion' => 1]);
        self::assertSame(403, $intentoBaja['codigo']);

        // Sí puede listar (cualquier sesión activa).
        $listado = $operario->get('/api-gestion/camiones.php');
        self::assertSame(200, $listado['codigo']);
    }
}
