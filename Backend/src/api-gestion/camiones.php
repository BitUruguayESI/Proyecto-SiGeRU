<?php
/**
 * api-gestion/camiones.php
 * CRUD de CAMION (RF26 Registrar camión).
 * Listar: cualquier sesión activa. Crear/editar/dar de baja: solo ADMINISTRADOR.
 *
 * GET    ?id=1   -> un camión
 * GET             -> todos los camiones
 * POST   { matricula, marca, modelo, capacidad_carga, estado?, kilometraje? }
 * PUT    { id_camion, ...campos a cambiar... }
 * DELETE { id_camion }   -> baja lógica: estado = FUERA_DE_SERVICIO
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

$pdo = obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];

$estadosValidos = ['DISPONIBLE', 'EN_RUTA', 'EN_MANTENIMIENTO', 'FUERA_DE_SERVICIO'];

if ($metodo === 'GET') {
    requerirSesionActiva();

    if (isset($_GET['id'])) {
        $consulta = $pdo->prepare('SELECT * FROM CAMION WHERE id_camion = :id');
        $consulta->execute(['id' => (int) $_GET['id']]);
        $camion = $consulta->fetch();
        if (!$camion) {
            responderJson(['exito' => false, 'mensaje' => 'Camión no encontrado.'], 404);
        }
        responderJson(['exito' => true, 'camion' => $camion]);
    }

    $camiones = $pdo->query('SELECT * FROM CAMION ORDER BY id_camion')->fetchAll();
    responderJson(['exito' => true, 'camiones' => $camiones]);
}

if ($metodo === 'POST') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();

    $matricula = limpiarTexto($entrada['matricula'] ?? '');
    $marca = limpiarTexto($entrada['marca'] ?? '');
    $modelo = limpiarTexto($entrada['modelo'] ?? '');
    $capacidad = (float) ($entrada['capacidad_carga'] ?? 0);
    $estado = limpiarTexto($entrada['estado'] ?? 'DISPONIBLE');
    $kilometraje = (int) ($entrada['kilometraje'] ?? 0);

    $errores = [];
    if ($matricula === '') $errores[] = 'La matrícula es obligatoria.';
    if ($marca === '') $errores[] = 'La marca es obligatoria.';
    if ($modelo === '') $errores[] = 'El modelo es obligatorio.';
    if ($capacidad <= 0) $errores[] = 'La capacidad de carga debe ser mayor a 0.';
    if (!in_array($estado, $estadosValidos, true)) $errores[] = 'Estado inválido.';
    if ($errores) {
        responderJson(['exito' => false, 'mensaje' => implode(' ', $errores)], 400);
    }

    $duplicado = $pdo->prepare('SELECT id_camion FROM CAMION WHERE matricula = :matricula');
    $duplicado->execute(['matricula' => $matricula]);
    if ($duplicado->fetch()) {
        responderJson(['exito' => false, 'mensaje' => 'Ya existe un camión con esa matrícula.'], 409);
    }

    $insertar = $pdo->prepare(
        'INSERT INTO CAMION (matricula, marca, modelo, capacidad_carga, estado, kilometraje)
         VALUES (:matricula, :marca, :modelo, :capacidad, :estado, :kilometraje)'
    );
    $insertar->execute([
        'matricula'   => $matricula,
        'marca'       => $marca,
        'modelo'      => $modelo,
        'capacidad'   => $capacidad,
        'estado'      => $estado,
        'kilometraje' => $kilometraje,
    ]);

    responderJson(['exito' => true, 'mensaje' => 'Camión registrado.', 'id_camion' => (int) $pdo->lastInsertId()], 201);
}

if ($metodo === 'PUT') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idCamion = (int) ($entrada['id_camion'] ?? 0);

    if ($idCamion <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_camion es obligatorio.'], 400);
    }

    $mapaCampos = [
        'matricula' => 'matricula', 'marca' => 'marca', 'modelo' => 'modelo',
        'capacidad_carga' => 'capacidad_carga', 'estado' => 'estado',
        'kilometraje' => 'kilometraje', 'fecha_ultimo_mantenimiento' => 'fecha_ultimo_mantenimiento',
    ];
    $campos = [];
    $valores = ['id' => $idCamion];

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

    $sql = 'UPDATE CAMION SET ' . implode(', ', $campos) . ' WHERE id_camion = :id';
    $pdo->prepare($sql)->execute($valores);

    responderJson(['exito' => true, 'mensaje' => 'Camión actualizado.']);
}

if ($metodo === 'DELETE') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idCamion = (int) ($entrada['id_camion'] ?? 0);

    if ($idCamion <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_camion es obligatorio.'], 400);
    }

    // Baja lógica: preserva el historial de descargas y mantenimientos.
    $actualizar = $pdo->prepare("UPDATE CAMION SET estado = 'FUERA_DE_SERVICIO' WHERE id_camion = :id");
    $actualizar->execute(['id' => $idCamion]);

    if ($actualizar->rowCount() === 0) {
        responderJson(['exito' => false, 'mensaje' => 'Camión no encontrado.'], 404);
    }

    responderJson(['exito' => true, 'mensaje' => 'Camión dado de baja.']);
}

responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
