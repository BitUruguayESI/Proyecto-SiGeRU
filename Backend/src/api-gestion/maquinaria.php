<?php
/**
 * api-gestion/maquinaria.php
 * CRUD de MAQUINARIA (asociada a una INSTALACION) con sus repuestos
 * (MAQUINARIA_REPUESTO, N:M con REPUESTO).
 * Listar: cualquier sesión activa. Crear/editar/eliminar: solo ADMINISTRADOR.
 *
 * GET    ?id=1   -> una máquina (con sus repuestos)
 * GET             -> todas las máquinas
 * POST   { nombre, tipo, id_centro, estado?, repuestos?: [id_repuesto,...] }
 * PUT    { id_maquinaria, ...campos..., repuestos?: [] }
 * DELETE { id_maquinaria }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

$pdo = obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];
$estadosValidos = ['OPERATIVA', 'EN_MANTENIMIENTO', 'FUERA_DE_SERVICIO'];

function maquinariaConRepuestos(PDO $pdo, int $idMaquinaria): ?array
{
    $consulta = $pdo->prepare(
        'SELECT m.*, i.nombre AS nombre_instalacion
         FROM MAQUINARIA m JOIN INSTALACION i ON i.id_centro = m.id_centro
         WHERE m.id_maquinaria = :id'
    );
    $consulta->execute(['id' => $idMaquinaria]);
    $maquinaria = $consulta->fetch();
    if (!$maquinaria) {
        return null;
    }
    $repuestos = $pdo->prepare(
        'SELECT r.id_repuesto, r.nombre, r.tipo FROM MAQUINARIA_REPUESTO mr
         JOIN REPUESTO r ON r.id_repuesto = mr.id_repuesto WHERE mr.id_maquinaria = :id'
    );
    $repuestos->execute(['id' => $idMaquinaria]);
    $maquinaria['repuestos'] = $repuestos->fetchAll();
    return $maquinaria;
}

function reemplazarRepuestosMaquinaria(PDO $pdo, int $idMaquinaria, array $idsRepuesto): void
{
    $pdo->prepare('DELETE FROM MAQUINARIA_REPUESTO WHERE id_maquinaria = :id')->execute(['id' => $idMaquinaria]);
    $insertar = $pdo->prepare('INSERT INTO MAQUINARIA_REPUESTO (id_maquinaria, id_repuesto) VALUES (:maquinaria, :repuesto)');
    foreach ($idsRepuesto as $idRepuesto) {
        $idRepuesto = (int) $idRepuesto;
        if ($idRepuesto > 0) {
            $insertar->execute(['maquinaria' => $idMaquinaria, 'repuesto' => $idRepuesto]);
        }
    }
}

if ($metodo === 'GET') {
    requerirSesionActiva();

    if (isset($_GET['id'])) {
        $maquinaria = maquinariaConRepuestos($pdo, (int) $_GET['id']);
        if (!$maquinaria) {
            responderJson(['exito' => false, 'mensaje' => 'Máquina no encontrada.'], 404);
        }
        responderJson(['exito' => true, 'maquinaria' => $maquinaria]);
    }

    $ids = $pdo->query('SELECT id_maquinaria FROM MAQUINARIA ORDER BY id_maquinaria')->fetchAll();
    $lista = array_map(fn($fila) => maquinariaConRepuestos($pdo, (int) $fila['id_maquinaria']), $ids);
    responderJson(['exito' => true, 'maquinas' => $lista]);
}

if ($metodo === 'POST') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();

    $nombre = limpiarTexto($entrada['nombre'] ?? '');
    $tipo = limpiarTexto($entrada['tipo'] ?? '');
    $idCentro = (int) ($entrada['id_centro'] ?? 0);
    $estado = limpiarTexto($entrada['estado'] ?? 'OPERATIVA');
    $repuestos = is_array($entrada['repuestos'] ?? null) ? $entrada['repuestos'] : [];

    $errores = [];
    if ($nombre === '') $errores[] = 'El nombre es obligatorio.';
    if ($tipo === '') $errores[] = 'El tipo es obligatorio.';
    if ($idCentro <= 0) $errores[] = 'Tenés que elegir una instalación (centro de acopio o vertedero).';
    if (!in_array($estado, $estadosValidos, true)) $errores[] = 'Estado inválido.';
    if ($errores) {
        responderJson(['exito' => false, 'mensaje' => implode(' ', $errores)], 400);
    }

    $centro = $pdo->prepare('SELECT id_centro FROM INSTALACION WHERE id_centro = :id');
    $centro->execute(['id' => $idCentro]);
    if (!$centro->fetch()) {
        responderJson(['exito' => false, 'mensaje' => 'La instalación seleccionada no existe.'], 404);
    }

    try {
        $pdo->beginTransaction();
        $insertar = $pdo->prepare(
            'INSERT INTO MAQUINARIA (nombre, tipo, estado, id_centro) VALUES (:nombre, :tipo, :estado, :centro)'
        );
        $insertar->execute(['nombre' => $nombre, 'tipo' => $tipo, 'estado' => $estado, 'centro' => $idCentro]);
        $idMaquinaria = (int) $pdo->lastInsertId();

        if ($repuestos) {
            reemplazarRepuestosMaquinaria($pdo, $idMaquinaria, $repuestos);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        responderJson(['exito' => false, 'mensaje' => 'No se pudo registrar la máquina: ' . $e->getMessage()], 500);
    }

    responderJson(['exito' => true, 'mensaje' => 'Máquina registrada.', 'id_maquinaria' => $idMaquinaria], 201);
}

if ($metodo === 'PUT') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idMaquinaria = (int) ($entrada['id_maquinaria'] ?? 0);

    if ($idMaquinaria <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_maquinaria es obligatorio.'], 400);
    }

    $mapaCampos = ['nombre' => 'nombre', 'tipo' => 'tipo', 'estado' => 'estado', 'id_centro' => 'id_centro'];
    $campos = [];
    $valores = ['id' => $idMaquinaria];

    foreach ($mapaCampos as $claveEntrada => $columna) {
        if (!isset($entrada[$claveEntrada])) continue;
        if ($columna === 'estado' && !in_array($entrada['estado'], $estadosValidos, true)) {
            responderJson(['exito' => false, 'mensaje' => 'Estado inválido.'], 400);
        }
        $campos[] = "$columna = :$columna";
        $valores[$columna] = is_string($entrada[$claveEntrada]) ? limpiarTexto($entrada[$claveEntrada]) : $entrada[$claveEntrada];
    }

    try {
        $pdo->beginTransaction();
        if ($campos) {
            $sql = 'UPDATE MAQUINARIA SET ' . implode(', ', $campos) . ' WHERE id_maquinaria = :id';
            $pdo->prepare($sql)->execute($valores);
        }
        if (isset($entrada['repuestos']) && is_array($entrada['repuestos'])) {
            reemplazarRepuestosMaquinaria($pdo, $idMaquinaria, $entrada['repuestos']);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        responderJson(['exito' => false, 'mensaje' => 'No se pudo actualizar la máquina: ' . $e->getMessage()], 500);
    }

    responderJson(['exito' => true, 'mensaje' => 'Máquina actualizada.']);
}

if ($metodo === 'DELETE') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idMaquinaria = (int) ($entrada['id_maquinaria'] ?? 0);

    if ($idMaquinaria <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_maquinaria es obligatorio.'], 400);
    }

    // Borrado físico: los repuestos asociados se desvinculan en cascada
    // (MAQUINARIA_REPUESTO), y el historial de mantenimientos/traslados
    // (nivel avanzado 3.2) se borra en cascada también, ya que no tiene
    // sentido conservarlo sin la máquina a la que pertenece.
    $eliminar = $pdo->prepare('DELETE FROM MAQUINARIA WHERE id_maquinaria = :id');
    $eliminar->execute(['id' => $idMaquinaria]);

    if ($eliminar->rowCount() === 0) {
        responderJson(['exito' => false, 'mensaje' => 'Máquina no encontrada.'], 404);
    }

    responderJson(['exito' => true, 'mensaje' => 'Máquina eliminada.']);
}

responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
