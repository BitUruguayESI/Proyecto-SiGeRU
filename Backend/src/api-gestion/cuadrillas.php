<?php
/**
 * api-gestion/cuadrillas.php
 * CRUD de CUADRILLA, con sus rutas habituales (CUADRILLA_RUTA_HABITUAL)
 * y su "cuadrilla estable": 1 camión + 1 chofer + N recolectores
 * (id_camion/id_chofer en CUADRILLA, recolectores en CUADRILLA_RECOLECTOR).
 * Listar: cualquier sesión activa. Crear/editar/eliminar: solo ADMINISTRADOR.
 *
 * GET    ?id=1       -> una cuadrilla (con rutas habituales, camión, chofer y recolectores)
 * GET    ?propia=1   -> la cuadrilla del OPERARIO logueado (donde es chofer o recolector),
 *                       o { cuadrilla: null } si no integra ninguna. Para "Mi día".
 * GET                -> todas las cuadrillas
 * POST   { nombre_cuadrilla, cantidad_integrantes?, rutas_habituales?: [id_ruta,...],
 *          id_camion?, id_chofer?, recolectores?: [id_operario,...] }
 * PUT    { id_cuadrilla, nombre_cuadrilla?, cantidad_integrantes?, rutas_habituales?: [],
 *          id_camion?, id_chofer?, recolectores?: [] }
 * DELETE { id_cuadrilla }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

$pdo = obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];

function cuadrillaConRutas(PDO $pdo, int $idCuadrilla): ?array
{
    $consulta = $pdo->prepare(
        'SELECT cu.*, cam.matricula AS camion_matricula,
                uc.nombre_completo AS chofer_nombre
         FROM CUADRILLA cu
         LEFT JOIN CAMION cam ON cam.id_camion = cu.id_camion
         LEFT JOIN OPERARIO oc ON oc.id_operario = cu.id_chofer
         LEFT JOIN USUARIO uc ON uc.ci = oc.ci_usuario
         WHERE cu.id_cuadrilla = :id'
    );
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

    $recolectores = $pdo->prepare(
        'SELECT o.id_operario, u.nombre_completo
         FROM CUADRILLA_RECOLECTOR cr
         JOIN OPERARIO o ON o.id_operario = cr.id_operario
         JOIN USUARIO u ON u.ci = o.ci_usuario
         WHERE cr.id_cuadrilla = :id
         ORDER BY u.nombre_completo'
    );
    $recolectores->execute(['id' => $idCuadrilla]);
    $cuadrilla['recolectores'] = $recolectores->fetchAll();

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

/**
 * Reemplazo completo de los recolectores de una cuadrilla (mismo patrón
 * que reemplazarRutasHabituales). Antes de insertar valida que cada
 * id_operario exista y tenga cargo RECOLECTOR, para dar un mensaje claro
 * en vez de dejar que falle por la FK o por la UNIQUE de "una sola
 * cuadrilla por recolector".
 */
function reemplazarRecolectoresCuadrilla(PDO $pdo, int $idCuadrilla, array $idsOperario): void
{
    $idsOperario = array_values(array_unique(array_map('intval', $idsOperario)));

    foreach ($idsOperario as $idOperario) {
        if ($idOperario <= 0) {
            continue;
        }
        $consulta = $pdo->prepare(
            'SELECT o.id_operario, u.nombre_completo FROM OPERARIO o
             JOIN USUARIO u ON u.ci = o.ci_usuario
             WHERE o.id_operario = :id AND o.cargo = \'RECOLECTOR\''
        );
        $consulta->execute(['id' => $idOperario]);
        $fila = $consulta->fetch();
        if (!$fila) {
            throw new RuntimeException("El operario #{$idOperario} no existe o no tiene cargo RECOLECTOR.");
        }
        $otraCuadrilla = $pdo->prepare(
            'SELECT cr.id_cuadrilla, cu.nombre_cuadrilla FROM CUADRILLA_RECOLECTOR cr
             JOIN CUADRILLA cu ON cu.id_cuadrilla = cr.id_cuadrilla
             WHERE cr.id_operario = :id AND cr.id_cuadrilla <> :cuadrilla'
        );
        $otraCuadrilla->execute(['id' => $idOperario, 'cuadrilla' => $idCuadrilla]);
        $filaOtra = $otraCuadrilla->fetch();
        if ($filaOtra) {
            throw new RuntimeException(
                "{$fila['nombre_completo']} ya integra la cuadrilla \"{$filaOtra['nombre_cuadrilla']}\"."
            );
        }
    }

    $pdo->prepare('DELETE FROM CUADRILLA_RECOLECTOR WHERE id_cuadrilla = :id')->execute(['id' => $idCuadrilla]);
    $insertar = $pdo->prepare('INSERT INTO CUADRILLA_RECOLECTOR (id_cuadrilla, id_operario) VALUES (:cuadrilla, :operario)');
    foreach ($idsOperario as $idOperario) {
        if ($idOperario > 0) {
            $insertar->execute(['cuadrilla' => $idCuadrilla, 'operario' => $idOperario]);
        }
    }
}

