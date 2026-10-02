<?php
/**
 * api-gestion/rutas.php
 * CRUD de RUTA (RF17 Definir zona geográfica, RF22 Registrar ruta).
 * Listar: cualquier sesión activa. Crear/editar/eliminar: solo ADMINISTRADOR.
 *
 * GET    ?id=1   -> una ruta
 * GET             -> todas las rutas
 * POST   { nombre_zona, frecuencia }
 * PUT    { id_ruta, nombre_zona?, frecuencia? }
 * DELETE { id_ruta }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

$pdo = obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    requerirSesionActiva();

    if (isset($_GET['id'])) {
        $consulta = $pdo->prepare('SELECT * FROM RUTA WHERE id_ruta = :id');
        $consulta->execute(['id' => (int) $_GET['id']]);
        $ruta = $consulta->fetch();
        if (!$ruta) {
            responderJson(['exito' => false, 'mensaje' => 'Ruta no encontrada.'], 404);
        }
        responderJson(['exito' => true, 'ruta' => $ruta]);
    }

    $rutas = $pdo->query('SELECT * FROM RUTA ORDER BY id_ruta')->fetchAll();
    responderJson(['exito' => true, 'rutas' => $rutas]);
}

if ($metodo === 'POST') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();

    $nombreZona = limpiarTexto($entrada['nombre_zona'] ?? '');
    $frecuencia = limpiarTexto($entrada['frecuencia'] ?? '');

    if ($nombreZona === '' || $frecuencia === '') {
        responderJson(['exito' => false, 'mensaje' => 'nombre_zona y frecuencia son obligatorios.'], 400);
    }

    $insertar = $pdo->prepare('INSERT INTO RUTA (nombre_zona, frecuencia) VALUES (:nombre_zona, :frecuencia)');
    $insertar->execute(['nombre_zona' => $nombreZona, 'frecuencia' => $frecuencia]);

    responderJson(['exito' => true, 'mensaje' => 'Ruta registrada.', 'id_ruta' => (int) $pdo->lastInsertId()], 201);
}

if ($metodo === 'PUT') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idRuta = (int) ($entrada['id_ruta'] ?? 0);

    if ($idRuta <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_ruta es obligatorio.'], 400);
    }

    $campos = [];
    $valores = ['id' => $idRuta];
    if (isset($entrada['nombre_zona'])) {
        $campos[] = 'nombre_zona = :nombre_zona';
        $valores['nombre_zona'] = limpiarTexto($entrada['nombre_zona']);
    }
    if (isset($entrada['frecuencia'])) {
        $campos[] = 'frecuencia = :frecuencia';
        $valores['frecuencia'] = limpiarTexto($entrada['frecuencia']);
    }
    if (!$campos) {
        responderJson(['exito' => false, 'mensaje' => 'No hay campos para actualizar.'], 400);
    }

    $sql = 'UPDATE RUTA SET ' . implode(', ', $campos) . ' WHERE id_ruta = :id';
    $pdo->prepare($sql)->execute($valores);

    responderJson(['exito' => true, 'mensaje' => 'Ruta actualizada.']);
}

if ($metodo === 'DELETE') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idRuta = (int) ($entrada['id_ruta'] ?? 0);

    if ($idRuta <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_ruta es obligatorio.'], 400);
    }

    // Los contenedores que apuntaban a esta ruta quedan con id_ruta NULL
    // (ON DELETE SET NULL), no se pierden.
    try {
        $eliminar = $pdo->prepare('DELETE FROM RUTA WHERE id_ruta = :id');
        $eliminar->execute(['id' => $idRuta]);
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            responderJson(['exito' => false, 'mensaje' => 'No se puede eliminar: la ruta tiene asignaciones diarias registradas.'], 409);
        }
        throw $e;
    }

    if ($eliminar->rowCount() === 0) {
        responderJson(['exito' => false, 'mensaje' => 'Ruta no encontrada.'], 404);
    }

    responderJson(['exito' => true, 'mensaje' => 'Ruta eliminada.']);
}

responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
