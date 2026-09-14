<?php
/**
 * api-retiros/listar.php
 * Listado de solicitudes de retiro de residuos especiales, para el
 * panel de gestión. Solo staff (Operario o Administrador) — requiere
 * sesión activa.
 *
 * GET  ?estado=PENDIENTE&id_cuadrilla=2   (ambos opcionales)
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

requerirSesionActiva();

$pdo = obtenerConexion();

$condiciones = [];
$valores = [];

$estadosValidos = ['PENDIENTE', 'RETIRADO'];
if (!empty($_GET['estado']) && in_array($_GET['estado'], $estadosValidos, true)) {
    $condiciones[] = 'r.estado = :estado';
    $valores['estado'] = $_GET['estado'];
}
if (!empty($_GET['id_cuadrilla'])) {
    $condiciones[] = 'r.id_cuadrilla = :id_cuadrilla';
    $valores['id_cuadrilla'] = (int) $_GET['id_cuadrilla'];
}

$sql = 'SELECT r.id_solicitud, r.tipo_residuo, r.direccion, r.descripcion, r.estado,
               r.fecha_registro, r.fecha_retiro,
               r.id_vecino, v.email AS vecino_email,
               r.id_cuadrilla, cu.nombre_cuadrilla
        FROM SOLICITUD_RETIRO r
        LEFT JOIN VECINO v ON v.id_vecino = r.id_vecino
        LEFT JOIN CUADRILLA cu ON cu.id_cuadrilla = r.id_cuadrilla';

if ($condiciones) {
    $sql .= ' WHERE ' . implode(' AND ', $condiciones);
}
$sql .= ' ORDER BY r.fecha_registro DESC';

$consulta = $pdo->prepare($sql);
$consulta->execute($valores);

responderJson(['exito' => true, 'solicitudes' => $consulta->fetchAll()]);
