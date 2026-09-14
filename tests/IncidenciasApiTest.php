<?php
/**
 * tests/IncidenciasApiTest.php
 * Máquina de estados de INCIDENCIA (RF18-RF20): reporte público,
 * asignación de cuadrilla, transiciones válidas e inválidas.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class IncidenciasApiTest extends TestCase
{
    private ApiClient $anonimo;
    private ApiClient $admin;
    private int $idContenedor;
    private int $idCuadrilla;

    protected function setUp(): void
    {
        $this->anonimo = new ApiClient();
        $this->admin = new ApiClient();

        $sufijo = (string) random_int(0, 999999);
        $ciAdmin = '6' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $this->admin->post('/api-usuarios/registro.php', [
            'ci' => $ciAdmin, 'contrasena' => 'clave12345',
            'email' => "inc-admin{$sufijo}@sigeru.uy", 'nombre_completo' => 'Admin Incidencias',
            'tipo_usuario' => 'ADMINISTRADOR',
        ]);
        $this->admin->post('/api-usuarios/login.php', ['ci' => $ciAdmin, 'contrasena' => 'clave12345']);

        $contenedor = $this->admin->post('/api-gestion/contenedores.php', [
            'direccion' => '18 de Julio 1234, Montevideo', 'tipos_residuo' => ['organico'],
        ]);
        $this->idContenedor = $contenedor['cuerpo']['id_contenedor'];

        $cuadrilla = $this->admin->post('/api-gestion/cuadrillas.php', [
            'nombre_cuadrilla' => "Cuadrilla test {$sufijo}", 'cantidad_integrantes' => 2,
        ]);
        $this->idCuadrilla = $cuadrilla['cuerpo']['id_cuadrilla'];
    }

    private function reportarIncidencia(string $tipo = 'Dañado'): int
    {
        $reporte = $this->anonimo->post('/api-incidencias/registrar.php', [
            'descripcion' => 'Incidencia de prueba automatizada.',
            'tipo_incidencia' => $tipo,
            'id_contenedor' => $this->idContenedor,
            'email_vecino' => 'vecino-test@correo.com',
        ]);
        self::assertSame(201, $reporte['codigo'], 'El reporte público debería aceptarse sin sesión.');
        return $reporte['cuerpo']['id_incidencia'];
    }

    public function testReportePublicoQuedaPendiente(): void
    {
        $id = $this->reportarIncidencia();

        $detalle = $this->admin->get('/api-incidencias/listar.php?estado=PENDIENTE');
        $encontrada = array_filter($detalle['cuerpo']['incidencias'], fn($i) => (int) $i['id_incidencia'] === $id);
        self::assertNotEmpty($encontrada);
    }

    /**
     * El vecino reporta por dirección, no por coordenadas: listar.php le
     * devuelve al Administrador la dirección del contenedor (no la
     * "coordenadas" cruda), y contenedores_disponibles.php (el que arma
     * la lista de sugerencias en reportar_incidencia.html) también la
     * incluye para poder mostrarla en vez de un id.
     */
    public function testDireccionSeVeEnVezDeCoordenadas(): void
    {
        $id = $this->reportarIncidencia();

        $listado = $this->admin->get('/api-incidencias/listar.php?estado=PENDIENTE');
        $incidencia = null;
        foreach ($listado['cuerpo']['incidencias'] as $i) {
            if ((int) $i['id_incidencia'] === $id) { $incidencia = $i; break; }
        }
        self::assertTrue($incidencia !== null);
        self::assertSame('18 de Julio 1234, Montevideo', $incidencia['contenedor_direccion']);
        self::assertFalse(array_key_exists('contenedor_coordenadas', $incidencia));

        $disponibles = $this->anonimo->get('/api-incidencias/contenedores_disponibles.php');
        self::assertSame(200, $disponibles['codigo']);
        $contenedor = null;
        foreach ($disponibles['cuerpo']['contenedores'] as $c) {
            if ((int) $c['id_contenedor'] === $this->idContenedor) { $contenedor = $c; break; }
        }
        self::assertTrue($contenedor !== null);
        self::assertSame('18 de Julio 1234, Montevideo', $contenedor['direccion']);
    }

    public function testDesbordeMarcaElContenedorComoDesbordado(): void
    {
        $this->reportarIncidencia('Desborde');

        $contenedor = $this->admin->get("/api-gestion/contenedores.php?id={$this->idContenedor}");
        self::assertSame('DESBORDADO', $contenedor['cuerpo']['contenedor']['estado']);
    }

    public function testNoSePuedeSaltarDePendienteAEnProceso(): void
    {
        $id = $this->reportarIncidencia();

        // Según el Diagrama de Estados, PENDIENTE sólo puede pasar a
        // ASIGNADA (vía asignar_cuadrilla.php); intentar EN_PROCESO
        // directo con actualizar_estado.php debe rechazarse.
        $respuesta = $this->admin->put('/api-incidencias/actualizar_estado.php', [
            'id_incidencia' => $id,
            'estado' => 'EN_PROCESO',
        ]);
        self::assertSame(409, $respuesta['codigo']);
        self::assertFalse($respuesta['cuerpo']['exito']);
    }

    public function testCicloCompletoHastaResueltaYElContenedorVuelveAOperativo(): void
    {
        $id = $this->reportarIncidencia('Desborde');

        // Con el modelo de 3 estados, asignar_cuadrilla.php ya deja la
        // incidencia en EN_PROCESO directamente (no hay estado ASIGNADA
        // intermedio).
        $asignacion = $this->admin->put('/api-incidencias/asignar_cuadrilla.php', [
            'id_incidencia' => $id,
            'id_cuadrilla' => $this->idCuadrilla,
        ]);
        self::assertSame(200, $asignacion['codigo']);

        $detalle = $this->admin->get("/api-incidencias/listar.php?id_cuadrilla={$this->idCuadrilla}");
        $encontrada = current(array_filter($detalle['cuerpo']['incidencias'], fn($i) => (int) $i['id_incidencia'] === $id));
        self::assertSame('EN_PROCESO', $encontrada['estado']);

        $resuelta = $this->admin->put('/api-incidencias/actualizar_estado.php', [
            'id_incidencia' => $id, 'estado' => 'RESUELTA',
        ]);
        self::assertSame(200, $resuelta['codigo']);

        $contenedor = $this->admin->get("/api-gestion/contenedores.php?id={$this->idContenedor}");
        self::assertSame('OPERATIVO', $contenedor['cuerpo']['contenedor']['estado']);
    }

    public function testRecolectorNoPuedeResolverPeroChoferSi(): void
    {
        $sufijo = (string) random_int(0, 999999);

        $ciRecolector = '5' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $recolector = new ApiClient();
        $recolector->post('/api-usuarios/registro.php', [
            'ci' => $ciRecolector, 'contrasena' => 'clave12345',
            'email' => "inc-recolector{$sufijo}@sigeru.uy", 'nombre_completo' => 'Recolector Incidencias',
            'tipo_usuario' => 'OPERARIO', 'cargo' => 'RECOLECTOR',
        ]);
        $recolector->post('/api-usuarios/login.php', ['ci' => $ciRecolector, 'contrasena' => 'clave12345']);

        $ciChofer = '4' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $chofer = new ApiClient();
        $chofer->post('/api-usuarios/registro.php', [
            'ci' => $ciChofer, 'contrasena' => 'clave12345',
            'email' => "inc-chofer{$sufijo}@sigeru.uy", 'nombre_completo' => 'Chofer Incidencias',
            'tipo_usuario' => 'OPERARIO', 'cargo' => 'CHOFER',
            'licencia_categoria' => 'D', 'licencia_vencimiento' => '2027-01-01',
        ]);
        $chofer->post('/api-usuarios/login.php', ['ci' => $ciChofer, 'contrasena' => 'clave12345']);

        $id = $this->reportarIncidencia();
        $this->admin->put('/api-incidencias/asignar_cuadrilla.php', [
            'id_incidencia' => $id, 'id_cuadrilla' => $this->idCuadrilla,
        ]);

        $rechazado = $recolector->put('/api-incidencias/actualizar_estado.php', [
            'id_incidencia' => $id, 'estado' => 'RESUELTA',
        ]);
        self::assertSame(403, $rechazado['codigo']);
        self::assertFalse($rechazado['cuerpo']['exito']);

        $aceptado = $chofer->put('/api-incidencias/actualizar_estado.php', [
            'id_incidencia' => $id, 'estado' => 'RESUELTA',
        ]);
        self::assertSame(200, $aceptado['codigo']);
        self::assertTrue($aceptado['cuerpo']['exito']);
    }
}
