<?php
/**
 * api-gestion/notificaciones.php
 * Notificaciones/alertas del sistema (nivel avanzado 3.1) para el
 * Administrador: contenedores desbordados, incidencias sin asignar,
 * retiros/consultas sin responder, y vencimientos próximos de carnet de
 * salud/licencia de conducir. Se generan "a demanda" al pedir el
 * listado — ver Comun/notificaciones.php.
 *
 * GET  ?solo_no_leidas=1   (opcional)
 * PUT  { id_notificacion }      -> marca una notificación como leída
 * PUT  { marcar_todas: true }   -> marca todas las del usuario como leídas
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';
require_once __DIR__ . '/../Comun/notificaciones.php';

$pdo = obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $sesion = requerirRolAdministrador();

    generarNotificacionesPendientes($pdo);

    $sql = 'SELECT id_notificacion, mensaje, fecha_hora, tipo, referencia, leida FROM NOTIFICACION WHERE ci_usuario = :ci';
    $valores = ['ci' => $sesion['ci_usuario']];
    if (!empty($_GET['solo_no_leidas'])) {
        $sql .= ' AND leida = FALSE';
    }
    $sql .= ' ORDER BY fecha_hora DESC LIMIT 100';

    $consulta = $pdo->prepare($sql);
    $consulta->execute($valores);
    $notificaciones = $consulta->fetchAll();

    $noLeidas = $pdo->prepare('SELECT COUNT(*) AS total FROM NOTIFICACION WHERE ci_usuario = :ci AND leida = FALSE');
    $noLeidas->execute(['ci' => $sesion['ci_usuario']]);

    responderJson([
        'exito' => true,
        'notificaciones' => $notificaciones,
        'no_leidas' => (int) $noLeidas->fetch()['total'],
    ]);
}

if ($metodo === 'PUT') {
    $sesion = requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();

    if (!empty($entrada['marcar_todas'])) {
        $pdo->prepare('UPDATE NOTIFICACION SET leida = TRUE WHERE ci_usuario = :ci')
            ->execute(['ci' => $sesion['ci_usuario']]);
        responderJson(['exito' => true, 'mensaje' => 'Todas las notificaciones se marcaron como leídas.']);
    }

    $idNotificacion = (int) ($entrada['id_notificacion'] ?? 0);
    if ($idNotificacion <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_notificacion es obligatorio.'], 400);
    }

    $actualizar = $pdo->prepare(
        'UPDATE NOTIFICACION SET leida = TRUE WHERE id_notificacion = :id AND ci_usuario = :ci'
    );
    $actualizar->execute(['id' => $idNotificacion, 'ci' => $sesion['ci_usuario']]);

    if ($actualizar->rowCount() === 0) {
        responderJson(['exito' => false, 'mensaje' => 'Notificación no encontrada.'], 404);
    }

    responderJson(['exito' => true, 'mensaje' => 'Notificación marcada como leída.']);
}

responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
