<?php
/**
 * api-gestion/repuestos.php
 * Catálogo de REPUESTO (para maquinaria y camiones). CRUD simple.
 * Listar: cualquier sesión activa. Crear/editar/eliminar: solo ADMINISTRADOR.
 *
 * GET    ?id=1   -> un repuesto
 * GET             -> todos los repuestos
 * POST   { nombre, tipo, stock?, estado? }
 * PUT    { id_repuesto, ...campos a cambiar... }
 * DELETE { id_repuesto }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

$pdo = obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];
$estadosValidos = ['DISPONIBLE', 'AGOTADO', 'DESCONTINUADO'];

if ($metodo === 'GET') {
    requerirSesionActiva();

    if (isset($_GET['id'])) {
        $consulta = $pdo->prepare('SELECT * FROM REPUESTO WHERE id_repuesto = :id');
        $consulta->execute(['id' => (int) $_GET['id']]);
        $repuesto = $consulta->fetch();
        if (!$repuesto) {
            responderJson(['exito' => false, 'mensaje' => 'Repuesto no encontrado.'], 404);
        }
        responderJson(['exito' => true, 'repuesto' => $repuesto]);
    }

    $repuestos = $pdo->query('SELECT * FROM REPUESTO ORDER BY nombre')->fetchAll();
    responderJson(['exito' => true, 'repuestos' => $repuestos]);
}

if ($metodo === 'POST') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();

    $nombre = limpiarTexto($entrada['nombre'] ?? '');
    $tipo = limpiarTexto($entrada['tipo'] ?? '');
    $stock = (int) ($entrada['stock'] ?? 0);
    $estado = limpiarTexto($entrada['estado'] ?? 'DISPONIBLE');

    $errores = [];
    if ($nombre === '') $errores[] = 'El nombre es obligatorio.';
    if ($tipo === '') $errores[] = 'El tipo es obligatorio.';
    if ($stock < 0) $errores[] = 'El stock no puede ser negativo.';
    if (!in_array($estado, $estadosValidos, true)) $errores[] = 'Estado inválido.';
    if ($errores) {
        responderJson(['exito' => false, 'mensaje' => implode(' ', $errores)], 400);
    }

    $insertar = $pdo->prepare(
        'INSERT INTO REPUESTO (nombre, tipo, stock, estado) VALUES (:nombre, :tipo, :stock, :estado)'
    );
    $insertar->execute(['nombre' => $nombre, 'tipo' => $tipo, 'stock' => $stock, 'estado' => $estado]);

    responderJson(['exito' => true, 'mensaje' => 'Repuesto registrado.', 'id_repuesto' => (int) $pdo->lastInsertId()], 201);
}

if ($metodo === 'PUT') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idRepuesto = (int) ($entrada['id_repuesto'] ?? 0);

    if ($idRepuesto <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_repuesto es obligatorio.'], 400);
    }

    $mapaCampos = ['nombre' => 'nombre', 'tipo' => 'tipo', 'stock' => 'stock', 'estado' => 'estado'];
    $campos = [];
    $valores = ['id' => $idRepuesto];

    foreach ($mapaCampos as $claveEntrada => $columna) {
        if (!isset($entrada[$claveEntrada])) continue;
        if ($columna === 'estado' && !in_array($entrada['estado'], $estadosValidos, true)) {
            responderJson(['exito' => false, 'mensaje' => 'Estado inválido.'], 400);
        }
        $campos[] = "$columna = :$columna";
        $valores[$columna] = is_string($entrada[$claveEntrada]) ? limpiarTexto($entrada[$claveEntrada]) : $entrada[$claveEntrada];
    }

    if (!$campos) {
        responderJson(['exito' => false, 'mensaje' => 'No hay campos para actualizar.'], 400);
    }

    $sql = 'UPDATE REPUESTO SET ' . implode(', ', $campos) . ' WHERE id_repuesto = :id';
    $pdo->prepare($sql)->execute($valores);

    responderJson(['exito' => true, 'mensaje' => 'Repuesto actualizado.']);
}

if ($metodo === 'DELETE') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idRepuesto = (int) ($entrada['id_repuesto'] ?? 0);

    if ($idRepuesto <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_repuesto es obligatorio.'], 400);
    }

    // No hay historial que dependa de REPUESTO (solo asociaciones N:M con
    // MAQUINARIA/CAMION), así que eliminar es seguro: las filas de
    // MAQUINARIA_REPUESTO/CAMION_REPUESTO se borran en cascada.
    $eliminar = $pdo->prepare('DELETE FROM REPUESTO WHERE id_repuesto = :id');
    $eliminar->execute(['id' => $idRepuesto]);

    if ($eliminar->rowCount() === 0) {
        responderJson(['exito' => false, 'mensaje' => 'Repuesto no encontrado.'], 404);
    }

    responderJson(['exito' => true, 'mensaje' => 'Repuesto eliminado.']);
}

responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
