<?php
/**
 * tests/ContenedoresApiTest.php
 * CRUD completo de api-gestion/contenedores.php, y su contrato de
 * autorización (401 sin sesión, 403 con sesión de Operario).
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ContenedoresApiTest extends TestCase
{
    private ApiClient $anonimo;
    private ApiClient $admin;
    private ApiClient $operario;

    protected function setUp(): void
    {
        $this->anonimo = new ApiClient();
        $this->admin = new ApiClient();
        $this->operario = new ApiClient();

        $sufijo = (string) random_int(0, 999999);

        $ciAdmin = '8' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $this->admin->post('/api-usuarios/registro.php', [
            'ci' => $ciAdmin, 'contrasena' => 'clave12345',
            'email' => "cont-admin{$sufijo}@sigeru.uy", 'nombre_completo' => 'Admin Contenedores',
            'tipo_usuario' => 'ADMINISTRADOR',
        ]);
        $this->admin->post('/api-usuarios/login.php', ['ci' => $ciAdmin, 'contrasena' => 'clave12345']);

        $ciOperario = '7' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $this->operario->post('/api-usuarios/registro.php', [
            'ci' => $ciOperario, 'contrasena' => 'clave12345',
            'email' => "cont-oper{$sufijo}@sigeru.uy", 'nombre_completo' => 'Operario Contenedores',
            'tipo_usuario' => 'OPERARIO', 'cargo' => 'RECOLECTOR',
        ]);
        $this->operario->post('/api-usuarios/login.php', ['ci' => $ciOperario, 'contrasena' => 'clave12345']);
    }

    public function testListarSinSesionDevuelve401(): void
    {
        $respuesta = $this->anonimo->get('/api-gestion/contenedores.php');
        self::assertSame(401, $respuesta['codigo']);
    }

    public function testCrearComoOperarioDevuelve403(): void
    {
        $respuesta = $this->operario->post('/api-gestion/contenedores.php', [
            'direccion' => '18 de Julio 1234, Montevideo',
            'tipos_residuo' => ['organico'],
        ]);
        self::assertSame(403, $respuesta['codigo']);
    }

    public function testCicloCompletoAltaEdicionYBajaComoAdministrador(): void
    {
        $alta = $this->admin->post('/api-gestion/contenedores.php', [
            'direccion' => '18 de Julio 1234, Montevideo',
            'nivel_llenado' => 10,
            'tipos_residuo' => ['organico', 'plastico'],
        ]);
        self::assertSame(201, $alta['codigo']);
        self::assertTrue($alta['cuerpo']['exito']);
        $id = $alta['cuerpo']['id_contenedor'];
        self::assertGreaterThan(0, $id);

        $uno = $this->admin->get("/api-gestion/contenedores.php?id={$id}");
        self::assertSame(200, $uno['codigo']);
        self::assertSame('OPERATIVO', $uno['cuerpo']['contenedor']['estado']);
        self::assertCount(2, $uno['cuerpo']['contenedor']['tipos_residuo']);

        $edicion = $this->admin->put('/api-gestion/contenedores.php', [
            'id_contenedor' => $id,
            'estado' => 'DESBORDADO',
        ]);
        self::assertSame(200, $edicion['codigo']);

        $verificacion = $this->admin->get("/api-gestion/contenedores.php?id={$id}");
        self::assertSame('DESBORDADO', $verificacion['cuerpo']['contenedor']['estado']);

        // "Dar de baja" es baja lógica: DELETE no borra la fila, la pasa
        // a FUERA_DE_SERVICIO (para no perder historial de incidencias).
        $baja = $this->admin->delete('/api-gestion/contenedores.php', ['id_contenedor' => $id]);
        self::assertSame(200, $baja['codigo']);

        $final = $this->admin->get("/api-gestion/contenedores.php?id={$id}");
        self::assertSame(200, $final['codigo']);
        self::assertSame('FUERA_DE_SERVICIO', $final['cuerpo']['contenedor']['estado']);
    }

    public function testListarComoOperarioSiFunciona(): void
    {
        // El Operario puede LEER (requerirSesionActiva), aunque no
        // pueda escribir (requerirRolAdministrador).
        $respuesta = $this->operario->get('/api-gestion/contenedores.php');
        self::assertSame(200, $respuesta['codigo']);
        self::assertTrue($respuesta['cuerpo']['exito']);
    }
}
