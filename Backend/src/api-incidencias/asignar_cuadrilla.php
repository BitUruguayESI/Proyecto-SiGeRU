<?php
/**
 * api-incidencias/asignar_cuadrilla.php
 * RF19 — Asignar cuadrilla a incidencia. Solo ADMINISTRADOR.
 * Válido solo desde PENDIENTE -> pasa directo a EN_PROCESO (el modelo de
 * 3 estados no tiene un estado intermedio "ASIGNADA": asignarle una
 * cuadrilla a la incidencia ES lo que la pone en curso).
 *
 * PUT { "id_incidencia": 1, "id_cuadrilla": 2 }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

requerirRolAdministrador();

$entrada = obtenerJsonEntrada();
$idIncidencia = (int) ($entrada['id_incidencia'] ?? 0);
$idCuadrilla  = (int) ($entrada['id_cuadrilla'] ?? 0);

if ($idIncidencia <= 0 || $idCuadrilla <= 0) {
    responderJson(['exito' => false, 'mensaje' => 'id_incidencia e id_cuadrilla son obligatorios.'], 400);
}

$pdo = obtenerConexion();

$consulta = $pdo->prepare('SELECT estado FROM INCIDENCIA WHERE id_incidencia = :id');
$consulta->execute(['id' => $idIncidencia]);
$incidencia = $consulta->fetch();

if (!$incidencia) {
    responderJson(['exito' => false, 'mensaje' => 'Incidencia no encontrada.'], 404);
}
if ($incidencia['estado'] !== 'PENDIENTE') {
    responderJson([
        'exito' => false,
        'mensaje' => "No se puede asignar cuadrilla desde el estado {$incidencia['estado']}. Solo desde PENDIENTE.",
    ], 409);
}

$cuadrilla = $pdo->prepare('SELECT id_cuadrilla FROM CUADRILLA WHERE id_cuadrilla = :id');
$cuadrilla->execute(['id' => $idCuadrilla]);
if (!$cuadrilla->fetch()) {
    responderJson(['exito' => false, 'mensaje' => 'La cuadrilla seleccionada no existe.'], 404);
}

$actualizar = $pdo->prepare(
    "UPDATE INCIDENCIA SET id_cuadrilla = :cuadrilla, estado = 'EN_PROCESO' WHERE id_incidencia = :id"
);
$actualizar->execute(['cuadrilla' => $idCuadrilla, 'id' => $idIncidencia]);

responderJson(['exito' => true, 'mensaje' => 'Cuadrilla asignada. La incidencia pasó a EN_PROCESO.']);
