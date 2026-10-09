<?php
/**
 * api-usuarios/restablecer_contrasena.php
 * RF04 - Paso 2: con el token recibido por email (ver
 * solicitar_recuperacion.php), el usuario elige una contraseña nueva.
 * Valida que el token exista, no haya sido usado y no esté vencido
 * (5 minutos desde que se generó); si todo es correcto, actualiza
 * USUARIO.contrasena y marca el token como usado para que no sirva de
 * nuevo.
 *
 * Espera un POST con cuerpo JSON:
 * { "token": "...", "contrasena": "nueva-contrasena" }
 * Responde JSON: { "exito": true|false, "mensaje": "..." }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$entrada = obtenerJsonEntrada();
$token = limpiarTexto($entrada['token'] ?? '');
$contrasena = (string) ($entrada['contrasena'] ?? '');

if ($token === '') {
    responderJson(['exito' => false, 'mensaje' => 'Falta el token de recuperación.'], 400);
}
if (strlen($contrasena) < 8) {
    responderJson(['exito' => false, 'mensaje' => 'La contraseña debe tener al menos 8 caracteres.'], 400);
}

$pdo = obtenerConexion();

$consulta = $pdo->prepare(
    'SELECT id_recuperacion, ci_usuario, fecha_expiracion, usado
     FROM RECUPERACION_CONTRASENA
     WHERE token = :token'
);
$consulta->execute(['token' => $token]);
$recuperacion = $consulta->fetch();

if (!$recuperacion) {
    responderJson(['exito' => false, 'mensaje' => 'El link de recuperación no es válido.'], 404);
}
if ((int) $recuperacion['usado'] === 1) {
    responderJson(['exito' => false, 'mensaje' => 'Este link ya fue usado. Pedí uno nuevo desde "Olvidé mi contraseña".'], 410);
}
if (strtotime($recuperacion['fecha_expiracion']) < time()) {
    responderJson(['exito' => false, 'mensaje' => 'Este link venció. Pedí uno nuevo desde "Olvidé mi contraseña".'], 410);
}

$hashContrasena = password_hash($contrasena, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    $pdo->prepare('UPDATE USUARIO SET contrasena = :contrasena WHERE ci = :ci')
        ->execute(['contrasena' => $hashContrasena, 'ci' => $recuperacion['ci_usuario']]);

    $pdo->prepare('UPDATE RECUPERACION_CONTRASENA SET usado = 1 WHERE id_recuperacion = :id')
        ->execute(['id' => $recuperacion['id_recuperacion']]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderJson(['exito' => false, 'mensaje' => 'No se pudo restablecer la contraseña: ' . $e->getMessage()], 500);
}

responderJson(['exito' => true, 'mensaje' => 'Contraseña actualizada. Ya podés iniciar sesión con la nueva.']);