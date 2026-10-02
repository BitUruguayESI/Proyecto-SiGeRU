<?php
/**
 * api-retiros/responder.php
 * Envía por email real (SMTP, ver Comun/correo.php) la respuesta del
 * Administrador al vecino que pidió el retiro (por ejemplo la fecha y
 * horario acordados), y solo si el envío se hace con éxito la deja
 * también guardada dentro de la propia solicitud, para que quede
 * visible en el panel quién respondió qué y cuándo.
 *
 * Se puede usar en cualquier estado de la solicitud (antes o después
 * de asignar cuadrilla, y también ya retirada, por si hace falta dejar
 * una aclaración posterior).
 *
 * PUT { "id_solicitud": 1, "respuesta": "Coordinamos el retiro para..." }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';
require_once __DIR__ . '/../Comun/correo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

requerirRolAdministrador();

$entrada = obtenerJsonEntrada();
$idSolicitud = (int) ($entrada['id_solicitud'] ?? 0);
$respuesta = limpiarTexto($entrada['respuesta'] ?? '');

if ($idSolicitud <= 0) {
    responderJson(['exito' => false, 'mensaje' => 'id_solicitud es obligatorio.'], 400);
}
if ($respuesta === '') {
    responderJson(['exito' => false, 'mensaje' => 'La respuesta no puede estar vacía.'], 400);
}

$pdo = obtenerConexion();

$consulta = $pdo->prepare(
    'SELECT r.id_solicitud, r.tipo_residuo, r.direccion, v.email AS vecino_email
     FROM SOLICITUD_RETIRO r
     LEFT JOIN VECINO v ON v.id_vecino = r.id_vecino
     WHERE r.id_solicitud = :id'
);
$consulta->execute(['id' => $idSolicitud]);
$solicitud = $consulta->fetch();

if (!$solicitud) {
    responderJson(['exito' => false, 'mensaje' => 'Solicitud no encontrada.'], 404);
}
if (empty($solicitud['vecino_email'])) {
    responderJson(['exito' => false, 'mensaje' => 'Esta solicitud no tiene un email de vecino asociado.'], 422);
}

$asunto = 'SiGeRU - Retiro de residuos especiales';
$cuerpo = "Hola,\n\nTe escribimos por tu solicitud de retiro de \"{$solicitud['tipo_residuo']}\" "
    . "en {$solicitud['direccion']}:\n\n{$respuesta}\n\nSaludos,\nSiGeRU";

$envio = enviarCorreo($solicitud['vecino_email'], $asunto, $cuerpo);
if (!$envio['exito']) {
    responderJson(['exito' => false, 'mensaje' => $envio['mensaje']], 502);
}

$pdo->prepare(
    'UPDATE SOLICITUD_RETIRO
     SET respuesta_admin = :respuesta, fecha_respuesta = NOW()
     WHERE id_solicitud = :id'
)->execute(['respuesta' => $respuesta, 'id' => $idSolicitud]);

responderJson(['exito' => true, 'mensaje' => 'Respuesta enviada por email y guardada.']);
