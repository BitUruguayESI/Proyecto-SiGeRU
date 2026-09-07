<?php
/**
 * api-gestion/cuadrillas.php
 * CRUD de CUADRILLA, con sus rutas habituales (CUADRILLA_RUTA_HABITUAL).
 * Listar: cualquier sesión activa. Crear/editar/eliminar: solo ADMINISTRADOR.
 *
 * GET    ?id=1   -> una cuadrilla (con sus rutas habituales)
 * GET             -> todas las cuadrillas
 * POST   { nombre_cuadrilla, cantidad_integrantes?, rutas_habituales?: [id_ruta,...] }
 * PUT    { id_cuadrilla, nombre_cuadrilla?, cantidad_integrantes?, rutas_habituales?: [] }
 * DELETE { id_cuadrilla }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

$pdo = obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];

function cuadrillaConRutas(PDO $pdo, int $idCuadrilla): ?array
{
    $consulta = $pdo->prepare('SELECT * FROM CUADRILLA WHERE id_cuadrilla = :id');
    $consulta->execute(['id' => $idCuadrilla]);
    $cuadrilla = $consulta->fetch();
    if (!$cuadrilla) {
        return null;
    }
    $rutas = $pdo->prepare(
        'SELECT r.id_ruta, r.nombre_zona FROM CUADRILLA_RUTA_HABITUAL cr
         JOIN RUTA r ON r.id_ruta = cr.id_ruta WHERE cr.id_cuadrilla = :id'
    );
    $rutas->execute(['id' => $idCuadrilla]);
    $cuadrilla['rutas_habituales'] = $rutas->fetchAll();
    return $cuadrilla;
}

function reemplazarRutasHabituales(PDO $pdo, int $idCuadrilla, array $idsRuta): void
{
    $pdo->prepare('DELETE FROM CUADRILLA_RUTA_HABITUAL WHERE id_cuadrilla = :id')->execute(['id' => $idCuadrilla]);
    $insertar = $pdo->prepare('INSERT INTO CUADRILLA_RUTA_HABITUAL (id_cuadrilla, id_ruta) VALUES (:cuadrilla, :ruta)');
    foreach ($idsRuta as $idRuta) {
        $idRuta = (int) $idRuta;
        if ($idRuta > 0) {
            $insertar->execute(['cuadrilla' => $idCuadrilla, 'ruta' => $idRuta]);
        }
    }
}

if ($metodo === 'GET') {
    requerirSesionActiva();

    if (isset($_GET['id'])) {
        $cuadrilla = cuadrillaConRutas($pdo, (int) $_GET['id']);
        if (!$cuadrilla) {
            responderJson(['exito' => false, 'mensaje' => 'Cuadrilla no encontrada.'], 404);
        }
        responderJson(['exito' => true, 'cuadrilla' => $cuadrilla]);
    }

    $ids = $pdo->query('SELECT id_cuadrilla FROM CUADRILLA ORDER BY id_cuadrilla')->fetchAll();
    $lista = array_map(fn($fila) => cuadrillaConRutas($pdo, (int) $fila['id_cuadrilla']), $ids);
    responderJson(['exito' => true, 'cuadrillas' => $lista]);
}

if ($metodo === 'POST') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();

    $nombre = limpiarTexto($entrada['nombre_cuadrilla'] ?? '');
    $cantidad = (int) ($entrada['cantidad_integrantes'] ?? 0);
    $rutasHabituales = is_array($entrada['rutas_habituales'] ?? null) ? $entrada['rutas_habituales'] : [];

    if ($nombre === '') {
        responderJson(['exito' => false, 'mensaje' => 'nombre_cuadrilla es obligatorio.'], 400);
    }

    try {
        $pdo->beginTransaction();
        $insertar = $pdo->prepare(
            'INSERT INTO CUADRILLA (nombre_cuadrilla, cantidad_integrantes) VALUES (:nombre, :cantidad)'
        );
        $insertar->execute(['nombre' => $nombre, 'cantidad' => $cantidad]);
        $idCuadrilla = (int) $pdo->lastInsertId();

        if ($rutasHabituales) {
            reemplazarRutasHabituales($pdo, $idCuadrilla, $rutasHabituales);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        responderJson(['exito' => false, 'mensaje' => 'No se pudo registrar la cuadrilla: ' . $e->getMessage()], 500);
    }

    responderJson(['exito' => true, 'mensaje' => 'Cuadrilla registrada.', 'id_cuadrilla' => $idCuadrilla], 201);
}

if ($metodo === 'PUT') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idCuadrilla = (int) ($entrada['id_cuadrilla'] ?? 0);

    if ($idCuadrilla <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_cuadrilla es obligatorio.'], 400);
    }

    $campos = [];
    $valores = ['id' => $idCuadrilla];
    if (isset($entrada['nombre_cuadrilla'])) {
        $campos[] = 'nombre_cuadrilla = :nombre';
        $valores['nombre'] = limpiarTexto($entrada['nombre_cuadrilla']);
    }
    if (isset($entrada['cantidad_integrantes'])) {
        $campos[] = 'cantidad_integrantes = :cantidad';
        $valores['cantidad'] = (int) $entrada['cantidad_integrantes'];
    }

    try {
        $pdo->beginTransaction();
        if ($campos) {
            $sql = 'UPDATE CUADRILLA SET ' . implode(', ', $campos) . ' WHERE id_cuadrilla = :id';
            $pdo->prepare($sql)->execute($valores);
        }
        if (isset($entrada['rutas_habituales']) && is_array($entrada['rutas_habituales'])) {
            reemplazarRutasHabituales($pdo, $idCuadrilla, $entrada['rutas_habituales']);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        responderJson(['exito' => false, 'mensaje' => 'No se pudo actualizar la cuadrilla: ' . $e->getMessage()], 500);
    }

    responderJson(['exito' => true, 'mensaje' => 'Cuadrilla actualizada.']);
}

if ($metodo === 'DELETE') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idCuadrilla = (int) ($entrada['id_cuadrilla'] ?? 0);

    if ($idCuadrilla <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_cuadrilla es obligatorio.'], 400);
    }

    try {
        $eliminar = $pdo->prepare('DELETE FROM CUADRILLA WHERE id_cuadrilla = :id');
        $eliminar->execute(['id' => $idCuadrilla]);
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            responderJson(['exito' => false, 'mensaje' => 'No se puede eliminar: la cuadrilla tiene asignaciones o incidencias registradas.'], 409);
        }
        throw $e;
    }

    if ($eliminar->rowCount() === 0) {
        responderJson(['exito' => false, 'mensaje' => 'Cuadrilla no encontrada.'], 404);
    }

    responderJson(['exito' => true, 'mensaje' => 'Cuadrilla eliminada.']);
}

responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
