<?php
/**
 * api-incidencias/registrar.php
 * RF15 — Registrar incidencia. Público (no requiere cuenta) y anónimo:
 * no se pide ni se guarda ningún dato de contacto del vecino
 * (id_vecino queda NULL). Como no hay email, el sistema ya no puede
 * detectar si el mismo vecino ya había reportado el mismo problema.
 *
 * Espera POST JSON:
 * { "id_contenedor": 1, "tipo_incidencia": "Desborde", "descripcion": "..." }
 *
 * Si tipo_incidencia sugiere desborde, el contenedor pasa a estado
 * DESBORDADO automáticamente (simplificación: alcanza con la palabra
 * "desborde" en el tipo; se puede afinar más adelante con un campo
 * booleano explícito en el formulario), y su nivel_llenado se lleva a
 * 100 (queda reflejado también en el historial de lecturas usado por
 * las predicciones de llenado, nivel avanzado 3.4).
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$entrada = obtenerJsonEntrada();

$idContenedor    = (int) ($entrada['id_contenedor'] ?? 0);
$tipoIncidencia  = limpiarTexto($entrada['tipo_incidencia'] ?? '');
$descripcion     = limpiarTexto($entrada['descripcion'] ?? '');

$errores = [];
if ($idContenedor <= 0) $errores[] = 'Tenés que seleccionar un contenedor.';
if ($tipoIncidencia === '') $errores[] = 'El tipo de incidencia es obligatorio.';
if ($descripcion === '') $errores[] = 'La descripción es obligatoria.';
if ($errores) {
    responderJson(['exito' => false, 'mensaje' => implode(' ', $errores)], 400);
}

$pdo = obtenerConexion();

$contenedor = $pdo->prepare('SELECT id_contenedor FROM CONTENEDOR WHERE id_contenedor = :id');
$contenedor->execute(['id' => $idContenedor]);
if (!$contenedor->fetch()) {
    responderJson(['exito' => false, 'mensaje' => 'El contenedor seleccionado no existe.'], 404);
}

try {
    $pdo->beginTransaction();

    // Denuncia anónima: no se identifica al vecino, id_vecino queda NULL.
    $insertarIncidencia = $pdo->prepare(
        'INSERT INTO INCIDENCIA (descripcion, tipo_incidencia, estado, id_contenedor, id_vecino)
         VALUES (:descripcion, :tipo, \'PENDIENTE\', :id_contenedor, NULL)'
    );
    $insertarIncidencia->execute([
        'descripcion'   => $descripcion,
        'tipo'          => $tipoIncidencia,
        'id_contenedor' => $idContenedor,
    ]);
    $idIncidencia = (int) $pdo->lastInsertId();

    if (stripos($tipoIncidencia, 'desborde') !== false) {
        $pdo->prepare("UPDATE CONTENEDOR SET estado = 'DESBORDADO', nivel_llenado = 100 WHERE id_contenedor = :id")
            ->execute(['id' => $idContenedor]);
        $pdo->prepare('INSERT INTO LECTURA_NIVEL_CONTENEDOR (id_contenedor, nivel_llenado) VALUES (:id, 100)')
            ->execute(['id' => $idContenedor]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderJson(['exito' => false, 'mensaje' => 'No se pudo registrar la incidencia: ' . $e->getMessage()], 500);
}

responderJson([
    'exito' => true,
    'mensaje' => 'Incidencia registrada de forma anónima.',
    'id_incidencia' => $idIncidencia,
], 201);
