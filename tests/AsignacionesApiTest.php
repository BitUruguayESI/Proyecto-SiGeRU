<?php
/**
 * tests/AsignacionesApiTest.php
 * Cubre api-recoleccion/asignaciones.php: alta (RF23), edición (PUT,
 * agregado a pedido para poder corregir una asignación diaria sin
 * borrarla y crearla de nuevo) y que solo el Administrador pueda hacerlo.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AsignacionesApiTest extends TestCase
{
    private ApiClient $admin;

    protected function setUp(): void
    {
        $this->admin = new ApiClient();
        $sufijo = (string) random_int(0, 999999);
        $ciAdmin = '4' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $this->admin->post('/api-usuarios/registro.php', [
            'ci' => $ciAdmin, 'contrasena' => 'clave12345',
            'email' => "asig-admin{$sufijo}@sigeru.uy", 'nombre_completo' => 'Admin Asignaciones',
            'tipo_usuario' => 'ADMINISTRADOR',
        ]);
        $this->admin->post('/api-usuarios/login.php', ['ci' => $ciAdmin, 'contrasena' => 'clave12345']);
    }

    private function ciDePrueba(): string
    {
        return '2' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    }

    private function crearOperario(string $cargo): int
    {
        $ci = $this->ciDePrueba();
        $this->admin->post('/api-usuarios/registro.php', [
            'ci' => $ci, 'contrasena' => 'clave12345',
            'email' => "op{$ci}@sigeru.uy", 'nombre_completo' => "Operario {$ci}",
            'tipo_usuario' => 'OPERARIO', 'cargo' => $cargo,
        ]);
        $listado = $this->admin->get("/api-usuarios/listar_operarios.php?cargo={$cargo}");
        foreach ($listado['cuerpo']['operarios'] as $o) {
            if ($o['ci'] === $ci) {
                return (int) $o['id_operario'];
            }
        }
        throw new RuntimeException('No se encontró el operario recién creado.');
    }

    /** Arma una cuadrilla estable completa (camión + chofer + 1 recolector) y devuelve sus ids. */
    private function crearCuadrillaCompleta(): array
    {
        $idChofer = $this->crearOperario('CHOFER');
        $idRecolector = $this->crearOperario('RECOLECTOR');

        $camion = $this->admin->post('/api-gestion/camiones.php', [
            'matricula' => 'ASG' . random_int(1000, 9999),
            'marca' => 'Test', 'modelo' => 'Test', 'capacidad_carga' => 1000,
        ]);
        self::assertSame(201, $camion['codigo']);
        $idCamion = $camion['cuerpo']['id_camion'];

        $cuadrilla = $this->admin->post('/api-gestion/cuadrillas.php', [
            'nombre_cuadrilla' => 'Cuadrilla asignaciones ' . random_int(0, 9999),
            'cantidad_integrantes' => 2,
            'id_camion' => $idCamion,
            'id_chofer' => $idChofer,
            'recolectores' => [$idRecolector],
        ]);
        self::assertSame(201, $cuadrilla['codigo'], $cuadrilla['cuerpo']['mensaje'] ?? '');

        $ruta = $this->admin->post('/api-gestion/rutas.php', [
            'nombre_zona' => 'Zona asignaciones ' . random_int(0, 9999),
            'frecuencia' => 'Diaria',
        ]);
        self::assertSame(201, $ruta['codigo']);

        return [
            'id_cuadrilla' => $cuadrilla['cuerpo']['id_cuadrilla'],
            'id_camion' => $idCamion,
            'id_chofer' => $idChofer,
            'id_recolector' => $idRecolector,
            'id_ruta' => $ruta['cuerpo']['id_ruta'],
        ];
    }

    public function testCrearYEditarAsignacionDiaria(): void
    {
        $datos = $this->crearCuadrillaCompleta();

        $alta = $this->admin->post('/api-recoleccion/asignaciones.php', [
            'fecha' => '2026-10-01',
            'turno' => 'MANANA',
            'id_cuadrilla' => $datos['id_cuadrilla'],
            'id_ruta' => $datos['id_ruta'],
            'id_camion' => $datos['id_camion'],
            'operarios' => [
                ['id_operario' => $datos['id_chofer'], 'rol_en_asignacion' => 'Chofer'],
                ['id_operario' => $datos['id_recolector'], 'rol_en_asignacion' => 'Recolector'],
            ],
        ]);
        self::assertSame(201, $alta['codigo'], $alta['cuerpo']['mensaje'] ?? '');
        $idAsignacion = $alta['cuerpo']['id_asignacion'];

        // Edito: cambio fecha y turno, y saco al recolector (por ejemplo,
        // porque faltó ese día) dejando solo al chofer.
        $edicion = $this->admin->put('/api-recoleccion/asignaciones.php', [
            'id_asignacion' => $idAsignacion,
            'fecha' => '2026-10-02',
            'turno' => 'TARDE',
            'id_cuadrilla' => $datos['id_cuadrilla'],
            'id_ruta' => $datos['id_ruta'],
            'id_camion' => $datos['id_camion'],
            'operarios' => [
                ['id_operario' => $datos['id_chofer'], 'rol_en_asignacion' => 'Chofer'],
            ],
        ]);
        self::assertSame(200, $edicion['codigo'], $edicion['cuerpo']['mensaje'] ?? '');
        self::assertTrue($edicion['cuerpo']['exito']);

        $listado = $this->admin->get('/api-recoleccion/asignaciones.php?fecha=2026-10-02');
        self::assertCount(1, $listado['cuerpo']['asignaciones']);
        $actualizada = $listado['cuerpo']['asignaciones'][0];
        self::assertSame('TARDE', $actualizada['turno']);
        self::assertCount(1, $actualizada['operarios']);
        self::assertSame('Chofer', $actualizada['operarios'][0]['rol_en_asignacion']);
    }

    public function testEditarAsignacionInexistenteDevuelve404(): void
    {
        $respuesta = $this->admin->put('/api-recoleccion/asignaciones.php', [
            'id_asignacion' => 999999,
            'fecha' => '2026-10-02', 'turno' => 'TARDE',
            'id_cuadrilla' => 1, 'id_ruta' => 1, 'id_camion' => 1,
            'operarios' => [],
        ]);
        self::assertSame(404, $respuesta['codigo']);
    }

    public function testOperarioNoPuedeEditarAsignaciones(): void
    {
        $datos = $this->crearCuadrillaCompleta();
        $alta = $this->admin->post('/api-recoleccion/asignaciones.php', [
            'fecha' => '2026-10-03', 'turno' => 'MANANA',
            'id_cuadrilla' => $datos['id_cuadrilla'], 'id_ruta' => $datos['id_ruta'],
            'id_camion' => $datos['id_camion'],
            'operarios' => [['id_operario' => $datos['id_chofer'], 'rol_en_asignacion' => 'Chofer']],
        ]);
        $idAsignacion = $alta['cuerpo']['id_asignacion'];

        $operario = new ApiClient();
        $ciOperario = $this->ciDePrueba();
        $operario->post('/api-usuarios/registro.php', [
            'ci' => $ciOperario, 'contrasena' => 'clave12345',
            'email' => "op-ed{$ciOperario}@sigeru.uy", 'nombre_completo' => 'Operario Edicion',
            'tipo_usuario' => 'OPERARIO', 'cargo' => 'RECOLECTOR',
        ]);
        $operario->post('/api-usuarios/login.php', ['ci' => $ciOperario, 'contrasena' => 'clave12345']);

        $intento = $operario->put('/api-recoleccion/asignaciones.php', [
            'id_asignacion' => $idAsignacion,
            'fecha' => '2026-10-04', 'turno' => 'TARDE',
            'id_cuadrilla' => $datos['id_cuadrilla'], 'id_ruta' => $datos['id_ruta'],
            'id_camion' => $datos['id_camion'], 'operarios' => [],
        ]);
        self::assertSame(403, $intento['codigo']);
    }
}
