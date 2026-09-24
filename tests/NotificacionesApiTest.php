<?php
/**
 * tests/NotificacionesApiTest.php
 * Nivel avanzado 3.1 — api-gestion/notificaciones.php: solo
 * Administrador puede ver/gestionar sus alertas, se generan "a
 * demanda" (un contenedor desbordado dispara una), y se pueden marcar
 * como leídas (una por una, o todas juntas).
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class NotificacionesApiTest extends TestCase
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
            'email' => "notif-admin{$sufijo}@sigeru.uy", 'nombre_completo' => 'Admin Notificaciones',
            'tipo_usuario' => 'ADMINISTRADOR',
        ]);
        $this->admin->post('/api-usuarios/login.php', ['ci' => $ciAdmin, 'contrasena' => 'clave12345']);

        $ciOperario = '7' . str_pad($sufijo, 7, '0', STR_PAD_LEFT);
        $this->operario->post('/api-usuarios/registro.php', [
            'ci' => $ciOperario, 'contrasena' => 'clave12345',
            'email' => "notif-oper{$sufijo}@sigeru.uy", 'nombre_completo' => 'Operario Notificaciones',
            'tipo_usuario' => 'OPERARIO', 'cargo' => 'RECOLECTOR',
        ]);
        $this->operario->post('/api-usuarios/login.php', ['ci' => $ciOperario, 'contrasena' => 'clave12345']);
    }

    public function testListarSinSesionDevuelve401YComoOperarioDevuelve403(): void
    {
        self::assertSame(401, $this->anonimo->get('/api-gestion/notificaciones.php')['codigo']);
        self::assertSame(403, $this->operario->get('/api-gestion/notificaciones.php')['codigo']);
    }

    public function testContenedorDesbordadoGeneraAlertaYSePuedeMarcarLeida(): void
    {
        $direccion = 'Notificaciones test ' . random_int(0, 999999);
        $alta = $this->admin->post('/api-gestion/contenedores.php', ['direccion' => $direccion]);
        self::assertSame(201, $alta['codigo'], $alta['cuerpo']['mensaje'] ?? '');
        $idContenedor = $alta['cuerpo']['id_contenedor'];

        $this->admin->put('/api-gestion/contenedores.php', [
            'id_contenedor' => $idContenedor, 'estado' => 'DESBORDADO',
        ]);

        // Mensaje exacto que arma Comun/notificaciones.php — coincidencia
        // exacta en vez de "contains", para no confundirse con la alerta de
        // otro contenedor desbordado que haya quedado de otro test.
        $mensajeEsperado = "El contenedor #{$idContenedor} ({$direccion}) está desbordado.";

        $listado = $this->admin->get('/api-gestion/notificaciones.php');
        self::assertSame(200, $listado['codigo']);
        self::assertTrue($listado['cuerpo']['no_leidas'] >= 1);

        $encontrada = null;
        foreach ($listado['cuerpo']['notificaciones'] as $n) {
            if ($n['tipo'] === 'CONTENEDOR_DESBORDADO' && $n['mensaje'] === $mensajeEsperado) {
                $encontrada = $n;
                break;
            }
        }
        self::assertTrue($encontrada !== null, 'No se generó la alerta de contenedor desbordado.');
        self::assertFalse((bool) $encontrada['leida']);

        // No se duplica en un segundo pedido (dedup por tipo+mensaje).
        $segundoListado = $this->admin->get('/api-gestion/notificaciones.php');
        $repetidas = array_filter(
            $segundoListado['cuerpo']['notificaciones'],
            fn($n) => $n['tipo'] === 'CONTENEDOR_DESBORDADO' && $n['mensaje'] === $mensajeEsperado
        );
        self::assertCount(1, $repetidas);

        $marcar = $this->admin->put('/api-gestion/notificaciones.php', ['id_notificacion' => $encontrada['id_notificacion']]);
        self::assertSame(200, $marcar['codigo']);

        $trasMarcar = $this->admin->get('/api-gestion/notificaciones.php?solo_no_leidas=1');
        $sigueApareciendo = array_filter(
            $trasMarcar['cuerpo']['notificaciones'],
            fn($n) => (int) $n['id_notificacion'] === (int) $encontrada['id_notificacion']
        );
        self::assertCount(0, $sigueApareciendo);
    }

    public function testMarcarTodasComoLeidas(): void
    {
        $alta = $this->admin->post('/api-gestion/contenedores.php', [
            'direccion' => 'Notificaciones marcar todas ' . random_int(0, 999999),
        ]);
        $this->admin->put('/api-gestion/contenedores.php', [
            'id_contenedor' => $alta['cuerpo']['id_contenedor'], 'estado' => 'DESBORDADO',
        ]);
        $this->admin->get('/api-gestion/notificaciones.php'); // dispara la generación

        $marcarTodas = $this->admin->put('/api-gestion/notificaciones.php', ['marcar_todas' => true]);
        self::assertSame(200, $marcarTodas['codigo']);

        $trasMarcar = $this->admin->get('/api-gestion/notificaciones.php');
        self::assertSame(0, $trasMarcar['cuerpo']['no_leidas']);
    }
}
