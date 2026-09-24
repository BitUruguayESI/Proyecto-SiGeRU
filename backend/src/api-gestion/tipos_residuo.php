<?php
/**
 * api-gestion/tipos_residuo.php
 * Nivel avanzado 3.3 — catálogo de tipos de residuo con su
 * clasificación reciclable/no reciclable. Es un catálogo de referencia
 * para el panel y para el reporte de reciclaje (reporte_reciclaje.php);
 * no reemplaza a CONTENEDOR_TIPO_RESIDUO ni a SOLICITUD_RETIRO.tipo_residuo,
 * que siguen siendo texto libre para no romper lo ya cargado. Listar:
 * cualquier sesión activa. Crear/editar/eliminar: solo Administrador.
 *
 * GET    ?id=1   -> un tipo
 * GET             -> todos los tipos
 * POST   { nombre, reciclable? }
 * PUT    { id_tipo_residuo, nombre?, reciclable? }
 * DELETE { id_tipo_residuo }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

$pdo = obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    requerirSesionActiva();

    if (isset($_GET['id'])) {
        $consulta = $pdo->prepare('SELECT id_tipo_residuo, nombre, reciclable FROM TIPO_RESIDUO WHERE id_tipo_residuo = :id');
        $consulta->execute(['id' => (int) $_GET['id']]);
        $tipo = $consulta->fetch();
        if (!$tipo) {
            responderJson(['exito' => false, 'mensaje' => 'Tipo de residuo no encontrado.'], 404);
        }
        $tipo['reciclable'] = (bool) $tipo['reciclable'];
        responderJson(['exito' => true, 'tipo_residuo' => $tipo]);
    }

    $lista = $pdo->query('SELECT id_tipo_residuo, nombre, reciclable FROM TIPO_RESIDUO ORDER BY nombre')->fetchAll();
    foreach ($lista as &$tipo) {
        $tipo['reciclable'] = (bool) $tipo['reciclable'];
    }
    responderJson(['exito' => true, 'tipos_residuo' => $lista]);
}

if ($metodo === 'POST') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();

    $nombre = limpiarTexto($entrada['nombre'] ?? '');
    $reciclable = !empty($entrada['reciclable']);

    if ($nombre === '') {
        responderJson(['exito' => false, 'mensaje' => 'El nombre es obligatorio.'], 400);
    }

    $existe = $pdo->prepare('SELECT id_tipo_residuo FROM TIPO_RESIDUO WHERE nombre = :nombre');
    $existe->execute(['nombre' => $nombre]);
    if ($existe->fetch()) {
        responderJson(['exito' => false, 'mensaje' => 'Ya existe un tipo de residuo con ese nombre.'], 409);
    }

    $insertar = $pdo->prepare('INSERT INTO TIPO_RESIDUO (nombre, reciclable) VALUES (:nombre, :reciclable)');
    $insertar->execute(['nombre' => $nombre, 'reciclable' => $reciclable ? 1 : 0]);

    responderJson([
        'exito' => true,
        'mensaje' => 'Tipo de residuo registrado.',
        'id_tipo_residuo' => (int) $pdo->lastInsertId(),
    ], 201);
}

if ($metodo === 'PUT') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idTipo = (int) ($entrada['id_tipo_residuo'] ?? 0);

    if ($idTipo <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_tipo_residuo es obligatorio.'], 400);
    }

    $campos = [];
    $valores = ['id' => $idTipo];
    if (isset($entrada['nombre'])) {
        $nombre = limpiarTexto($entrada['nombre']);
        if ($nombre === '') {
            responderJson(['exito' => false, 'mensaje' => 'El nombre no puede quedar vacío.'], 400);
        }
        $campos[] = 'nombre = :nombre';
        $valores['nombre'] = $nombre;
    }
    if (isset($entrada['reciclable'])) {
        $campos[] = 'reciclable = :reciclable';
        $valores['reciclable'] = $entrada['reciclable'] ? 1 : 0;
    }

    if (!$campos) {
        responderJson(['exito' => false, 'mensaje' => 'No hay cambios para guardar.'], 400);
    }

    $sql = 'UPDATE TIPO_RESIDUO SET ' . implode(', ', $campos) . ' WHERE id_tipo_residuo = :id';
    $actualizar = $pdo->prepare($sql);
    $actualizar->execute($valores);

    if ($actualizar->rowCount() === 0) {
        $consulta = $pdo->prepare('SELECT id_tipo_residuo FROM TIPO_RESIDUO WHERE id_tipo_residuo = :id');
        $consulta->execute(['id' => $idTipo]);
        if (!$consulta->fetch()) {
            responderJson(['exito' => false, 'mensaje' => 'Tipo de residuo no encontrado.'], 404);
        }
    }

    responderJson(['exito' => true, 'mensaje' => 'Tipo de residuo actualizado.']);
}

if ($metodo === 'DELETE') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idTipo = (int) ($entrada['id_tipo_residuo'] ?? 0);

    if ($idTipo <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_tipo_residuo es obligatorio.'], 400);
    }

    // Las descargas que ya lo usaban quedan con id_tipo_residuo NULL
    // (ON DELETE SET NULL) — se conserva el peso descargado, se pierde
    // solo la clasificación.
    $eliminar = $pdo->prepare('DELETE FROM TIPO_RESIDUO WHERE id_tipo_residuo = :id');
    $eliminar->execute(['id' => $idTipo]);

    if ($eliminar->rowCount() === 0) {
        responderJson(['exito' => false, 'mensaje' => 'Tipo de residuo no encontrado.'], 404);
    }

    responderJson(['exito' => true, 'mensaje' => 'Tipo de residuo eliminado.']);
}

responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
