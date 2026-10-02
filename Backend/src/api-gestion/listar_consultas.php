<?php
/**
 * api-gestion/listar_consultas.php
 * Listado de consultas públicas (RN21) para el panel de gestión, para
 * que el Administrador sepa qué le llegó y la pueda responder por
 * email real desde la app (ver responder_consulta.php). Solo staff
 * (requiere sesión activa) — igual que api-retiros/listar.php.
 *
 * GET  ?estado=PENDIENTE   (opcional)
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

requerirSesionActiva();

$pdo = obtenerConexion();

$sql = 'SELECT id_consulta, nombre, email, mensaje, fecha, estado, respuesta_admin, fecha_respuesta FROM CONSULTA_PUBLICA';
$valores = [];

$estadosValidos = ['PENDIENTE', 'RESPONDIDA'];
if (!empty($_GET['estado']) && in_array($_GET['estado'], $estadosValidos, true)) {
    $sql .= ' WHERE estado = :estado';
    $valores['estado'] = $_GET['estado'];
}
$sql .= ' ORDER BY fecha DESC';

$consulta = $pdo->prepare($sql);
$consulta->execute($valores);

responderJson(['exito' => true, 'consultas' => $consulta->fetchAll()]);
