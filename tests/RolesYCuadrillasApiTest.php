<?php
/**
 * tests/RolesYCuadrillasApiTest.php
 * Cubre lo nuevo de esta vuelta: rol editable (Administrador/Recolector/
 * Chofer) en api-usuarios/usuarios.php, y la cuadrilla "estable" (1
 * camión + 1 chofer + N recolectores) en api-gestion/cuadrillas.php.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RolesYCuadrillasApiTest extends TestCase
{
    private ApiClient $admin;
    private string $ciAdmin;

    protected function setUp(): void
    {
        $this->admin = new ApiClient();
        $sufijo = (string) random_int(0, 999999);
        $this->ciAdmin = '3' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $this->admin->post('/api-usuarios/registro.php', [
            'ci' => $this->ciAdmin, 'contrasena' => 'clave12345',
            'email' => "roles-admin{$sufijo}@sigeru.uy", 'nombre_completo' => 'Admin Roles',
            'tipo_usuario' => 'ADMINISTRADOR',
        ]);
        $this->admin->post('/api-usuarios/login.php', ['ci' => $this->ciAdmin, 'contrasena' => 'clave12345']);
    }

    private function ciDePrueba(string $prefijo = '2'): string
    {
        return $prefijo . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    }

    private function crearOperario(string $cargo): string
    {
        $ci = $this->ciDePrueba();
        $datos = [
            'ci' => $ci, 'contrasena' => 'clave12345',
            'email' => "op{$ci}@sigeru.uy", 'nombre_completo' => "Operario {$ci}",
            'tipo_usuario' => 'OPERARIO', 'cargo' => $cargo,
        ];
        if ($cargo === 'CHOFER') {
            $datos['licencia_categoria'] = 'D';
            $datos['licencia_vencimiento'] = '2027-01-01';
        }
        $this->admin->post('/api-usuarios/registro.php', $datos);
        return $ci;
    }

    public function testCambiarRolDeRecolectorAChofer(): void
    {
        $ci = $this->crearOperario('RECOLECTOR');

        $cambio = $this->admin->put('/api-usuarios/usuarios.php', ['ci' => $ci, 'rol' => 'CHOFER']);
        self::assertSame(200, $cambio['codigo']);
        self::assertTrue($cambio['cuerpo']['exito']);

        $detalle = $this->admin->get("/api-usuarios/usuarios.php?ci={$ci}");
        self::assertSame('CHOFER', $detalle['cuerpo']['usuario']['rol']);
        self::assertSame('OPERARIO', $detalle['cuerpo']['usuario']['tipo_usuario']);
    }

    public function testCambiarRolDeOperarioAAdministradorYViceversa(): void
    {
        $ci = $this->crearOperario('RECOLECTOR');

        $aAdmin = $this->admin->put('/api-usuarios/usuarios.php', ['ci' => $ci, 'rol' => 'ADMINISTRADOR']);
        self::assertSame(200, $aAdmin['codigo']);

        $detalle = $this->admin->get("/api-usuarios/usuarios.php?ci={$ci}");
        self::assertSame('ADMINISTRADOR', $detalle['cuerpo']['usuario']['tipo_usuario']);
        self::assertSame('ADMINISTRADOR', $detalle['cuerpo']['usuario']['rol']);

        // Y de vuelta a OPERARIO (con cargo CHOFER esta vez) — confirma que
        // la generalización RN01 se puede mover en los dos sentidos.
        $aChofer = $this->admin->put('/api-usuarios/usuarios.php', ['ci' => $ci, 'rol' => 'CHOFER']);
        self::assertSame(200, $aChofer['codigo']);

        $detalle2 = $this->admin->get("/api-usuarios/usuarios.php?ci={$ci}");
        self::assertSame('OPERARIO', $detalle2['cuerpo']['usuario']['tipo_usuario']);
        self::assertSame('CHOFER', $detalle2['cuerpo']['usuario']['rol']);
    }

    public function testAdministradorNoPuedeCambiarsePropioRol(): void
    {
        $respuesta = $this->admin->put('/api-usuarios/usuarios.php', [
            'ci' => $this->ciAdmin, 'rol' => 'CHOFER',
        ]);
        self::assertSame(409, $respuesta['codigo']);
        self::assertFalse($respuesta['cuerpo']['exito']);
    }

    public function testRolInvalidoEsRechazado(): void
    {
        $ci = $this->crearOperario('RECOLECTOR');
        $respuesta = $this->admin->put('/api-usuarios/usuarios.php', ['ci' => $ci, 'rol' => 'SUPERVISOR']);
        self::assertSame(400, $respuesta['codigo']);
    }

    public function testCuadrillaConCamionChoferYRecolectoresEditable(): void
    {
        $ciChofer = $this->crearOperario('CHOFER');
        $ciRecolector1 = $this->crearOperario('RECOLECTOR');
        $ciRecolector2 = $this->crearOperario('RECOLECTOR');

        $operarios = $this->admin->get('/api-usuarios/listar_operarios.php');
        $idPorCi = [];
        foreach ($operarios['cuerpo']['operarios'] as $o) {
            $idPorCi[$o['ci']] = $o['id_operario'];
        }

        $camion = $this->admin->post('/api-gestion/camiones.php', [
            'matricula' => 'TST' . random_int(1000, 9999),
            'marca' => 'Test', 'modelo' => 'Test', 'capacidad_carga' => 1000,
        ]);
        self::assertSame(201, $camion['codigo']);
        $idCamion = $camion['cuerpo']['id_camion'];

        $cuadrilla = $this->admin->post('/api-gestion/cuadrillas.php', [
            'nombre_cuadrilla' => 'Cuadrilla test roles ' . random_int(0, 9999),
            'cantidad_integrantes' => 2,
            'id_camion' => $idCamion,
            'id_chofer' => $idPorCi[$ciChofer],
            'recolectores' => [$idPorCi[$ciRecolector1]],
        ]);
        self::assertSame(201, $cuadrilla['codigo'], $cuadrilla['cuerpo']['mensaje'] ?? '');
        $idCuadrilla = $cuadrilla['cuerpo']['id_cuadrilla'];

        $detalle = $this->admin->get("/api-gestion/cuadrillas.php?id={$idCuadrilla}");
        self::assertSame($idCamion, $detalle['cuerpo']['cuadrilla']['id_camion']);
        self::assertCount(1, $detalle['cuerpo']['cuadrilla']['recolectores']);

        // Editable: cambio el recolector de la cuadrilla.
        $edicion = $this->admin->put('/api-gestion/cuadrillas.php', [
            'id_cuadrilla' => $idCuadrilla,
            'recolectores' => [$idPorCi[$ciRecolector2]],
        ]);
        self::assertSame(200, $edicion['codigo']);

        $detalle2 = $this->admin->get("/api-gestion/cuadrillas.php?id={$idCuadrilla}");
        self::assertCount(1, $detalle2['cuerpo']['cuadrilla']['recolectores']);
        self::assertSame((int) $idPorCi[$ciRecolector2], (int) $detalle2['cuerpo']['cuadrilla']['recolectores'][0]['id_operario']);
    }

    public function testNoSePuedeAsignarElMismoChoferADosCuadrillas(): void
    {
        $ciChofer = $this->crearOperario('CHOFER');
        $operarios = $this->admin->get('/api-usuarios/listar_operarios.php?cargo=CHOFER');
        $idChofer = null;
        foreach ($operarios['cuerpo']['operarios'] as $o) {
            if ($o['ci'] === $ciChofer) $idChofer = $o['id_operario'];
        }
        self::assertTrue($idChofer !== null);

        $primera = $this->admin->post('/api-gestion/cuadrillas.php', [
            'nombre_cuadrilla' => 'Cuadrilla dup 1 ' . random_int(0, 9999),
            'id_chofer' => $idChofer,
        ]);
        self::assertSame(201, $primera['codigo']);

        $segunda = $this->admin->post('/api-gestion/cuadrillas.php', [
            'nombre_cuadrilla' => 'Cuadrilla dup 2 ' . random_int(0, 9999),
            'id_chofer' => $idChofer,
        ]);
        self::assertSame(409, $segunda['codigo']);
        self::assertFalse($segunda['cuerpo']['exito']);
    }
}
