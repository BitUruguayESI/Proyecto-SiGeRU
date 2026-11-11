<?php
/**
 * api-retiros/ubicacion.php
 * Devuelve las coordenadas (lat/lon) de la dirección de una solicitud de
 * retiro especial, geocodificándola contra OpenStreetMap (Nominatim), para
 * mostrarla en el mapa de "Mi día". Las solicitudes solo guardan la
 * dirección de texto, así que se ubica a pedido (el frontend lo pide de a
 * una y cachea el resultado, para respetar el límite de ~1 consulta por
 * segundo de Nominatim).
 * Solo staff (Operario o Administrador) — requiere sesión activa.
 *
 * GET  ?id_solicitud=3  -> { exito, lat, lon }  |  { exito:false, mensaje }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

requerirSesionActiva();

$idSolicitud = (int) ($_GET['id_solicitud'] ?? 0);
if ($idSolicitud <= 0) {
    responderJson(['exito' => false, 'mensaje' => 'id_solicitud es obligatorio.'], 400);
}

$pdo = obtenerConexion();
$consulta = $pdo->prepare('SELECT direccion FROM SOLICITUD_RETIRO WHERE id_solicitud = :id');
$consulta->execute(['id' => $idSolicitud]);
$fila = $consulta->fetch();
if (!$fila) {
    responderJson(['exito' => false, 'mensaje' => 'Solicitud no encontrada.'], 404);
}

$direccion = trim((string) $fila['direccion']);

// Primero con "Montevideo" (el sistema es municipal) para que Nominatim
// no la ubique en otra ciudad; si no aparece, se reintenta tal cual.
$conCiudad = stripos($direccion, 'montevideo') === false ? "{$direccion}, Montevideo" : $direccion;
$geo = geocodificarDireccion($conCiudad);
if (!$geo && $conCiudad !== $direccion) {
    usleep(1100000);
    $geo = geocodificarDireccion($direccion);
}

if (!$geo) {
    responderJson(['exito' => false, 'mensaje' => 'No se pudo ubicar la dirección en el mapa.']);
}

responderJson(['exito' => true, 'lat' => $geo['lat'], 'lon' => $geo['lon']]);
