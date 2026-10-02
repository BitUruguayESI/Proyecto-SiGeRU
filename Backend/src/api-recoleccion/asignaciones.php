<?php
/**
 * api-recoleccion/asignaciones.php
 * RF23 (crear asignación diaria) y RF25 (consultar rutas asignadas).
 * Una asignación diaria junta cuadrilla + ruta + camión + administrador
 * que la creó, y una lista de operarios con su rol (chofer/recolector)
 * vía ASIGNACION_OPERARIO.
 *
 * GET    ?fecha=&id_cuadrilla=&id_ruta=&id_camion=&mias=1   (todos opcionales)
 *        "mias=1" filtra solo las asignaciones donde participa el
 *        OPERARIO logueado (RF25 desde su propia perspectiva).
 * POST   { fecha, turno, id_cuadrilla, id_ruta, id_camion,
 *          operarios: [{ id_operario, rol_en_asignacion }] }   — solo Administrador
 * PUT    { id_asignacion, fecha, turno, id_cuadrilla, id_ruta, id_camion,
 *          operarios: [{ id_operario, rol_en_asignacion }] }   — solo Administrador
 *          (reemplaza la lista de operarios completa, igual que el POST)
 * DELETE { id_asignacion }   — solo Administrador
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

$pdo = obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];
$turnosValidos = ['MANANA', 'TARDE', 'NOCTURNO'];

function asignacionConOperarios(PDO $pdo, int $idAsignacion): ?array
{
    $consulta = $pdo->prepare(
        'SELECT a.id_asignacion, a.fecha, a.turno,
                a.id_cuadrilla, cu.nombre_cuadrilla,
                a.id_ruta, r.nombre_zona,
                a.id_camion, c.matricula,
                a.id_administrador, u.nombre_completo AS administrador
         FROM ASIGNACION_DIARIA a
         JOIN CUADRILLA cu ON cu.id_cuadrilla = a.id_cuadrilla
         JOIN RUTA r ON r.id_ruta = a.id_ruta
         JOIN CAMION c ON c.id_camion = a.id_camion
         JOIN ADMINISTRADOR ad ON ad.id_admin = a.id_administrador
         JOIN USUARIO u ON u.ci = ad.ci_usuario
         WHERE a.id_asignacion = :id'
    );
    $consulta->execute(['id' => $idAsignacion]);
    $asignacion = $consulta->fetch();
    if (!$asignacion) {
        return null;
    }
    $operarios = $pdo->prepare(
        'SELECT ao.id_operario, ao.rol_en_asignacion, u.nombre_completo
         FROM ASIGNACION_OPERARIO ao
         JOIN OPERARIO o ON o.id_operario = ao.id_operario
         JOIN USUARIO u ON u.ci = o.ci_usuario
         WHERE ao.id_asignacion = :id'
    );
    $operarios->execute(['id' => $idAsignacion]);
    $asignacion['operarios'] = $operarios->fetchAll();
    return $asignacion;
}

if ($metodo === 'GET') {
    $sesion = requerirSesionActiva();

    $condiciones = [];
    $valores = [];

    if (!empty($_GET['fecha'])) { $condiciones[] = 'a.fecha = :fecha'; $valores['fecha'] = $_GET['fecha']; }
    if (!empty($_GET['id_cuadrilla'])) { $condiciones[] = 'a.id_cuadrilla = :id_cuadrilla'; $valores['id_cuadrilla'] = (int) $_GET['id_cuadrilla']; }
    if (!empty($_GET['id_ruta'])) { $condiciones[] = 'a.id_ruta = :id_ruta'; $valores['id_ruta'] = (int) $_GET['id_ruta']; }
    if (!empty($_GET['id_camion'])) { $condiciones[] = 'a.id_camion = :id_camion'; $valores['id_camion'] = (int) $_GET['id_camion']; }

    $sql = 'SELECT DISTINCT a.id_asignacion FROM ASIGNACION_DIARIA a';
    if (!empty($_GET['mias'])) {
        $operario = $pdo->prepare('SELECT id_operario FROM OPERARIO WHERE ci_usuario = :ci');
        $operario->execute(['ci' => $sesion['ci_usuario']]);
        $filaOperario = $operario->fetch();
        if (!$filaOperario) {
            responderJson(['exito' => true, 'asignaciones' => []]); // un Administrador no tiene "mis asignaciones"
        }
        $sql .= ' JOIN ASIGNACION_OPERARIO ao ON ao.id_asignacion = a.id_asignacion';
        $condiciones[] = 'ao.id_operario = :id_operario_sesion';
        $valores['id_operario_sesion'] = (int) $filaOperario['id_operario'];
    }
    if ($condiciones) {
        $sql .= ' WHERE ' . implode(' AND ', $condiciones);
    }
    $sql .= ' ORDER BY a.id_asignacion DESC';

    $ids = $pdo->prepare($sql);
    $ids->execute($valores);
    $lista = array_map(fn($fila) => asignacionConOperarios($pdo, (int) $fila['id_asignacion']), $ids->fetchAll());

    responderJson(['exito' => true, 'asignaciones' => $lista]);
}

if ($metodo === 'POST') {
    $sesion = requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();

    $fecha = limpiarTexto($entrada['fecha'] ?? '');
    $turno = strtoupper(limpiarTexto($entrada['turno'] ?? ''));
    $idCuadrilla = (int) ($entrada['id_cuadrilla'] ?? 0);
    $idRuta = (int) ($entrada['id_ruta'] ?? 0);
    $idCamion = (int) ($entrada['id_camion'] ?? 0);
    $operarios = is_array($entrada['operarios'] ?? null) ? $entrada['operarios'] : [];

    $errores = [];
    if ($fecha === '') $errores[] = 'La fecha es obligatoria.';
    if (!in_array($turno, $turnosValidos, true)) $errores[] = 'Turno inválido.';
    if ($idCuadrilla <= 0) $errores[] = 'Tenés que elegir una cuadrilla.';
    if ($idRuta <= 0) $errores[] = 'Tenés que elegir una ruta.';
    if ($idCamion <= 0) $errores[] = 'Tenés que elegir un camión.';
    if ($errores) {
        responderJson(['exito' => false, 'mensaje' => implode(' ', $errores)], 400);
    }

    // Resolver id_administrador a partir del usuario logueado.
    $admin = $pdo->prepare('SELECT id_admin FROM ADMINISTRADOR WHERE ci_usuario = :ci');
    $admin->execute(['ci' => $sesion['ci_usuario']]);
    $filaAdmin = $admin->fetch();
    if (!$filaAdmin) {
        responderJson(['exito' => false, 'mensaje' => 'No se encontró el registro de Administrador de tu usuario.'], 500);
    }
    $idAdministrador = (int) $filaAdmin['id_admin'];

    try {
        $pdo->beginTransaction();

        $insertar = $pdo->prepare(
            'INSERT INTO ASIGNACION_DIARIA (fecha, turno, id_administrador, id_cuadrilla, id_ruta, id_camion)
             VALUES (:fecha, :turno, :admin, :cuadrilla, :ruta, :camion)'
        );
        $insertar->execute([
            'fecha' => $fecha, 'turno' => $turno, 'admin' => $idAdministrador,
            'cuadrilla' => $idCuadrilla, 'ruta' => $idRuta, 'camion' => $idCamion,
        ]);
        $idAsignacion = (int) $pdo->lastInsertId();

        if ($operarios) {
            $insertarOperario = $pdo->prepare(
                'INSERT INTO ASIGNACION_OPERARIO (id_asignacion, id_operario, rol_en_asignacion)
                 VALUES (:asignacion, :operario, :rol)'
            );
            foreach ($operarios as $op) {
                $idOperario = (int) ($op['id_operario'] ?? 0);
                $rol = limpiarTexto($op['rol_en_asignacion'] ?? '');
                if ($idOperario > 0 && $rol !== '') {
                    $insertarOperario->execute(['asignacion' => $idAsignacion, 'operario' => $idOperario, 'rol' => $rol]);
                }
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        responderJson(['exito' => false, 'mensaje' => 'No se pudo crear la asignación: ' . $e->getMessage()], 500);
    }

    responderJson(['exito' => true, 'mensaje' => 'Asignación diaria creada.', 'id_asignacion' => $idAsignacion], 201);
}

if ($metodo === 'PUT') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();

    $idAsignacion = (int) ($entrada['id_asignacion'] ?? 0);
    if ($idAsignacion <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_asignacion es obligatorio.'], 400);
    }

    $existente = $pdo->prepare('SELECT id_asignacion FROM ASIGNACION_DIARIA WHERE id_asignacion = :id');
    $existente->execute(['id' => $idAsignacion]);
    if (!$existente->fetch()) {
        responderJson(['exito' => false, 'mensaje' => 'Asignación no encontrada.'], 404);
    }

    $fecha = limpiarTexto($entrada['fecha'] ?? '');
    $turno = strtoupper(limpiarTexto($entrada['turno'] ?? ''));
    $idCuadrilla = (int) ($entrada['id_cuadrilla'] ?? 0);
    $idRuta = (int) ($entrada['id_ruta'] ?? 0);
    $idCamion = (int) ($entrada['id_camion'] ?? 0);
    $operarios = is_array($entrada['operarios'] ?? null) ? $entrada['operarios'] : [];

    $errores = [];
    if ($fecha === '') $errores[] = 'La fecha es obligatoria.';
    if (!in_array($turno, $turnosValidos, true)) $errores[] = 'Turno inválido.';
    if ($idCuadrilla <= 0) $errores[] = 'Tenés que elegir una cuadrilla.';
    if ($idRuta <= 0) $errores[] = 'Tenés que elegir una ruta.';
    if ($idCamion <= 0) $errores[] = 'Tenés que elegir un camión.';
    if ($errores) {
        responderJson(['exito' => false, 'mensaje' => implode(' ', $errores)], 400);
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare(
            'UPDATE ASIGNACION_DIARIA
                SET fecha = :fecha, turno = :turno, id_cuadrilla = :cuadrilla,
                    id_ruta = :ruta, id_camion = :camion
              WHERE id_asignacion = :id'
        )->execute([
            'fecha' => $fecha, 'turno' => $turno, 'cuadrilla' => $idCuadrilla,
            'ruta' => $idRuta, 'camion' => $idCamion, 'id' => $idAsignacion,
        ]);

        // Reemplazo total de la lista de operarios (mismo patrón que el POST).
        $pdo->prepare('DELETE FROM ASIGNACION_OPERARIO WHERE id_asignacion = :id')->execute(['id' => $idAsignacion]);

        if ($operarios) {
            $insertarOperario = $pdo->prepare(
                'INSERT INTO ASIGNACION_OPERARIO (id_asignacion, id_operario, rol_en_asignacion)
                 VALUES (:asignacion, :operario, :rol)'
            );
            foreach ($operarios as $op) {
                $idOperario = (int) ($op['id_operario'] ?? 0);
                $rol = limpiarTexto($op['rol_en_asignacion'] ?? '');
                if ($idOperario > 0 && $rol !== '') {
                    $insertarOperario->execute(['asignacion' => $idAsignacion, 'operario' => $idOperario, 'rol' => $rol]);
                }
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        responderJson(['exito' => false, 'mensaje' => 'No se pudo actualizar la asignación: ' . $e->getMessage()], 500);
    }

    responderJson(['exito' => true, 'mensaje' => 'Asignación diaria actualizada.']);
}

if ($metodo === 'DELETE') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idAsignacion = (int) ($entrada['id_asignacion'] ?? 0);

    if ($idAsignacion <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_asignacion es obligatorio.'], 400);
    }

    $eliminar = $pdo->prepare('DELETE FROM ASIGNACION_DIARIA WHERE id_asignacion = :id');
    $eliminar->execute(['id' => $idAsignacion]);

    if ($eliminar->rowCount() === 0) {
        responderJson(['exito' => false, 'mensaje' => 'Asignación no encontrada.'], 404);
    }

    responderJson(['exito' => true, 'mensaje' => 'Asignación eliminada.']);
}

responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
