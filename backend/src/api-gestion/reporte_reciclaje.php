<?php
/**
 * api-gestion/reporte_reciclaje.php
 * Nivel avanzado 3.3 — reporte de % reciclado vs. no reciclable, a
 * partir del peso ya registrado en DESCARGA (RN16) y su clasificación
 * opcional contra el catálogo TIPO_RESIDUO. Las descargas sin
 * id_tipo_residuo (por ejemplo, las cargadas antes de que existiera
 * esta clasificación) se cuentan aparte, como "sin clasificar", y no
 * entran en el porcentaje reciclado para no distorsionarlo.
 *
 * GET — cualquier sesión activa
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

requerirSesionActiva();

$pdo = obtenerConexion();

$resumen = $pdo->query(
    "SELECT
        COALESCE(SUM(CASE WHEN t.reciclable = TRUE THEN d.peso ELSE 0 END), 0) AS peso_reciclable,
        COALESCE(SUM(CASE WHEN t.reciclable = FALSE THEN d.peso ELSE 0 END), 0) AS peso_no_reciclable,
        COALESCE(SUM(CASE WHEN d.id_tipo_residuo IS NULL THEN d.peso ELSE 0 END), 0) AS peso_sin_clasificar
     FROM DESCARGA d
     LEFT JOIN TIPO_RESIDUO t ON t.id_tipo_residuo = d.id_tipo_residuo"
)->fetch();

$pesoReciclable = (float) $resumen['peso_reciclable'];
$pesoNoReciclable = (float) $resumen['peso_no_reciclable'];
$pesoSinClasificar = (float) $resumen['peso_sin_clasificar'];
$pesoClasificado = $pesoReciclable + $pesoNoReciclable;

$porTipo = $pdo->query(
    "SELECT t.id_tipo_residuo, t.nombre, t.reciclable, COALESCE(SUM(d.peso), 0) AS peso_total
     FROM TIPO_RESIDUO t
     LEFT JOIN DESCARGA d ON d.id_tipo_residuo = t.id_tipo_residuo
     GROUP BY t.id_tipo_residuo, t.nombre, t.reciclable
     ORDER BY peso_total DESC"
)->fetchAll();
foreach ($porTipo as &$fila) {
    $fila['reciclable'] = (bool) $fila['reciclable'];
    $fila['peso_total'] = (float) $fila['peso_total'];
}

responderJson([
    'exito' => true,
    'resumen' => [
        'peso_reciclable' => $pesoReciclable,
        'peso_no_reciclable' => $pesoNoReciclable,
        'peso_sin_clasificar' => $pesoSinClasificar,
        'peso_total' => $pesoClasificado + $pesoSinClasificar,
        'porcentaje_reciclado' => $pesoClasificado > 0 ? round($pesoReciclable / $pesoClasificado * 100, 1) : null,
    ],
    'por_tipo' => $porTipo,
]);
