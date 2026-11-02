<?php
/**
 * api-usuarios/restablecer_contrasena.php
 * RF04 - Paso 2: con el código de 6 dígitos recibido por email (ver
 * solicitar_recuperacion.php) y el mismo email, el usuario elige una
 * contraseña nueva. Valida que el código exista para ese usuario, no
 * haya sido usado y no esté vencido (5 minutos desde que se generó); si
 * todo es correcto, actualiza USUARIO.contrasena y marca el código como
 * usado para que no sirva de nuevo.
 *
 * Espera un POST con cuerpo JSON:
 * { "email": "...", "codigo": "123456", "contrasena": "nueva-contrasena" }
 * Responde JSON: { "exito": true|false, "mensaje": "..." }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$entrada = obtenerJsonEntrada();
$email = limpiarTexto($entrada['email'] ?? '');
$codigo = limpiarTexto($entrada['codigo'] ?? '');
$contrasena = (string) ($entrada['contrasena'] ?? '');

if ($email === '' || $codigo === '') {
    responderJson(['exito' => false, 'mensaje' => 'Faltan el email o el código.'], 400);
}
if (strlen($contrasena) < 8) {
    responderJson(['exito' => false, 'mensaje' => 'La contraseña debe tener al menos 8 caracteres.'], 400);
}

$pdo = obtenerConexion();

// Mismo mensaje genérico para "ese email no existe" y "código
// incorrecto": así no se puede usar este formulario para averiguar
// qué emails están dados de alta (mismo criterio que
// solicitar_recuperacion.php).
$mensajeInvalido = 'Email o código incorrecto.';

$usuario = $pdo->prepare('SELECT ci FROM USUARIO WHERE email = :email AND estado = \'ACTIVO\'');
$usuario->execute(['email' => $email]);
$usuario = $usuario->fetch();

if (!$usuario) {
    responderJson(['exito' => false, 'mensaje' => $mensajeInvalido], 400);
}

// Si pidió el código varias veces, toma el más reciente que coincida.
$consulta = $pdo->prepare(
    'SELECT id_recuperacion, fecha_expiracion, usado
     FROM RECUPERACION_CONTRASENA
     WHERE ci_usuario = :ci AND token = :codigo
     ORDER BY fecha_creacion DESC LIMIT 1'
);
$consulta->execute(['ci' => $usuario['ci'], 'codigo' => $codigo]);
$recuperacion = $consulta->fetch();

if (!$recuperacion) {
    responderJson(['exito' => false, 'mensaje' => $mensajeInvalido], 400);
}
if ((int) $recuperacion['usado'] === 1) {
    responderJson(['exito' => false, 'mensaje' => 'Este código ya fue usado. Pedí uno nuevo desde "Olvidé mi contraseña".'], 410);
}
if (strtotime($recuperacion['fecha_expiracion']) < time()) {
    responderJson(['exito' => false, 'mensaje' => 'Este código venció. Pedí uno nuevo desde "Olvidé mi contraseña".'], 410);
}

$hashContrasena = password_hash($contrasena, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    $pdo->prepare('UPDATE USUARIO SET contrasena = :contrasena WHERE ci = :ci')
        ->execute(['contrasena' => $hashContrasena, 'ci' => $usuario['ci']]);

    $pdo->prepare('UPDATE RECUPERACION_CONTRASENA SET usado = 1 WHERE id_recuperacion = :id')
        ->execute(['id' => $recuperacion['id_recuperacion']]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderJson(['exito' => false, 'mensaje' => 'No se pudo restablecer la contraseña: ' . $e->getMessage()], 500);
}

responderJson(['exito' => true, 'mensaje' => 'Contraseña actualizada. Ya podés iniciar sesión con la nueva.']);