<?php
/**
 * tests/PrediccionesApiTest.php
 * Nivel avanzado 3.4 — api-gestion/predicciones.php: estructura de la
 * respuesta, que un contenedor sin historial suficiente no arroje una
 * estimación inventada, y que la generación por zona se calcule
 * cruzando DESCARGA con la asignación diaria del camión ese día.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PrediccionesApiTest extends TestCase
{
    private ApiClient $anonimo;
    private ApiClient $admin;

    protected function setUp(): void
    {
        $this->anonimo = new ApiClient();
        $this->admin = new ApiClient();
        $sufijo = (string) random_int(0, 999999);
        $ciAdmin = '8' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $this->admin->post('/api-usuarios/registro.php', [
            'ci' => $ciAdmin, 'contrasena' => 'clave12345',
            'email' => "pred-admin{$sufijo}@sigeru.uy", 'nombre_completo' => 'Admin Predicciones',
            'tipo_usuario' => 'ADMINISTRADOR',
        ]);
        $this->admin->post('/api-usuarios/login.php', ['ci' => $ciAdmin, 'contrasena' => 'clave12345']);
    }

    public function testSinSesionDevuelve401(): void
    {
        self::assertSame(401, $this->anonimo->get('/api-gestion/predicciones.php')['codigo']);
    }

    public function testContenedorConHistorialInsuficienteNoArrojaEstimacionInventada(): void
    {
        $alta = $this->admin->post('/api-gestion/contenedores.php', [
            'direccion' => 'Predicciones test ' . random_int(0, 999999), 'nivel_llenado' => 40,
        ]);
        self::assertSame(201, $alta['codigo'], $alta['cuerpo']['mensaje'] ?? '');
        $idContenedor = $alta['cuerpo']['id_contenedor'];

        // Una segunda lectura el mismo día (menos de medio día de diferencia
        // real) no alcanza para calcular una tasa diaria confiable.
        $this->admin->put('/api-gestion/contenedores.php', ['id_contenedor' => $idContenedor, 'nivel_llenado' => 55]);

        $respuesta = $this->admin->get('/api-gestion/predicciones.php');
        self::assertSame(200, $respuesta['codigo']);
        self::assertArrayHasKey('contenedores', $respuesta['cuerpo']);
        self::assertArrayHasKey('zonas', $respuesta['cuerpo']);

        $encontrado = null;
        foreach ($respuesta['cuerpo']['contenedores'] as $c) {
            if ((int) $c['id_contenedor'] === (int) $idContenedor) { $encontrado = $c; break; }
        }
        self::assertTrue($encontrado !== null);
        self::assertSame(55, $encontrado['nivel_llenado']);
        self::assertNull($encontrado['dias_estimados_para_lleno']);
        self::assertNull($encontrado['tasa_diaria_pct']);
    }

    public function testGeneracionPorZonaCruzaDescargaConAsignacionDiaria(): void
    {
        $sufijo = random_int(0, 9999);
        $ci = fn() => '2' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);

        $ciChofer = $ci();
        $this->admin->post('/api-usuarios/registro.php', [
            'ci' => $ciChofer, 'contrasena' => 'clave12345', 'email' => "pred-chofer{$sufijo}@sigeru.uy",
            'nombre_completo' => 'Chofer Predicciones', 'tipo_usuario' => 'OPERARIO', 'cargo' => 'CHOFER',
            'licencia_categoria' => 'D', 'licencia_vencimiento' => '2027-01-01',
        ]);
        $listadoChoferes = $this->admin->get('/api-usuarios/listar_operarios.php?cargo=CHOFER');
        $idChofer = null;
        foreach ($listadoChoferes['cuerpo']['operarios'] as $o) {
            if ($o['ci'] === $ciChofer) { $idChofer = (int) $o['id_operario']; break; }
        }
        self::assertTrue($idChofer !== null);

        $camion = $this->admin->post('/api-gestion/camiones.php', [
            'matricula' => 'PRE' . random_int(1000, 9999), 'marca' => 'Test', 'modelo' => 'Test', 'capacidad_carga' => 1000,
        ]);
        $idCamion = $camion['cuerpo']['id_camion'];

        $cuadrilla = $this->admin->post('/api-gestion/cuadrillas.php', [
            'nombre_cuadrilla' => 'Cuadrilla predicciones ' . $sufijo, 'cantidad_integrantes' => 1,
            'id_camion' => $idCamion, 'id_chofer' => $idChofer,
        ]);
        self::assertSame(201, $cuadrilla['codigo'], $cuadrilla['cuerpo']['mensaje'] ?? '');

        $nombreZona = "Zona predicciones {$sufijo}";
        $ruta = $this->admin->post('/api-gestion/rutas.php', ['nombre_zona' => $nombreZona, 'frecuencia' => 'Diaria']);
        $idRuta = $ruta['cuerpo']['id_ruta'];

        $centro = $this->admin->post('/api-gestion/instalaciones.php', [
            'nombre' => 'Centro predicciones ' . $sufijo, 'coordenadas' => '-34.9,-56.1',
            'capacidad_maxima' => 5000, 'residuos_aceptados' => 'organico', 'tipo_instalacion' => 'CENTRO_ACOPIO',
        ]);
        $idCentro = $centro['cuerpo']['id_centro'];

        $hoy = (new DateTime())->format('Y-m-d');
        $asignacion = $this->admin->post('/api-recoleccion/asignaciones.php', [
            'fecha' => $hoy, 'turno' => 'MANANA',
            'id_cuadrilla' => $cuadrilla['cuerpo']['id_cuadrilla'], 'id_ruta' => $idRuta, 'id_camion' => $idCamion,
            'operarios' => [['id_operario' => $idChofer, 'rol_en_asignacion' => 'Chofer']],
        ]);
        self::assertSame(201, $asignacion['codigo'], $asignacion['cuerpo']['mensaje'] ?? '');

        $descarga = $this->admin->post('/api-recoleccion/descargas.php', [
            'id_camion' => $idCamion, 'id_centro' => $idCentro,
            'fecha_descarga' => $hoy, 'hora_descarga' => '14:00', 'peso' => 300,
        ]);
        self::assertSame(201, $descarga['codigo'], $descarga['cuerpo']['mensaje'] ?? '');

        $respuesta = $this->admin->get('/api-gestion/predicciones.php');
        self::assertSame(200, $respuesta['codigo']);

        $zona = null;
        foreach ($respuesta['cuerpo']['zonas'] as $z) {
            if ($z['nombre_zona'] === $nombreZona) { $zona = $z; break; }
        }
        self::assertTrue($zona !== null, 'No se encontró la zona en la generación de residuos.');
        self::assertEquals(10.0, $zona['generacion_promedio_diaria_kg']); // 300 kg / 30 días
        self::assertEquals(300.0, $zona['proyeccion_proximos_30_dias_kg']);
    }
}
