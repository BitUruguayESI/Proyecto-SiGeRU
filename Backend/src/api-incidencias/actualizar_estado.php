<?php
/**
 * api-incidencias/actualizar_estado.php
 * RF20 — Actualizar estado de incidencia, siguiendo el modelo simplificado
 * de 3 estados: PENDIENTE -> EN_PROCESO (en asignar_cuadrilla.php, al
 * elegirle una cuadrilla) -> RESUELTA (acá).
 *
 * Marcar RESUELTA está restringido: solo el Administrador o el Chofer de
 * la cuadrilla pueden hacerlo (el Recolector puede ver el estado, pero no
 * cerrarla) — ver Comun/funciones.php::requerirPuedeResolverIncidencia().
 *
 * PUT { "id_incidencia": 1, "estado": "RESUELTA" }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$pdo = obtenerConexion();

requerirPuedeResolverIncidencia($pdo);

$entrada = obtenerJsonEntrada();
$idIncidencia = (int) ($entrada['id_incidencia'] ?? 0);
$estadoNuevo  = limpiarTexto($entrada['estado'] ?? '');

if ($idIncidencia <= 0) {
    responderJson(['exito' => false, 'mensaje' => 'id_incidencia es obligatorio.'], 400);
}

// Única transición que queda en este endpoint (PENDIENTE->EN_PROCESO se
// hace en asignar_cuadrilla.php, porque requiere elegir una cuadrilla).
$transicionesValidas = [
    'EN_PROCESO' => ['RESUELTA'],
];

$consulta = $pdo->prepare('SELECT estado, id_contenedor FROM INCIDENCIA WHERE id_incidencia = :id');
$consulta->execute(['id' => $idIncidencia]);
$incidencia = $consulta->fetch();

if (!$incidencia) {
    responderJson(['exito' => false, 'mensaje' => 'Incidencia no encontrada.'], 404);
}

$estadoActual = $incidencia['estado'];
$siguientesValidos = $transicionesValidas[$estadoActual] ?? [];

if (!in_array($estadoNuevo, $siguientesValidos, true)) {
    $opciones = $siguientesValidos ? implode(' o ', $siguientesValidos) : '(ninguna desde este estado con este endpoint)';
    responderJson([
        'exito' => false,
        'mensaje' => "Transición inválida: desde {$estadoActual} solo se puede pasar a {$opciones}.",
    ], 409);
}

try {
    $pdo->beginTransaction();

    // La única transición que llega hasta acá es EN_PROCESO -> RESUELTA.
    $pdo->prepare(
        "UPDATE INCIDENCIA SET estado = 'RESUELTA', fecha_resolucion = NOW() WHERE id_incidencia = :id"
    )->execute(['id' => $idIncidencia]);

    // Si el contenedor estaba desbordado por esta incidencia, se
    // considera solucionado (simplificación: no chequea si hay otras
    // incidencias abiertas sobre el mismo contenedor).
    if ($incidencia['id_contenedor']) {
        $pdo->prepare(
            "UPDATE CONTENEDOR SET estado = 'OPERATIVO' WHERE id_contenedor = :id AND estado = 'DESBORDADO'"
        )->execute(['id' => $incidencia['id_contenedor']]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderJson(['exito' => false, 'mensaje' => 'No se pudo actualizar el estado: ' . $e->getMessage()], 500);
}

responderJson(['exito' => true, 'mensaje' => "Incidencia actualizada a {$estadoNuevo}."]);
