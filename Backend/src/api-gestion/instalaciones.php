<?php
/**
 * api-gestion/instalaciones.php
 * CRUD de INSTALACION (RF10 Registrar centro de acopio, RF11 Registrar vertedero).
 * tipo_instalacion distingue cuál de las dos es.
 * Listar: cualquier sesión activa. Crear/editar/eliminar: solo ADMINISTRADOR.
 *
 * GET    ?id=1   -> una instalación
 * GET             -> todas las instalaciones
 * POST   { nombre, coordenadas, capacidad_maxima, residuos_aceptados, tipo_instalacion }
 * PUT    { id_centro, ...campos a cambiar... }
 * DELETE { id_centro }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

$pdo = obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    requerirSesionActiva();

    if (isset($_GET['id'])) {
        $consulta = $pdo->prepare('SELECT * FROM INSTALACION WHERE id_centro = :id');
        $consulta->execute(['id' => (int) $_GET['id']]);
        $instalacion = $consulta->fetch();
        if (!$instalacion) {
            responderJson(['exito' => false, 'mensaje' => 'Instalación no encontrada.'], 404);
        }
        responderJson(['exito' => true, 'instalacion' => $instalacion]);
    }

    $instalaciones = $pdo->query('SELECT * FROM INSTALACION ORDER BY id_centro')->fetchAll();
    responderJson(['exito' => true, 'instalaciones' => $instalaciones]);
}

if ($metodo === 'POST') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();

    $nombre = limpiarTexto($entrada['nombre'] ?? '');
    $coordenadas = limpiarTexto($entrada['coordenadas'] ?? '');
    $capacidadMaxima = (float) ($entrada['capacidad_maxima'] ?? 0);
    $residuosAceptados = limpiarTexto($entrada['residuos_aceptados'] ?? '');
    $tipoInstalacion = strtoupper(limpiarTexto($entrada['tipo_instalacion'] ?? ''));

    $errores = [];
    if ($nombre === '') $errores[] = 'El nombre es obligatorio.';
    if ($coordenadas === '') $errores[] = 'Las coordenadas son obligatorias.';
    if ($capacidadMaxima <= 0) $errores[] = 'La capacidad máxima debe ser mayor a 0.';
    if ($residuosAceptados === '') $errores[] = 'Los residuos aceptados son obligatorios.';
    if (!in_array($tipoInstalacion, ['CENTRO_ACOPIO', 'VERTEDERO'], true)) {
        $errores[] = 'tipo_instalacion debe ser CENTRO_ACOPIO o VERTEDERO.';
    }
    if ($errores) {
        responderJson(['exito' => false, 'mensaje' => implode(' ', $errores)], 400);
    }

    $insertar = $pdo->prepare(
        'INSERT INTO INSTALACION (nombre, coordenadas, capacidad_maxima, residuos_aceptados, tipo_instalacion)
         VALUES (:nombre, :coordenadas, :capacidad, :residuos, :tipo)'
    );
    $insertar->execute([
        'nombre'      => $nombre,
        'coordenadas' => $coordenadas,
        'capacidad'   => $capacidadMaxima,
        'residuos'    => $residuosAceptados,
        'tipo'        => $tipoInstalacion,
    ]);

    responderJson(['exito' => true, 'mensaje' => 'Instalación registrada.', 'id_centro' => (int) $pdo->lastInsertId()], 201);
}

if ($metodo === 'PUT') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idCentro = (int) ($entrada['id_centro'] ?? 0);

    if ($idCentro <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_centro es obligatorio.'], 400);
    }

    $mapaCampos = [
        'nombre' => 'nombre', 'coordenadas' => 'coordenadas',
        'capacidad_maxima' => 'capacidad_maxima', 'residuos_aceptados' => 'residuos_aceptados',
        'tipo_instalacion' => 'tipo_instalacion',
    ];
    $campos = [];
    $valores = ['id' => $idCentro];

    foreach ($mapaCampos as $clave => $columna) {
        if (!isset($entrada[$clave])) continue;
        if ($columna === 'tipo_instalacion' && !in_array(strtoupper($entrada[$clave]), ['CENTRO_ACOPIO', 'VERTEDERO'], true)) {
            responderJson(['exito' => false, 'mensaje' => 'tipo_instalacion debe ser CENTRO_ACOPIO o VERTEDERO.'], 400);
        }
        $campos[] = "$columna = :$columna";
        $valores[$columna] = is_string($entrada[$clave]) ? limpiarTexto($entrada[$clave]) : $entrada[$clave];
    }

    if (!$campos) {
        responderJson(['exito' => false, 'mensaje' => 'No hay campos para actualizar.'], 400);
    }

    $sql = 'UPDATE INSTALACION SET ' . implode(', ', $campos) . ' WHERE id_centro = :id';
    $pdo->prepare($sql)->execute($valores);

    responderJson(['exito' => true, 'mensaje' => 'Instalación actualizada.']);
}

if ($metodo === 'DELETE') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idCentro = (int) ($entrada['id_centro'] ?? 0);

    if ($idCentro <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_centro es obligatorio.'], 400);
    }

    try {
        $eliminar = $pdo->prepare('DELETE FROM INSTALACION WHERE id_centro = :id');
        $eliminar->execute(['id' => $idCentro]);
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            responderJson(['exito' => false, 'mensaje' => 'No se puede eliminar: tiene maquinaria o descargas registradas.'], 409);
        }
        throw $e;
    }

    if ($eliminar->rowCount() === 0) {
        responderJson(['exito' => false, 'mensaje' => 'Instalación no encontrada.'], 404);
    }

    responderJson(['exito' => true, 'mensaje' => 'Instalación eliminada.']);
}

responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
