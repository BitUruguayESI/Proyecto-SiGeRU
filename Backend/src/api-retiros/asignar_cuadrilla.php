<?php
/**
 * api-retiros/asignar_cuadrilla.php
 * Asigna (o reasigna) la cuadrilla que va a hacer un retiro especial.
 * Solo ADMINISTRADOR. A diferencia de INCIDENCIA, SOLICITUD_RETIRO tiene
 * solo 2 estados (PENDIENTE/RETIRADO) — asignar cuadrilla NO cambia el
 * estado, solo deja la solicitud lista para que el Chofer de esa
 * cuadrilla la vea en "Mi día" y la marque como retirada él mismo
 * (ver api-retiros/actualizar_estado.php).
 *
 * PUT { "id_solicitud": 1, "id_cuadrilla": 2 }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

requerirRolAdministrador();

$entrada = obtenerJsonEntrada();
$idSolicitud = (int) ($entrada['id_solicitud'] ?? 0);
$idCuadrilla = (int) ($entrada['id_cuadrilla'] ?? 0);

if ($idSolicitud <= 0 || $idCuadrilla <= 0) {
    responderJson(['exito' => false, 'mensaje' => 'id_solicitud e id_cuadrilla son obligatorios.'], 400);
}

$pdo = obtenerConexion();

$consulta = $pdo->prepare('SELECT estado FROM SOLICITUD_RETIRO WHERE id_solicitud = :id');
$consulta->execute(['id' => $idSolicitud]);
$solicitud = $consulta->fetch();

if (!$solicitud) {
    responderJson(['exito' => false, 'mensaje' => 'Solicitud no encontrada.'], 404);
}
if ($solicitud['estado'] !== 'PENDIENTE') {
    responderJson(['exito' => false, 'mensaje' => 'Esta solicitud ya está marcada como retirada.'], 409);
}

$cuadrilla = $pdo->prepare('SELECT id_cuadrilla FROM CUADRILLA WHERE id_cuadrilla = :id');
$cuadrilla->execute(['id' => $idCuadrilla]);
if (!$cuadrilla->fetch()) {
    responderJson(['exito' => false, 'mensaje' => 'La cuadrilla seleccionada no existe.'], 404);
}

$pdo->prepare('UPDATE SOLICITUD_RETIRO SET id_cuadrilla = :cuadrilla WHERE id_solicitud = :id')
    ->execute(['cuadrilla' => $idCuadrilla, 'id' => $idSolicitud]);

responderJson(['exito' => true, 'mensaje' => 'Cuadrilla asignada al retiro.']);
