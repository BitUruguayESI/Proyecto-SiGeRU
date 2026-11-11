<?php
/**
 * api-gestion/contenedores.php
 * CRUD de CONTENEDOR (RF07 Registrar, RF08 Modificar estado, RF09 Dar de baja).
 * Solo ADMINISTRADOR puede crear/editar/dar de baja; cualquier USUARIO
 * con sesión activa (Operario o Administrador) puede listar.
 *
 * El alta/edición se hace con una dirección de texto ("direccion"), no con
 * coordenadas a mano: acá se geocodifica automáticamente contra OpenStreetMap
 * (Nominatim) para completar "coordenadas" y poder mostrar el contenedor en
 * el mapa interactivo (RF13). Si no hay internet o no se encuentra la
 * dirección, igual se guarda el contenedor (con coordenadas NULL) y se avisa
 * en el mensaje — no bloquea el alta.
 *
 * GET    ?id=1           -> un contenedor (con sus tipos de residuo)
 * GET    ?resumen=1      -> solo totales (total + cantidad por estado), sin
 *                           traer la lista completa — para tarjetas/contadores
 *                           que no necesitan el detalle de cada contenedor
 *                           (ver Dashboard: con miles de contenedores reales
 *                           cargados, traer la lista entera solo para contar
 *                           era un desperdicio de ancho de banda).
 * GET                     -> todos los contenedores
 * GET    ?id_ruta=1,2     -> solo los contenedores de esas rutas (para el mapa de "Mi día")
 * GET    ?ids=5,9         -> solo esos contenedores
 * POST   { direccion, estado?, nivel_llenado?, id_ruta?, tipos_residuo?: [] }
 * PUT    { id_contenedor, direccion?, estado?, nivel_llenado?, id_ruta?, tipos_residuo?: [] }
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
        'SELECT c.id_contenedor, c.direccion, c.coordenadas, c.estado, c.nivel_llenado, c.id_ruta,
                r.nombre_zona, r.municipio
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

    if (isset($_GET['resumen'])) {
        $porEstado = ['OPERATIVO' => 0, 'DESBORDADO' => 0, 'FUERA_DE_SERVICIO' => 0];
        $total = 0;
        foreach ($pdo->query('SELECT estado, COUNT(*) AS cantidad FROM CONTENEDOR GROUP BY estado') as $fila) {
            $porEstado[$fila['estado']] = (int) $fila['cantidad'];
            $total += (int) $fila['cantidad'];
        }
        responderJson(['exito' => true, 'total' => $total, 'por_estado' => $porEstado]);
    }

    if (isset($_GET['id'])) {
        $contenedor = contenedorConTipos($pdo, (int) $_GET['id']);
        if (!$contenedor) {
            responderJson(['exito' => false, 'mensaje' => 'Contenedor no encontrado.'], 404);
        }
        responderJson(['exito' => true, 'contenedor' => $contenedor]);
    }

    // Antes esto hacia 2 consultas POR contenedor (una por tipos_residuo),
    // lo que con los ~11.000 contenedores reales cargados del CSV
    // municipal eran mas de 20.000 consultas seguidas: tardaba tanto que
    // a veces el servidor cortaba la respuesta a la mitad (PHP devolvia
    // un error de "tiempo maximo excedido" pegado al JSON, y el frontend
    // fallaba al parsearlo). Ahora son solo 2 consultas en total.
    // Filtros opcionales (listas de enteros separadas por coma). Se castean
    // a int antes de armar el IN (...), así no hay riesgo de inyección.
    $filtrosSql = [];
    foreach (['id_ruta' => 'c.id_ruta', 'ids' => 'c.id_contenedor'] as $parametro => $columna) {
        if (isset($_GET[$parametro])) {
            $numeros = array_values(array_filter(
                array_map('intval', explode(',', (string) $_GET[$parametro])),
                fn($n) => $n > 0
            ));
            $filtrosSql[] = $numeros ? "{$columna} IN (" . implode(',', $numeros) . ')' : '1 = 0';
        }
    }
    $donde = $filtrosSql ? ' WHERE ' . implode(' AND ', $filtrosSql) : '';

    $filas = $pdo->query(
        'SELECT c.id_contenedor, c.direccion, c.coordenadas, c.estado, c.nivel_llenado, c.id_ruta,
                r.nombre_zona, r.municipio
         FROM CONTENEDOR c
         LEFT JOIN RUTA r ON r.id_ruta = c.id_ruta' . $donde . '
         ORDER BY c.id_contenedor'
    )->fetchAll();

    $sqlTipos = 'SELECT id_contenedor, tipo_residuo FROM CONTENEDOR_TIPO_RESIDUO';
    if ($filtrosSql) {
        $idsFilas = array_map('intval', array_column($filas, 'id_contenedor'));
        $sqlTipos .= $idsFilas ? ' WHERE id_contenedor IN (' . implode(',', $idsFilas) . ')' : ' WHERE 1 = 0';
    }
    $tiposPorContenedor = [];
    foreach ($pdo->query($sqlTipos) as $fila) {
        $tiposPorContenedor[$fila['id_contenedor']][] = $fila['tipo_residuo'];
    }

    $lista = array_map(function ($c) use ($tiposPorContenedor) {
        $c['tipos_residuo'] = $tiposPorContenedor[$c['id_contenedor']] ?? [];
        return $c;
    }, $filas);

    responderJson(['exito' => true, 'contenedores' => $lista]);
}

if ($metodo === 'POST') {
    $sesion = requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();

    $direccion     = limpiarTexto($entrada['direccion'] ?? '');
    $estado        = limpiarTexto($entrada['estado'] ?? 'OPERATIVO');
    $nivelLlenado  = (int) ($entrada['nivel_llenado'] ?? 0);
    $idRuta        = isset($entrada['id_ruta']) && $entrada['id_ruta'] !== '' ? (int) $entrada['id_ruta'] : null;
    $tiposResiduo  = is_array($entrada['tipos_residuo'] ?? null) ? $entrada['tipos_residuo'] : [];

    if ($direccion === '') {
        responderJson(['exito' => false, 'mensaje' => 'La dirección es obligatoria.'], 400);
    }
    if (!in_array($estado, ['OPERATIVO', 'DESBORDADO', 'FUERA_DE_SERVICIO'], true)) {
        responderJson(['exito' => false, 'mensaje' => 'Estado inválido.'], 400);
    }
    if ($nivelLlenado < 0 || $nivelLlenado > 100) {
        responderJson(['exito' => false, 'mensaje' => 'El nivel de llenado debe estar entre 0 y 100.'], 400);
    }

    $geo = geocodificarDireccion($direccion);
    $coordenadas = $geo ? "{$geo['lat']},{$geo['lon']}" : null;

    try {
        $pdo->beginTransaction();

        $insertar = $pdo->prepare(
            'INSERT INTO CONTENEDOR (direccion, coordenadas, estado, nivel_llenado, id_ruta)
             VALUES (:direccion, :coordenadas, :estado, :nivel_llenado, :id_ruta)'
        );
        $insertar->execute([
            'direccion'     => $direccion,
            'coordenadas'   => $coordenadas,
            'estado'        => $estado,
            'nivel_llenado' => $nivelLlenado,
            'id_ruta'       => $idRuta,
        ]);
        $idContenedor = (int) $pdo->lastInsertId();

        // Primera lectura del historial de llenado (nivel avanzado 3.4:
        // predicciones) — ver LECTURA_NIVEL_CONTENEDOR.
        $pdo->prepare('INSERT INTO LECTURA_NIVEL_CONTENEDOR (id_contenedor, nivel_llenado) VALUES (:id, :nivel)')
            ->execute(['id' => $idContenedor, 'nivel' => $nivelLlenado]);

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

    registrarAuditoria($pdo, "Registró el contenedor #{$idContenedor} ({$direccion}).");

    $mensaje = $coordenadas
        ? 'Contenedor registrado y ubicado en el mapa.'
        : 'Contenedor registrado, pero no se pudo ubicar la dirección en el mapa automáticamente (revisá que esté completa, o que haya conexión a internet). Podés reintentar editándolo.';
    responderJson(['exito' => true, 'mensaje' => $mensaje, 'id_contenedor' => $idContenedor], 201);
}

if ($metodo === 'PUT') {
    $sesion = requerirRolAdministrador();
    $entrada = obtenerJsonEntrada();
    $idContenedor = (int) ($entrada['id_contenedor'] ?? 0);

    if ($idContenedor <= 0) {
        responderJson(['exito' => false, 'mensaje' => 'id_contenedor es obligatorio.'], 400);
    }

    $campos = [];
    $valores = ['id' => $idContenedor];
    $mensajeGeo = null;

    if (isset($entrada['direccion'])) {
        $direccion = limpiarTexto($entrada['direccion']);
        if ($direccion === '') {
            responderJson(['exito' => false, 'mensaje' => 'La dirección no puede quedar vacía.'], 400);
        }
        $campos[] = 'direccion = :direccion';
        $valores['direccion'] = $direccion;

        // Cambió la dirección -> reintentamos geocodificar.
        $geo = geocodificarDireccion($direccion);
        $campos[] = 'coordenadas = :coordenadas';
        $valores['coordenadas'] = $geo ? "{$geo['lat']},{$geo['lon']}" : null;
        $mensajeGeo = $geo
            ? null
            : 'No se pudo ubicar la nueva dirección en el mapa automáticamente (revisá que esté completa, o que haya conexión a internet).';
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

        // Nueva lectura del historial de llenado (nivel avanzado 3.4:
        // predicciones) cada vez que cambia nivel_llenado.
        if (isset($valores['nivel_llenado'])) {
            $pdo->prepare('INSERT INTO LECTURA_NIVEL_CONTENEDOR (id_contenedor, nivel_llenado) VALUES (:id, :nivel)')
                ->execute(['id' => $idContenedor, 'nivel' => $valores['nivel_llenado']]);
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

    registrarAuditoria($pdo, "Modificó el contenedor #{$idContenedor}.");

    responderJson(['exito' => true, 'mensaje' => $mensajeGeo ? "Contenedor actualizado. {$mensajeGeo}" : 'Contenedor actualizado.']);
}

if ($metodo === 'DELETE') {
    $sesion = requerirRolAdministrador();
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

    registrarAuditoria($pdo, "Dio de baja al contenedor #{$idContenedor}.");

    responderJson(['exito' => true, 'mensaje' => 'Contenedor dado de baja.']);
}

responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);