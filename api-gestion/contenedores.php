<?php
/**
 * api-gestion/contenedores.php
 * CRUD de CONTENEDOR (RF07 Registrar, RF08 Modificar estado, RF09 Dar de baja).
 * Solo ADMINISTRADOR puede crear/editar/dar de baja; cualquier USUARIO
 * con sesión activa (Operario o Administrador) puede listar.
 *
 * GET    ?id=1           -> un contenedor (con sus tipos de residuo)
 * GET                     -> todos los contenedores
 * POST   { coordenadas, estado?, nivel_llenado?, id_ruta?, tipos_residuo?: [] }
 * PUT    { id_contenedor, coordenadas?, estado?, nivel_llenado?, id_ruta?, tipos_residuo?: [] }
 * DELETE { id_contenedor }   -> baja lógica: estado = FUERA_DE_SERVICIO
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

$pdo = obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];

function contenedorConTipos(PDO $pdo, int $idContenedor): ?array
{
    $consulta = $pdo->prepare(
        'SELECT c.id_contenedor, c.coordenadas, c.estado, c.nivel_llenado, c.id_ruta, r.nombre_zona
         FROM CONTENEDOR c
         LEFT JOIN RUTA r ON r.id_ruta = c.id_ruta
         WHERE c.id_contenedor = :id'
    );
    $consulta->execute(['id' => $idContenedor]);
    $contenedor = $consulta->fetch();
    if (!$contenedor) {
        return null;
    }
    $tipos = $pdo->prepare('SELECT tipo_residuo FROM CONTENEDOR_TIPO_RESIDUO WHERE id_contenedor = :id');
    $tipos->execute(['id' => $idContenedor]);
    $contenedor['tipos_residuo'] = array_column($tipos->fetchAll(), 'tipo_residuo');
    return $contenedor;
}

if ($metodo === 'GET') {
    requerirSesionActiva();

    if (isset($_GET['id'])) {
        $contenedor = contenedorConTipos($pdo, (int) $_GET['id']);
        if (!$contenedor) {
            responderJson(['exito' => false, 'mensaje' => 'Contenedor no encontrado.'], 404);
        }
        responderJson(['exito' => true, 'contenedor' => $contenedor]);
    }

    $ids = $pdo->query('SELECT id_contenedor FROM CONTENEDOR ORDER BY id_contenedor')->fetchAll();
    $lista = array_map(fn($fila) => contenedorConTipos($pdo, (int) $fila['id_contenedor']), $ids);
    responderJson(['exito' => true, 'contenedores' => $lista]);
}

if ($metodo === 'POST') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();

    $coordenadas   = limpiarTexto($entrada['coordenadas'] ?? '');
    $estado        = limpiarTexto($entrada['estado'] ?? 'OPERATIVO');
    $nivelLlenado  = (int) ($entrada['nivel_llenado'] ?? 0);
    $idRuta        = isset($entrada['id_ruta']) && $entrada['id_ruta'] !== '' ? (int) $entrada['id_ruta'] : null;
    $tiposResiduo  = is_array($entrada['tipos_residuo'] ?? null) ? $entrada['tipos_residuo'] : [];

    if ($coordenadas === '') {
        responderJson(['exito' => false, 'mensaje' => 'Las coordenadas son obligatorias.'], 400);
    }
    if (!in_array($estado, ['OPERATIVO', 'DESBORDADO', 'FUERA_DE_SERVICIO'], true)) {
        responderJson(['exito' => false, 'mensaje' => 'Estado inválido.'], 400);
    }
    if ($nivelLlenado < 0 || $nivelLlenado > 100) {
        responderJson(['exito' => false, 'mensaje' => 'El nivel de llenado debe estar entre 0 y 100.'], 400);
    }

    try {
        $pdo->beginTransaction();

        $insertar = $pdo->prepare(
            'INSERT INTO CONTENEDOR (coordenadas, estado, nivel_llenado, id_ruta)
             VALUES (:coordenadas, :estado, :nivel_llenado, :id_ruta)'
        );
        $insertar->execute([
            'coordenadas'   => $coordenadas,
            'estado'        => $estado,
            'nivel_llenado' => $nivelLlenado,
            'id_ruta'       => $idRuta,
        ]);
        $idContenedor = (int) $pdo->lastInsertId();

        if ($tiposResiduo) {
            $insertarTipo = $pdo->prepare(
                'INSERT INTO CONTENEDOR_TIPO_RESIDUO (id_contenedor, tipo_residuo) VALUES (:id, :tipo)'
            );
            foreach ($tiposResiduo as $tipo) {
                $tipo = limpiarTexto((string) $tipo);
                if ($tipo !== '') {
                    $insertarTipo->execute(['id' => $idContenedor, 'tipo' => $tipo]);
                }
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        responderJson(['exito' => false, 'mensaje' => 'No se pudo registrar el contenedor: ' . $e->getMessage()], 500);
    }

    responderJson(['exito' => true, 'mensaje' => 'Contenedor registrado.', 'id_contenedor' => $idContenedor], 201);
}

if ($metodo === 'PUT') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idContenedor = (int) ($entrada['id_contenedor'] ?? 0);

    if ($idContenedor <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_contenedor es obligatorio.'], 400);
    }

    $campos = [];
    $valores = ['id' => $idContenedor];

    if (isset($entrada['coordenadas'])) {
        $campos[] = 'coordenadas = :coordenadas';
        $valores['coordenadas'] = limpiarTexto($entrada['coordenadas']);
    }
    if (isset($entrada['estado'])) {
        $estado = limpiarTexto($entrada['estado']);
        if (!in_array($estado, ['OPERATIVO', 'DESBORDADO', 'FUERA_DE_SERVICIO'], true)) {
            responderJson(['exito' => false, 'mensaje' => 'Estado inválido.'], 400);
        }
        $campos[] = 'estado = :estado';
        $valores['estado'] = $estado;
    }
    if (isset($entrada['nivel_llenado'])) {
        $nivel = (int) $entrada['nivel_llenado'];
        if ($nivel < 0 || $nivel > 100) {
            responderJson(['exito' => false, 'mensaje' => 'El nivel de llenado debe estar entre 0 y 100.'], 400);
        }
        $campos[] = 'nivel_llenado = :nivel_llenado';
        $valores['nivel_llenado'] = $nivel;
    }
    if (array_key_exists('id_ruta', $entrada)) {
        $campos[] = 'id_ruta = :id_ruta';
        $valores['id_ruta'] = $entrada['id_ruta'] === '' || $entrada['id_ruta'] === null ? null : (int) $entrada['id_ruta'];
    }

    try {
        $pdo->beginTransaction();

        if ($campos) {
            $sql = 'UPDATE CONTENEDOR SET ' . implode(', ', $campos) . ' WHERE id_contenedor = :id';
            $pdo->prepare($sql)->execute($valores);
        }

        if (isset($entrada['tipos_residuo']) && is_array($entrada['tipos_residuo'])) {
            $pdo->prepare('DELETE FROM CONTENEDOR_TIPO_RESIDUO WHERE id_contenedor = :id')->execute(['id' => $idContenedor]);
            $insertarTipo = $pdo->prepare(
                'INSERT INTO CONTENEDOR_TIPO_RESIDUO (id_contenedor, tipo_residuo) VALUES (:id, :tipo)'
            );
            foreach ($entrada['tipos_residuo'] as $tipo) {
                $tipo = limpiarTexto((string) $tipo);
                if ($tipo !== '') {
                    $insertarTipo->execute(['id' => $idContenedor, 'tipo' => $tipo]);
                }
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        responderJson(['exito' => false, 'mensaje' => 'No se pudo actualizar el contenedor: ' . $e->getMessage()], 500);
    }

    responderJson(['exito' => true, 'mensaje' => 'Contenedor actualizado.']);
}

if ($metodo === 'DELETE') {
    requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idContenedor = (int) ($entrada['id_contenedor'] ?? 0);

    if ($idContenedor <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_contenedor es obligatorio.'], 400);
    }

    // Baja lógica (no se elimina físicamente: preserva el historial de
    // incidencias asociadas). RF09 "dar de baja" = pasar a FUERA_DE_SERVICIO.
    $actualizar = $pdo->prepare("UPDATE CONTENEDOR SET estado = 'FUERA_DE_SERVICIO' WHERE id_contenedor = :id");
    $actualizar->execute(['id' => $idContenedor]);

    if ($actualizar->rowCount() === 0) {
        responderJson(['exito' => false, 'mensaje' => 'Contenedor no encontrado.'], 404);
    }

    responderJson(['exito' => true, 'mensaje' => 'Contenedor dado de baja.']);
}

responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
