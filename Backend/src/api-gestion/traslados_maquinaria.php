<?php
/**
 * api-gestion/traslados_maquinaria.php
 * Nivel avanzado 3.2 — registro de traslados de maquinaria entre
 * instalaciones (centros de acopio/vertederos). Al registrar un
 * traslado se actualiza también MAQUINARIA.id_centro, igual que
 * mantenimiento.php actualiza el resumen del camión.
 *
 * GET  ?id_maquinaria=1   — staff (id_maquinaria opcional: sin filtro trae todo)
 * POST { id_maquinaria, id_centro_destino, fecha, observaciones? } — solo Administrador
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

$pdo = obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    requerirSesionActiva();

    $sql = 'SELECT t.id_traslado, t.fecha, t.observaciones, t.id_maquinaria, ma.nombre AS nombre_maquinaria,
                   t.id_centro_origen, io.nombre AS nombre_origen,
                   t.id_centro_destino, id_.nombre AS nombre_destino
            FROM TRASLADO_MAQUINARIA t
            JOIN MAQUINARIA ma ON ma.id_maquinaria = t.id_maquinaria
            JOIN INSTALACION io ON io.id_centro = t.id_centro_origen
            JOIN INSTALACION id_ ON id_.id_centro = t.id_centro_destino';
    $valores = [];
    if (!empty($_GET['id_maquinaria'])) {
        $sql .= ' WHERE t.id_maquinaria = :id_maquinaria';
        $valores['id_maquinaria'] = (int) $_GET['id_maquinaria'];
    }
    $sql .= ' ORDER BY t.fecha DESC, t.id_traslado DESC';

    $consulta = $pdo->prepare($sql);
    $consulta->execute($valores);

    responderJson(['exito' => true, 'traslados' => $consulta->fetchAll()]);
}

if ($metodo === 'POST') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();

    $idMaquinaria = (int) ($entrada['id_maquinaria'] ?? 0);
    $idCentroDestino = (int) ($entrada['id_centro_destino'] ?? 0);
    $fecha = limpiarTexto($entrada['fecha'] ?? '');
    $observaciones = limpiarTexto($entrada['observaciones'] ?? '');

    $errores = [];
    if ($idMaquinaria <= 0) $errores[] = 'Tenés que elegir una máquina.';
    if ($idCentroDestino <= 0) $errores[] = 'Tenés que elegir la instalación de destino.';
    if ($fecha === '') $errores[] = 'La fecha es obligatoria.';
    if ($errores) {
        responderJson(['exito' => false, 'mensaje' => implode(' ', $errores)], 400);
    }

    $maquina = $pdo->prepare('SELECT id_centro FROM MAQUINARIA WHERE id_maquinaria = :id');
    $maquina->execute(['id' => $idMaquinaria]);
    $filaMaquina = $maquina->fetch();
    if (!$filaMaquina) {
        responderJson(['exito' => false, 'mensaje' => 'La máquina seleccionada no existe.'], 404);
    }
    $idCentroOrigen = (int) $filaMaquina['id_centro'];

    $destino = $pdo->prepare('SELECT id_centro FROM INSTALACION WHERE id_centro = :id');
    $destino->execute(['id' => $idCentroDestino]);
    if (!$destino->fetch()) {
        responderJson(['exito' => false, 'mensaje' => 'La instalación de destino no existe.'], 404);
    }

    if ($idCentroDestino === $idCentroOrigen) {
        responderJson(['exito' => false, 'mensaje' => 'La máquina ya está en esa instalación.'], 400);
    }

    try {
        $pdo->beginTransaction();

        $insertar = $pdo->prepare(
            'INSERT INTO TRASLADO_MAQUINARIA (fecha, id_maquinaria, id_centro_origen, id_centro_destino, observaciones)
             VALUES (:fecha, :maquinaria, :origen, :destino, :observaciones)'
        );
        $insertar->execute([
            'fecha' => $fecha,
            'maquinaria' => $idMaquinaria,
            'origen' => $idCentroOrigen,
            'destino' => $idCentroDestino,
            'observaciones' => $observaciones !== '' ? $observaciones : null,
        ]);
        $idTraslado = (int) $pdo->lastInsertId();

        $pdo->prepare('UPDATE MAQUINARIA SET id_centro = :centro WHERE id_maquinaria = :id')
            ->execute(['centro' => $idCentroDestino, 'id' => $idMaquinaria]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        responderJson(['exito' => false, 'mensaje' => 'No se pudo registrar el traslado: ' . $e->getMessage()], 500);
    }

    responderJson(['exito' => true, 'mensaje' => 'Traslado registrado.', 'id_traslado' => $idTraslado], 201);
}

responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
