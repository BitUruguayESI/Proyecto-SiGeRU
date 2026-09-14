<?php
/**
 * api-recoleccion/descargas.php
 * RF24 / RN16 — Registro individual de descarga de un camión en una
 * instalación (centro de acopio o vertedero). Un mismo camión puede
 * descargar más de una vez, en instalaciones distintas.
 *
 * GET  ?id_camion=&id_centro=&fecha=   (todos opcionales) — staff
 * POST { id_camion, id_centro, fecha_descarga, hora_descarga, peso } — staff
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

$pdo = obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    requerirSesionActiva();

    $condiciones = [];
    $valores = [];
    if (!empty($_GET['id_camion'])) { $condiciones[] = 'd.id_camion = :id_camion'; $valores['id_camion'] = (int) $_GET['id_camion']; }
    if (!empty($_GET['id_centro'])) { $condiciones[] = 'd.id_centro = :id_centro'; $valores['id_centro'] = (int) $_GET['id_centro']; }
    if (!empty($_GET['fecha'])) { $condiciones[] = 'd.fecha_descarga = :fecha'; $valores['fecha'] = $_GET['fecha']; }

    $sql = 'SELECT d.id_descarga, d.fecha_descarga, d.hora_descarga, d.peso,
                   d.id_camion, c.matricula, d.id_centro, i.nombre AS instalacion
            FROM DESCARGA d
            JOIN CAMION c ON c.id_camion = d.id_camion
            JOIN INSTALACION i ON i.id_centro = d.id_centro';
    if ($condiciones) {
        $sql .= ' WHERE ' . implode(' AND ', $condiciones);
    }
    $sql .= ' ORDER BY d.fecha_descarga DESC, d.hora_descarga DESC';

    $consulta = $pdo->prepare($sql);
    $consulta->execute($valores);

    responderJson(['exito' => true, 'descargas' => $consulta->fetchAll()]);
}

if ($metodo === 'POST') {
    requerirSesionActiva();
    $entrada = obtenerJsonEntrada();

    $idCamion = (int) ($entrada['id_camion'] ?? 0);
    $idCentro = (int) ($entrada['id_centro'] ?? 0);
    $fecha = limpiarTexto($entrada['fecha_descarga'] ?? '');
    $hora = limpiarTexto($entrada['hora_descarga'] ?? '');
    $peso = (float) ($entrada['peso'] ?? 0);

    $errores = [];
    if ($idCamion <= 0) $errores[] = 'Tenés que elegir un camión.';
    if ($idCentro <= 0) $errores[] = 'Tenés que elegir una instalación.';
    if ($fecha === '') $errores[] = 'La fecha es obligatoria.';
    if ($hora === '') $errores[] = 'La hora es obligatoria.';
    if ($peso <= 0) $errores[] = 'El peso debe ser mayor a 0.';
    if ($errores) {
        responderJson(['exito' => false, 'mensaje' => implode(' ', $errores)], 400);
    }

    $camion = $pdo->prepare('SELECT id_camion FROM CAMION WHERE id_camion = :id');
    $camion->execute(['id' => $idCamion]);
    if (!$camion->fetch()) {
        responderJson(['exito' => false, 'mensaje' => 'El camión seleccionado no existe.'], 404);
    }
    $instalacion = $pdo->prepare('SELECT id_centro FROM INSTALACION WHERE id_centro = :id');
    $instalacion->execute(['id' => $idCentro]);
    if (!$instalacion->fetch()) {
        responderJson(['exito' => false, 'mensaje' => 'La instalación seleccionada no existe.'], 404);
    }

    $insertar = $pdo->prepare(
        'INSERT INTO DESCARGA (fecha_descarga, hora_descarga, peso, id_camion, id_centro)
         VALUES (:fecha, :hora, :peso, :camion, :centro)'
    );
    $insertar->execute(['fecha' => $fecha, 'hora' => $hora, 'peso' => $peso, 'camion' => $idCamion, 'centro' => $idCentro]);

    responderJson(['exito' => true, 'mensaje' => 'Descarga registrada.', 'id_descarga' => (int) $pdo->lastInsertId()], 201);
}

responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
