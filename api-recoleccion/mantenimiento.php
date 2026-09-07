<?php
/**
 * api-recoleccion/mantenimiento.php
 * RF27 / RN20 — Historial de mantenimiento de un camión, independiente
 * del resumen (CAMION.kilometraje / fecha_ultimo_mantenimiento) que se
 * usa para consultas rápidas. Al registrar un mantenimiento se
 * actualiza también ese resumen.
 *
 * GET  ?id_camion=1   — staff (id_camion opcional: sin filtro trae todo)
 * POST { id_camion, fecha, kilometraje, tipo_mantenimiento, descripcion? } — solo Administrador
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

$pdo = obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    requerirSesionActiva();

    $sql = 'SELECT m.id_mantenimiento, m.fecha, m.kilometraje, m.tipo_mantenimiento,
                   m.descripcion, m.id_camion, c.matricula
            FROM MANTENIMIENTO_CAMION m JOIN CAMION c ON c.id_camion = m.id_camion';
    $valores = [];
    if (!empty($_GET['id_camion'])) {
        $sql .= ' WHERE m.id_camion = :id_camion';
        $valores['id_camion'] = (int) $_GET['id_camion'];
    }
    $sql .= ' ORDER BY m.fecha DESC';

    $consulta = $pdo->prepare($sql);
    $consulta->execute($valores);

    responderJson(['exito' => true, 'mantenimientos' => $consulta->fetchAll()]);
}

if ($metodo === 'POST') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();

    $idCamion = (int) ($entrada['id_camion'] ?? 0);
    $fecha = limpiarTexto($entrada['fecha'] ?? '');
    $kilometraje = (int) ($entrada['kilometraje'] ?? -1);
    $tipo = limpiarTexto($entrada['tipo_mantenimiento'] ?? '');
    $descripcion = limpiarTexto($entrada['descripcion'] ?? '');

    $errores = [];
    if ($idCamion <= 0) $errores[] = 'Tenés que elegir un camión.';
    if ($fecha === '') $errores[] = 'La fecha es obligatoria.';
    if ($kilometraje < 0) $errores[] = 'El kilometraje es obligatorio y no puede ser negativo.';
    if ($tipo === '') $errores[] = 'El tipo de mantenimiento es obligatorio.';
    if ($errores) {
        responderJson(['exito' => false, 'mensaje' => implode(' ', $errores)], 400);
    }

    $camion = $pdo->prepare('SELECT id_camion FROM CAMION WHERE id_camion = :id');
    $camion->execute(['id' => $idCamion]);
    if (!$camion->fetch()) {
        responderJson(['exito' => false, 'mensaje' => 'El camión seleccionado no existe.'], 404);
    }

    try {
        $pdo->beginTransaction();

        $insertar = $pdo->prepare(
            'INSERT INTO MANTENIMIENTO_CAMION (fecha, kilometraje, tipo_mantenimiento, descripcion, id_camion)
             VALUES (:fecha, :kilometraje, :tipo, :descripcion, :camion)'
        );
        $insertar->execute([
            'fecha' => $fecha, 'kilometraje' => $kilometraje, 'tipo' => $tipo,
            'descripcion' => $descripcion !== '' ? $descripcion : null, 'camion' => $idCamion,
        ]);
        // Se captura acá, antes del UPDATE: un UPDATE posterior en la misma
        // conexión resetea lastInsertId() a 0 aunque el INSERT haya sido exitoso.
        $idMantenimiento = (int) $pdo->lastInsertId();

        // Actualiza el resumen en CAMION (simplificación: asume que los
        // mantenimientos se cargan en orden cronológico).
        $pdo->prepare(
            "UPDATE CAMION SET kilometraje = :kilometraje, fecha_ultimo_mantenimiento = :fecha WHERE id_camion = :id"
        )->execute(['kilometraje' => $kilometraje, 'fecha' => $fecha, 'id' => $idCamion]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        responderJson(['exito' => false, 'mensaje' => 'No se pudo registrar el mantenimiento: ' . $e->getMessage()], 500);
    }

    responderJson(['exito' => true, 'mensaje' => 'Mantenimiento registrado.', 'id_mantenimiento' => $idMantenimiento], 201);
}

responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
