<?php
/**
 * api-gestion/responder_consulta.php
 * Envía por email real (SMTP, ver Comun/correo.php) la respuesta del
 * Administrador a una consulta pública (RN21), y solo si el envío se
 * hace con éxito la marca como RESPONDIDA y guarda el texto enviado,
 * para llevar el registro de qué se contestó y cuándo. Reemplaza al
 * anterior flujo de "mailto:" + marcar por separado. Solo Administrador.
 *
 * PUT { "id_consulta": 1, "respuesta": "Gracias por escribirnos..." }
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
$idConsulta = (int) ($entrada['id_consulta'] ?? 0);
$respuesta = limpiarTexto($entrada['respuesta'] ?? '');

if ($idConsulta <= 0) {
    responderJson(['exito' => false, 'mensaje' => 'id_consulta es obligatorio.'], 400);
}
if ($respuesta === '') {
    responderJson(['exito' => false, 'mensaje' => 'La respuesta no puede estar vacía.'], 400);
}

$pdo = obtenerConexion();

$consulta = $pdo->prepare('SELECT nombre, email, mensaje, estado FROM CONSULTA_PUBLICA WHERE id_consulta = :id');
$consulta->execute(['id' => $idConsulta]);
$fila = $consulta->fetch();

if (!$fila) {
    responderJson(['exito' => false, 'mensaje' => 'Consulta no encontrada.'], 404);
}
if ($fila['estado'] === 'RESPONDIDA') {
    responderJson(['exito' => false, 'mensaje' => 'Esta consulta ya fue respondida.'], 409);
}

$asunto = 'Re: tu consulta a SiGeRU';
$cuerpo = "Hola {$fila['nombre']},\n\nTe escribimos por tu consulta: \"{$fila['mensaje']}\"\n\n"
    . "{$respuesta}\n\nSaludos,\nSiGeRU";

$envio = enviarCorreo($fila['email'], $asunto, $cuerpo);
if (!$envio['exito']) {
    responderJson(['exito' => false, 'mensaje' => $envio['mensaje']], 502);
}

$pdo->prepare(
    "UPDATE CONSULTA_PUBLICA
     SET estado = 'RESPONDIDA', respuesta_admin = :respuesta, fecha_respuesta = NOW()
     WHERE id_consulta = :id"
)->execute(['respuesta' => $respuesta, 'id' => $idConsulta]);

responderJson(['exito' => true, 'mensaje' => 'Respuesta enviada por email y guardada.']);
