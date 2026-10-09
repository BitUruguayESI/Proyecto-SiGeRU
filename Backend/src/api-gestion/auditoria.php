<?php
/**
 * api-gestion/auditoria.php
 * Consulta del historial de auditoría (AUDITORIA, RF14/RN18): acciones
 * críticas registradas por los administradores (alta, baja o
 * modificación de usuarios, contenedores, instalaciones, etc.). Las
 * auditorías se generan automáticamente desde cada endpoint afectado
 * mediante registrarAuditoria() (Comun/funciones.php); este archivo
 * solo expone la consulta del historial. Solo ADMINISTRADOR puede
 * consultarlo.
 *
 * GET ?desde=&hasta=&id_administrador=   (todos opcionales, fechas en "YYYY-MM-DD")
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

requerirRolAdministrador();

$pdo = obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo !== 'GET') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$condiciones = [];
$valores = [];

if (!empty($_GET['desde'])) {
    $desde = limpiarTexto($_GET['desde']);
    if (!esFechaValida($desde)) {
        responderJson(['exito' => false, 'mensaje' => 'desde debe tener formato AAAA-MM-DD.'], 400);
    }
    $condiciones[] = 'a.fecha_hora >= :desde';
    $valores['desde'] = $desde . ' 00:00:00';
}
if (!empty($_GET['hasta'])) {
    $hasta = limpiarTexto($_GET['hasta']);
    if (!esFechaValida($hasta)) {
        responderJson(['exito' => false, 'mensaje' => 'hasta debe tener formato AAAA-MM-DD.'], 400);
    }
    $condiciones[] = 'a.fecha_hora <= :hasta';
    $valores['hasta'] = $hasta . ' 23:59:59';
}
if (!empty($_GET['id_administrador'])) {
    $condiciones[] = 'a.id_administrador = :id_administrador';
    $valores['id_administrador'] = (int) $_GET['id_administrador'];
}
$whereSql = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

// LEFT JOIN: si se elimina el administrador, AUDITORIA.id_administrador
// queda en NULL (ON DELETE SET NULL) pero el registro se conserva.
$consulta = $pdo->prepare(
    "SELECT a.id_auditoria, a.accion, a.fecha_hora, a.id_administrador,
            u.nombre_completo AS administrador
     FROM AUDITORIA a
     LEFT JOIN ADMINISTRADOR ad ON ad.id_admin = a.id_administrador
     LEFT JOIN USUARIO u ON u.ci = ad.ci_usuario
     $whereSql
     ORDER BY a.fecha_hora DESC"
);
$consulta->execute($valores);

responderJson(['exito' => true, 'auditoria' => $consulta->fetchAll()]);