/**
 * Valida id_camion/id_chofer antes de guardarlos: que existan y, en el
 * caso del chofer, que sea un OPERARIO con cargo CHOFER. No valida acá
 * la unicidad (ya la camión/otra cuadrilla) — eso lo cubre la UNIQUE KEY
 * de la base, atrapada más abajo como PDOException 23000.
 */
function validarCamionYChofer(PDO $pdo, ?int $idCamion, ?int $idChofer): void
{
    if ($idCamion !== null) {
        $consulta = $pdo->prepare('SELECT id_camion FROM CAMION WHERE id_camion = :id');
        $consulta->execute(['id' => $idCamion]);
        if (!$consulta->fetch()) {
            throw new RuntimeException('El camión seleccionado no existe.');
        }
    }
    if ($idChofer !== null) {
        $consulta = $pdo->prepare("SELECT id_operario FROM OPERARIO WHERE id_operario = :id AND cargo = 'CHOFER'");
        $consulta->execute(['id' => $idChofer]);
        if (!$consulta->fetch()) {
            throw new RuntimeException('El chofer seleccionado no existe o no tiene cargo CHOFER.');
        }
    }
}

if ($metodo === 'GET') {
    $sesion = requerirSesionActiva();

    if (isset($_GET['id'])) {
        $cuadrilla = cuadrillaConRutas($pdo, (int) $_GET['id']);
        if (!$cuadrilla) {
            responderJson(['exito' => false, 'mensaje' => 'Cuadrilla no encontrada.'], 404);
        }
        responderJson(['exito' => true, 'cuadrilla' => $cuadrilla]);
    }

    if (!empty($_GET['propia'])) {
        $operario = $pdo->prepare('SELECT id_operario FROM OPERARIO WHERE ci_usuario = :ci');
        $operario->execute(['ci' => $sesion['ci_usuario']]);
        $filaOperario = $operario->fetch();
        if (!$filaOperario) {
            responderJson(['exito' => true, 'cuadrilla' => null]); // un Administrador no integra ninguna cuadrilla
        }
        $idOperario = (int) $filaOperario['id_operario'];

        // Dos placeholders distintos (:id1/:id2) aunque el valor sea el
        // mismo: con PDO::ATTR_EMULATE_PREPARES en false (ver Comun/
        // conexion.php) un prepared statement nativo no admite reusar el
        // mismo parámetro con nombre dos veces.
        $buscar = $pdo->prepare(
            'SELECT id_cuadrilla FROM CUADRILLA WHERE id_chofer = :id1
             UNION
             SELECT id_cuadrilla FROM CUADRILLA_RECOLECTOR WHERE id_operario = :id2
             LIMIT 1'
        );
        $buscar->execute(['id1' => $idOperario, 'id2' => $idOperario]);
        $filaCuadrilla = $buscar->fetch();
        $cuadrilla = $filaCuadrilla ? cuadrillaConRutas($pdo, (int) $filaCuadrilla['id_cuadrilla']) : null;
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
    $idCamion = isset($entrada['id_camion']) && $entrada['id_camion'] !== '' && $entrada['id_camion'] !== null
        ? (int) $entrada['id_camion'] : null;
    $idChofer = isset($entrada['id_chofer']) && $entrada['id_chofer'] !== '' && $entrada['id_chofer'] !== null
        ? (int) $entrada['id_chofer'] : null;
    $recolectores = is_array($entrada['recolectores'] ?? null) ? $entrada['recolectores'] : [];

    if ($nombre === '') {
        responderJson(['exito' => false, 'mensaje' => 'nombre_cuadrilla es obligatorio.'], 400);
    }

    try {
        validarCamionYChofer($pdo, $idCamion, $idChofer);

        $pdo->beginTransaction();
        $insertar = $pdo->prepare(
            'INSERT INTO CUADRILLA (nombre_cuadrilla, cantidad_integrantes, id_camion, id_chofer)
             VALUES (:nombre, :cantidad, :camion, :chofer)'
        );
        $insertar->execute(['nombre' => $nombre, 'cantidad' => $cantidad, 'camion' => $idCamion, 'chofer' => $idChofer]);
        $idCuadrilla = (int) $pdo->lastInsertId();

        if ($rutasHabituales) {
            reemplazarRutasHabituales($pdo, $idCuadrilla, $rutasHabituales);
        }
        if ($recolectores) {
            reemplazarRecolectoresCuadrilla($pdo, $idCuadrilla, $recolectores);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ((int) $e->getCode() === 23000) {
            responderJson(['exito' => false, 'mensaje' => 'Ese camión o ese chofer ya están asignados a otra cuadrilla.'], 409);
        }
        responderJson(['exito' => false, 'mensaje' => 'No se pudo registrar la cuadrilla: ' . $e->getMessage()], 500);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        responderJson(['exito' => false, 'mensaje' => $e->getMessage()], 409);
    }

    registrarAuditoria($pdo, "Registró la cuadrilla #{$idCuadrilla} ({$nombre}).");

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

    $idCamion = null;
    if (array_key_exists('id_camion', $entrada)) {
        $idCamion = $entrada['id_camion'] === '' || $entrada['id_camion'] === null ? null : (int) $entrada['id_camion'];
        $campos[] = 'id_camion = :camion';
        $valores['camion'] = $idCamion;
    }
    $idChofer = null;
    if (array_key_exists('id_chofer', $entrada)) {
        $idChofer = $entrada['id_chofer'] === '' || $entrada['id_chofer'] === null ? null : (int) $entrada['id_chofer'];
        $campos[] = 'id_chofer = :chofer';
        $valores['chofer'] = $idChofer;
    }

    try {
        validarCamionYChofer($pdo, $idCamion, $idChofer);

        $pdo->beginTransaction();
        if ($campos) {
            $sql = 'UPDATE CUADRILLA SET ' . implode(', ', $campos) . ' WHERE id_cuadrilla = :id';
            $pdo->prepare($sql)->execute($valores);
        }
        if (isset($entrada['rutas_habituales']) && is_array($entrada['rutas_habituales'])) {
            reemplazarRutasHabituales($pdo, $idCuadrilla, $entrada['rutas_habituales']);
        }
        if (isset($entrada['recolectores']) && is_array($entrada['recolectores'])) {
            reemplazarRecolectoresCuadrilla($pdo, $idCuadrilla, $entrada['recolectores']);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ((int) $e->getCode() === 23000) {
            responderJson(['exito' => false, 'mensaje' => 'Ese camión o ese chofer ya están asignados a otra cuadrilla.'], 409);
        }
        responderJson(['exito' => false, 'mensaje' => 'No se pudo actualizar la cuadrilla: ' . $e->getMessage()], 500);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        responderJson(['exito' => false, 'mensaje' => $e->getMessage()], 409);
    }

    registrarAuditoria($pdo, "Modificó la cuadrilla #{$idCuadrilla}.");

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

    registrarAuditoria($pdo, "Eliminó la cuadrilla #{$idCuadrilla}.");

    responderJson(['exito' => true, 'mensaje' => 'Cuadrilla eliminada.']);
}

responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);