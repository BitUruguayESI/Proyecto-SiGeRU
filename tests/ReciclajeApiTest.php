<?php
/**
 * tests/ReciclajeApiTest.php
 * Nivel avanzado 3.3 — catálogo de tipos de residuo (tipos_residuo.php),
 * su uso opcional al registrar una descarga (api-recoleccion/descargas.php),
 * y el reporte de % reciclado (reporte_reciclaje.php).
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ReciclajeApiTest extends TestCase
{
    private ApiClient $admin;
    private ApiClient $operario;
    private int $idCamion;
    private int $idCentro;

    protected function setUp(): void
    {
        $this->admin = new ApiClient();
        $this->operario = new ApiClient();
        $sufijo = (string) random_int(0, 999999);

        $ciAdmin = '8' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $this->admin->post('/api-usuarios/registro.php', [
            'ci' => $ciAdmin, 'contrasena' => 'clave12345',
            'email' => "recic-admin{$sufijo}@sigeru.uy", 'nombre_completo' => 'Admin Reciclaje',
            'tipo_usuario' => 'ADMINISTRADOR',
        ]);
        $this->admin->post('/api-usuarios/login.php', ['ci' => $ciAdmin, 'contrasena' => 'clave12345']);

        $ciOperario = '7' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $this->operario->post('/api-usuarios/registro.php', [
            'ci' => $ciOperario, 'contrasena' => 'clave12345',
            'email' => "recic-oper{$sufijo}@sigeru.uy", 'nombre_completo' => 'Operario Reciclaje',
            'tipo_usuario' => 'OPERARIO', 'cargo' => 'RECOLECTOR',
        ]);
        $this->operario->post('/api-usuarios/login.php', ['ci' => $ciOperario, 'contrasena' => 'clave12345']);

        $camion = $this->admin->post('/api-gestion/camiones.php', [
            'matricula' => 'REC' . random_int(1000, 9999), 'marca' => 'Volvo', 'modelo' => 'FMX', 'capacidad_carga' => 5000,
        ]);
        $this->idCamion = $camion['cuerpo']['id_camion'];

        $centro = $this->admin->post('/api-gestion/instalaciones.php', [
            'nombre' => 'Centro reciclaje ' . random_int(0, 9999), 'coordenadas' => '-34.9,-56.1',
            'capacidad_maxima' => 5000, 'residuos_aceptados' => 'organico,plastico', 'tipo_instalacion' => 'CENTRO_ACOPIO',
        ]);
        $this->idCentro = $centro['cuerpo']['id_centro'];
    }

    private function crearTipoResiduo(string $nombre, bool $reciclable): int
    {
        $respuesta = $this->admin->post('/api-gestion/tipos_residuo.php', ['nombre' => $nombre, 'reciclable' => $reciclable]);
        self::assertSame(201, $respuesta['codigo'], $respuesta['cuerpo']['mensaje'] ?? '');
        return $respuesta['cuerpo']['id_tipo_residuo'];
    }

    public function testSoloAdministradorPuedeCrearEditarYEliminarTiposDeResiduo(): void
    {
        self::assertSame(403, $this->operario->post('/api-gestion/tipos_residuo.php', ['nombre' => 'Plástico'])['codigo']);

        $nombre = 'Plástico ' . random_int(0, 9999);
        $idTipo = $this->crearTipoResiduo($nombre, true);

        $repetido = $this->admin->post('/api-gestion/tipos_residuo.php', ['nombre' => $nombre]);
        self::assertSame(409, $repetido['codigo']);

        $edicion = $this->admin->put('/api-gestion/tipos_residuo.php', ['id_tipo_residuo' => $idTipo, 'reciclable' => false]);
        self::assertSame(200, $edicion['codigo']);

        $detalle = $this->admin->get("/api-gestion/tipos_residuo.php?id={$idTipo}");
        self::assertFalse($detalle['cuerpo']['tipo_residuo']['reciclable']);

        self::assertSame(403, $this->operario->delete('/api-gestion/tipos_residuo.php', ['id_tipo_residuo' => $idTipo])['codigo']);
        self::assertSame(200, $this->admin->delete('/api-gestion/tipos_residuo.php', ['id_tipo_residuo' => $idTipo])['codigo']);
    }

    public function testReporteDeReciclajeCalculaPorcentajeConDescargasClasificadas(): void
    {
        $sufijo = random_int(0, 999999);
        $idReciclable = $this->crearTipoResiduo("Vidrio test {$sufijo}", true);
        $idNoReciclable = $this->crearTipoResiduo("Orgánico test {$sufijo}", false);

        $this->admin->post('/api-recoleccion/descargas.php', [
            'id_camion' => $this->idCamion, 'id_centro' => $this->idCentro,
            'fecha_descarga' => '2026-05-01', 'hora_descarga' => '10:00', 'peso' => 100,
            'id_tipo_residuo' => $idReciclable,
        ]);
        $this->admin->post('/api-recoleccion/descargas.php', [
            'id_camion' => $this->idCamion, 'id_centro' => $this->idCentro,
            'fecha_descarga' => '2026-05-01', 'hora_descarga' => '11:00', 'peso' => 300,
            'id_tipo_residuo' => $idNoReciclable,
        ]);
        // Sin clasificar: no debe afectar el porcentaje.
        $this->admin->post('/api-recoleccion/descargas.php', [
            'id_camion' => $this->idCamion, 'id_centro' => $this->idCentro,
            'fecha_descarga' => '2026-05-01', 'hora_descarga' => '12:00', 'peso' => 9999,
        ]);

        $reporte = $this->admin->get('/api-gestion/reporte_reciclaje.php');
        self::assertSame(200, $reporte['codigo']);
        self::assertTrue($reporte['cuerpo']['resumen']['peso_reciclable'] >= 100);
        self::assertTrue($reporte['cuerpo']['resumen']['peso_no_reciclable'] >= 300);
        self::assertTrue(is_numeric($reporte['cuerpo']['resumen']['porcentaje_reciclado'] ?? null));
    }

    public function testDescargaConTipoDeResiduoInexistenteDevuelve404(): void
    {
        $respuesta = $this->admin->post('/api-recoleccion/descargas.php', [
            'id_camion' => $this->idCamion, 'id_centro' => $this->idCentro,
            'fecha_descarga' => '2026-05-01', 'hora_descarga' => '09:00', 'peso' => 50,
            'id_tipo_residuo' => 999999,
        ]);
        self::assertSame(404, $respuesta['codigo']);
    }
}
