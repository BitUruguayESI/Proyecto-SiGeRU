<?php
/**
 * api-retiros/actualizar_estado.php
 * Marca una solicitud de retiro como RETIRADO. Solo el Administrador
 * (es quien coordina y despacha las cuadrillas para estos retiros
 * puntuales, caso a caso).
 *
 * PUT { "id_solicitud": 1, "id_cuadrilla": 2 }   (id_cuadrilla es opcional)
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

requerirRolAdministrador();

$pdo = obtenerConexion();

$entrada = obtenerJsonEntrada();
$idSolicitud = (int) ($entrada['id_solicitud'] ?? 0);
$idCuadrilla = isset($entrada['id_cuadrilla']) && $entrada['id_cuadrilla'] !== ''
    ? (int) $entrada['id_cuadrilla']
    : null;

if ($idSolicitud <= 0) {
    responderJson(['exito' => false, 'mensaje' => 'id_solicitud es obligatorio.'], 400);
}

$consulta = $pdo->prepare('SELECT estado FROM SOLICITUD_RETIRO WHERE id_solicitud = :id');
$consulta->execute(['id' => $idSolicitud]);
$solicitud = $consulta->fetch();

if (!$solicitud) {
    responderJson(['exito' => false, 'mensaje' => 'Solicitud no encontrada.'], 404);
}

if ($solicitud['estado'] === 'RETIRADO') {
    responderJson(['exito' => false, 'mensaje' => 'Esta solicitud ya está marcada como retirada.'], 409);
}

if ($idCuadrilla !== null) {
    $cuadrilla = $pdo->prepare('SELECT id_cuadrilla FROM CUADRILLA WHERE id_cuadrilla = :id');
    $cuadrilla->execute(['id' => $idCuadrilla]);
    if (!$cuadrilla->fetch()) {
        responderJson(['exito' => false, 'mensaje' => 'La cuadrilla seleccionada no existe.'], 404);
    }
}

try {
    $pdo->beginTransaction();

    $pdo->prepare(
        "UPDATE SOLICITUD_RETIRO
         SET estado = 'RETIRADO', fecha_retiro = NOW(), id_cuadrilla = :id_cuadrilla
         WHERE id_solicitud = :id"
    )->execute([
        'id_cuadrilla' => $idCuadrilla,
        'id' => $idSolicitud,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderJson(['exito' => false, 'mensaje' => 'No se pudo actualizar la solicitud: ' . $e->getMessage()], 500);
}

responderJson(['exito' => true, 'mensaje' => 'Solicitud marcada como retirada.']);
