<?php
/**
 * api-incidencias/deshacer_asignacion.php
 * Deshace una asignación de cuadrilla reciente: vuelve la incidencia de
 * EN_PROCESO a PENDIENTE y libera la cuadrilla, para el caso de que el
 * Administrador se haya equivocado de cuadrilla al asignar.
 *
 * No depende de ninguna columna nueva de fecha: la ventana de tiempo
 * "reciente" la controla el frontend (solo muestra el botón un rato
 * después de asignar); acá solo se valida que la incidencia siga
 * EN_PROCESO, por las dudas.
 *
 * PUT { "id_incidencia": 1 }
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

if ($idIncidencia <= 0) {
    responderJson(['exito' => false, 'mensaje' => 'id_incidencia es obligatorio.'], 400);
}

$pdo = obtenerConexion();

$consulta = $pdo->prepare('SELECT estado FROM INCIDENCIA WHERE id_incidencia = :id');
$consulta->execute(['id' => $idIncidencia]);
$incidencia = $consulta->fetch();

if (!$incidencia) {
    responderJson(['exito' => false, 'mensaje' => 'Incidencia no encontrada.'], 404);
}
if ($incidencia['estado'] !== 'EN_PROCESO') {
    responderJson([
        'exito' => false,
        'mensaje' => "No se puede deshacer: la incidencia ya está en estado {$incidencia['estado']}.",
    ], 409);
}

$pdo->prepare(
    "UPDATE INCIDENCIA SET id_cuadrilla = NULL, estado = 'PENDIENTE' WHERE id_incidencia = :id"
)->execute(['id' => $idIncidencia]);

responderJson(['exito' => true, 'mensaje' => 'Asignación deshecha. La incidencia volvió a Pendiente.']);
