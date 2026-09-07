<?php
/**
 * api-incidencias/actualizar_estado.php
 * RF20 — Actualizar estado de incidencia, siguiendo el Diagrama de
 * Estados: ASIGNADA -> EN_PROCESO -> RESUELTA | ESCALADA.
 * (PENDIENTE->ASIGNADA y ESCALADA->ASIGNADA se hacen en asignar_cuadrilla.php,
 * porque esas dos transiciones requieren elegir una cuadrilla.)
 *
 * Cualquier USUARIO logueado (Operario o Administrador) puede actualizar
 * — en la práctica es la cuadrilla en el terreno la que marca el avance.
 *
 * PUT { "id_incidencia": 1, "estado": "EN_PROCESO" | "RESUELTA" | "ESCALADA" }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

requerirSesionActiva();

$entrada = obtenerJsonEntrada();
$idIncidencia = (int) ($entrada['id_incidencia'] ?? 0);
$estadoNuevo  = limpiarTexto($entrada['estado'] ?? '');

if ($idIncidencia <= 0) {
    responderJson(['exito' => false, 'mensaje' => 'id_incidencia es obligatorio.'], 400);
}

// Transiciones permitidas por el Diagrama de Estados (sin pasar por asignar_cuadrilla.php)
$transicionesValidas = [
    'ASIGNADA'   => ['EN_PROCESO'],
    'EN_PROCESO' => ['RESUELTA', 'ESCALADA'],
];

$pdo = obtenerConexion();

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

    if ($estadoNuevo === 'RESUELTA') {
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
    } elseif ($estadoNuevo === 'ESCALADA') {
        $pdo->prepare(
            "UPDATE INCIDENCIA SET estado = 'ESCALADA', fecha_escalacion = NOW() WHERE id_incidencia = :id"
        )->execute(['id' => $idIncidencia]);
    } else {
        $pdo->prepare('UPDATE INCIDENCIA SET estado = :estado WHERE id_incidencia = :id')
            ->execute(['estado' => $estadoNuevo, 'id' => $idIncidencia]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderJson(['exito' => false, 'mensaje' => 'No se pudo actualizar el estado: ' . $e->getMessage()], 500);
}

responderJson(['exito' => true, 'mensaje' => "Incidencia actualizada a {$estadoNuevo}."]);
