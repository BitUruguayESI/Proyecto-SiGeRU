<?php
/**
 * api-gestion/mantenimiento_maquinaria.php
 * Nivel avanzado 3.2 — historial de reparaciones/mantenimiento de
 * MAQUINARIA, análogo a api-recoleccion/mantenimiento.php (camiones).
 * A diferencia de los camiones, acá no hay un "resumen" en la tabla
 * padre que sincronizar: registrar un mantenimiento no cambia el
 * estado de la máquina automáticamente (lo hace el Administrador desde
 * el panel, por ejemplo volverla a OPERATIVA una vez reparada).
 *
 * GET  ?id_maquinaria=1   — staff (id_maquinaria opcional: sin filtro trae todo)
 * POST { id_maquinaria, fecha, tipo_mantenimiento, descripcion?, costo? } — solo Administrador
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

$pdo = obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    requerirSesionActiva();

    $sql = 'SELECT m.id_mantenimiento, m.fecha, m.tipo_mantenimiento, m.descripcion, m.costo,
                   m.id_maquinaria, ma.nombre AS nombre_maquinaria
            FROM MANTENIMIENTO_MAQUINARIA m JOIN MAQUINARIA ma ON ma.id_maquinaria = m.id_maquinaria';
    $valores = [];
    if (!empty($_GET['id_maquinaria'])) {
        $sql .= ' WHERE m.id_maquinaria = :id_maquinaria';
        $valores['id_maquinaria'] = (int) $_GET['id_maquinaria'];
    }
    $sql .= ' ORDER BY m.fecha DESC';

    $consulta = $pdo->prepare($sql);
    $consulta->execute($valores);

    responderJson(['exito' => true, 'mantenimientos' => $consulta->fetchAll()]);
}

if ($metodo === 'POST') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();

    $idMaquinaria = (int) ($entrada['id_maquinaria'] ?? 0);
    $fecha = limpiarTexto($entrada['fecha'] ?? '');
    $tipo = limpiarTexto($entrada['tipo_mantenimiento'] ?? '');
    $descripcion = limpiarTexto($entrada['descripcion'] ?? '');
    $costo = $entrada['costo'] ?? null;

    $errores = [];
    if ($idMaquinaria <= 0) $errores[] = 'Tenés que elegir una máquina.';
    if ($fecha === '') $errores[] = 'La fecha es obligatoria.';
    if ($tipo === '') $errores[] = 'El tipo de mantenimiento es obligatorio.';
    if ($costo !== null && $costo !== '' && (!is_numeric($costo) || (float) $costo < 0)) {
        $errores[] = 'El costo, si se indica, no puede ser negativo.';
    }
    if ($errores) {
        responderJson(['exito' => false, 'mensaje' => implode(' ', $errores)], 400);
    }

    $maquinaria = $pdo->prepare('SELECT id_maquinaria FROM MAQUINARIA WHERE id_maquinaria = :id');
    $maquinaria->execute(['id' => $idMaquinaria]);
    if (!$maquinaria->fetch()) {
        responderJson(['exito' => false, 'mensaje' => 'La máquina seleccionada no existe.'], 404);
    }

    $insertar = $pdo->prepare(
        'INSERT INTO MANTENIMIENTO_MAQUINARIA (fecha, tipo_mantenimiento, descripcion, costo, id_maquinaria)
         VALUES (:fecha, :tipo, :descripcion, :costo, :maquinaria)'
    );
    $insertar->execute([
        'fecha' => $fecha,
        'tipo' => $tipo,
        'descripcion' => $descripcion !== '' ? $descripcion : null,
        'costo' => ($costo !== null && $costo !== '') ? (float) $costo : null,
        'maquinaria' => $idMaquinaria,
    ]);

    responderJson([
        'exito' => true,
        'mensaje' => 'Mantenimiento registrado.',
        'id_mantenimiento' => (int) $pdo->lastInsertId(),
    ], 201);
}

responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
