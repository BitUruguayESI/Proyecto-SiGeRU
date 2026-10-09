<?php
/**
 * api-usuarios/solicitar_recuperacion.php
 * RF04 - Paso 1: el usuario pide recuperar su contraseña indicando su
 * email. Si existe un USUARIO con ese email, se genera un token al azar
 * de un solo uso (válido 5 minutos) y se le manda por email un link para
 * completar el restablecimiento en restablecer_contrasena.html.
 *
 * Por seguridad, la respuesta es la misma exista o no ese email
 * registrado (si no, cualquiera podría usar este formulario para
 * averiguar qué emails están dados de alta en el sistema).
 *
 * Espera un POST con cuerpo JSON: { "email": "persona@correo.com" }
 * Responde JSON: { "exito": true, "mensaje": "..." }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';
require_once __DIR__ . '/../Comun/correo.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$entrada = obtenerJsonEntrada();
$email = limpiarTexto($entrada['email'] ?? '');

if (!esEmailValido($email)) {
    responderJson(['exito' => false, 'mensaje' => 'Ingresá un email válido.'], 400);
}

$mensajeGenerico = 'Si ese email está registrado, te enviamos un correo con instrucciones para restablecer tu contraseña.';

$pdo = obtenerConexion();

$consulta = $pdo->prepare('SELECT ci, nombre_completo FROM USUARIO WHERE email = :email AND estado = \'ACTIVO\'');
$consulta->execute(['email' => $email]);
$usuario = $consulta->fetch();

// Si no existe (o está inactivo), igual respondemos éxito con el mensaje
// genérico, pero no mandamos ningún email.
if (!$usuario) {
    responderJson(['exito' => true, 'mensaje' => $mensajeGenerico]);
}

$token = bin2hex(random_bytes(32));

$pdo->prepare(
    'INSERT INTO RECUPERACION_CONTRASENA (ci_usuario, token, fecha_expiracion)
     VALUES (:ci, :token, DATE_ADD(NOW(), INTERVAL 5 MINUTE))'
)->execute(['ci' => $usuario['ci'], 'token' => $token]);

$urlFrontend = getenv('SIGERU_FRONTEND_URL') ?: 'http://localhost:8080';
$link = "{$urlFrontend}/restablecer_contrasena.html?token={$token}";

$asunto = 'Restablecer tu contraseña en SiGeRU';
$cuerpo = "Hola {$usuario['nombre_completo']},\n\n"
    . "Pediste restablecer tu contraseña en SiGeRU. Entrá a este link dentro de los próximos 5 minutos para elegir una contraseña nueva:\n\n"
    . "{$link}\n\n"
    . "Si no fuiste vos, podés ignorar este correo: tu contraseña actual sigue siendo válida.\n\n"
    . "Saludos,\nSiGeRU";

enviarCorreo($usuario['email'], $asunto, $cuerpo);

responderJson(['exito' => true, 'mensaje' => $mensajeGenerico]);