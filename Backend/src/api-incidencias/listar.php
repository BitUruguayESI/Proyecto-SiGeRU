<?php
/**
 * api-incidencias/listar.php
 * RF14 (listado filtrable) y RF21 (historial por contenedor/zona).
 * Solo staff (Operario o Administrador) — requiere sesión activa.
 *
 * No incluye contenedor_coordenadas a propósito: al staff se le muestra
 * la dirección, no coordenadas crudas (ver IncidenciasApiTest::
 * testDireccionSeVeEnVezDeCoordenadas). Para ubicar las incidencias en el
 * mapa interactivo (RF13), el frontend cruza id_contenedor con la lista
 * de contenedores que ya carga por separado (esa sí trae coordenadas).
 *
 * GET  ?estado=PENDIENTE&id_contenedor=1&id_cuadrilla=2   (todos opcionales)
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

$estadosValidos = ['PENDIENTE', 'EN_PROCESO', 'RESUELTA'];
if (!empty($_GET['estado']) && in_array($_GET['estado'], $estadosValidos, true)) {
    $condiciones[] = 'i.estado = :estado';
    $valores['estado'] = $_GET['estado'];
}
if (!empty($_GET['id_contenedor'])) {
    $condiciones[] = 'i.id_contenedor = :id_contenedor';
    $valores['id_contenedor'] = (int) $_GET['id_contenedor'];
}
if (!empty($_GET['id_cuadrilla'])) {
    $condiciones[] = 'i.id_cuadrilla = :id_cuadrilla';
    $valores['id_cuadrilla'] = (int) $_GET['id_cuadrilla'];
}

$sql = 'SELECT i.id_incidencia, i.descripcion, i.tipo_incidencia, i.estado,
               i.fecha_registro, i.fecha_resolucion,
               i.id_contenedor, c.direccion AS contenedor_direccion,
               i.id_cuadrilla, cu.nombre_cuadrilla
        FROM INCIDENCIA i
        LEFT JOIN CONTENEDOR c ON c.id_contenedor = i.id_contenedor
        LEFT JOIN CUADRILLA cu ON cu.id_cuadrilla = i.id_cuadrilla';

if ($condiciones) {
    $sql .= ' WHERE ' . implode(' AND ', $condiciones);
}
$sql .= ' ORDER BY i.fecha_registro DESC';

$consulta = $pdo->prepare($sql);
$consulta->execute($valores);

responderJson(['exito' => true, 'incidencias' => $consulta->fetchAll()]);