<?php
/**
 * api-usuarios/solicitar_recuperacion.php
 * RF04 - Paso 1: el usuario pide recuperar su contraseña indicando su
 * email. Si existe un USUARIO con ese email, se genera un código
 * numérico de 6 dígitos de un solo uso (válido 5 minutos) y se le manda
 * por email. El usuario lo ingresa junto con su email en
 * restablecer_contrasena.html para completar el restablecimiento (ver
 * ese archivo para el paso 2).
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

$consulta = $pdo->prepare('SELECT ci, nombre_completo, email FROM USUARIO WHERE email = :email AND estado = \'ACTIVO\'');
$consulta->execute(['email' => $email]);
$usuario = $consulta->fetch();

// Si no existe (o está inactivo), igual respondemos éxito con el mensaje
// genérico, pero no mandamos ningún email.
if (!$usuario) {
    responderJson(['exito' => true, 'mensaje' => $mensajeGenerico]);
}

// Código numérico de 6 dígitos (no un link): más fácil de tipear a
// mano, y evita depender de que el cliente de email no "pre-visite" el
// link solo (algunos antivirus/escáneres de email lo hacen, lo que
// invalidaría un token de un solo uso antes de que el usuario llegue a
// usarlo). Reintenta si por azar ya existe ese mismo código sin usar
// (poco probable con 900.000 combinaciones, pero el token es UNIQUE).
$intentos = 0;
do {
    $codigo = (string) random_int(100000, 999999);
    $intentos++;
    try {
        $pdo->prepare(
            'INSERT INTO RECUPERACION_CONTRASENA (ci_usuario, token, fecha_expiracion)
             VALUES (:ci, :token, DATE_ADD(NOW(), INTERVAL 5 MINUTE))'
        )->execute(['ci' => $usuario['ci'], 'token' => $codigo]);
        break;
    } catch (PDOException $e) {
        if ($intentos >= 5) {
            responderJson(['exito' => false, 'mensaje' => 'No se pudo generar el código. Probá de nuevo.'], 500);
        }
        // Duplicado por la UNIQUE KEY del token: se reintenta con otro código.
    }
} while (true);

$urlFrontend = getenv('SIGERU_FRONTEND_URL') ?: 'http://localhost:8080';
$link = "{$urlFrontend}/restablecer_contrasena.html?email=" . urlencode($usuario['email']);

$asunto = 'Tu código para restablecer la contraseña en SiGeRU';
$cuerpo = "Hola {$usuario['nombre_completo']},\n\n"
    . "Pediste restablecer tu contraseña en SiGeRU. Tu código es:\n\n"
    . "{$codigo}\n\n"
    . "Entrá a {$link} dentro de los próximos 5 minutos e ingresá ese código junto con tu contraseña nueva.\n\n"
    . "Si no fuiste vos, podés ignorar este correo: tu contraseña actual sigue siendo válida.\n\n"
    . "Saludos,\nSiGeRU";

enviarCorreo($usuario['email'], $asunto, $cuerpo);

responderJson(['exito' => true, 'mensaje' => $mensajeGenerico]);