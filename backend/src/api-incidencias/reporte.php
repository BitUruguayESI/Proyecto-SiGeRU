<?php
/**
 * api-incidencias/reporte.php
 * Informe simple de incidencias: agrupa el listado en "abiertas"
 * (PENDIENTE + EN_PROCESO) vs. "cerradas" (RESUELTA), con un resumen de
 * conteos. Es un informe de solo lectura — para gestionar cada
 * incidencia (asignar cuadrilla, resolver) se sigue usando listar.php +
 * asignar_cuadrilla.php/actualizar_estado.php como hasta ahora.
 *
 * Solo staff (Operario o Administrador) — requiere sesión activa.
 *
 * GET — sin parámetros.
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

requerirSesionActiva();

$pdo = obtenerConexion();

$sql = 'SELECT i.id_incidencia, i.tipo_incidencia, i.estado,
               i.fecha_registro, i.fecha_resolucion,
               i.id_contenedor, c.direccion AS contenedor_direccion
        FROM INCIDENCIA i
        LEFT JOIN CONTENEDOR c ON c.id_contenedor = i.id_contenedor
        ORDER BY i.fecha_registro DESC';

$todas = $pdo->query($sql)->fetchAll();

$abiertas = array_values(array_filter($todas, fn($i) => $i['estado'] !== 'RESUELTA'));
$cerradas = array_values(array_filter($todas, fn($i) => $i['estado'] === 'RESUELTA'));

responderJson([
    'exito' => true,
    'resumen' => [
        'abiertas' => count($abiertas),
        'cerradas' => count($cerradas),
        'total' => count($todas),
    ],
    'abiertas' => $abiertas,
    'cerradas' => $cerradas,
]);
