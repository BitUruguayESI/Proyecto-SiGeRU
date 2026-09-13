<?php
/**
 * api-gestion/registrar_consulta.php
 * RN21 — Consulta pública: un visitante no registrado envía una consulta
 * general desde la Landing Page. No requiere cuenta ni VECINO; se
 * identifica solo con nombre y email.
 *
 * Nota de ubicación: CONSULTA_PUBLICA no depende de ningún otro módulo,
 * se dejó en api-gestion por ser la vía por la que el administrador la
 * revisa/responde. Si tu Diagrama de Paquetes la ubica en otro lado, este
 * archivo se puede mover sin cambios (no tiene dependencias).
 *
 * Espera un POST con cuerpo JSON:
 * { "nombre": "...", "email": "...", "mensaje": "..." }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$entrada = obtenerJsonEntrada();

$nombre  = limpiarTexto($entrada['nombre'] ?? '');
$email   = limpiarTexto($entrada['email'] ?? '');
$mensaje = limpiarTexto($entrada['mensaje'] ?? '');

$errores = [];
if ($nombre === '') {
    $errores[] = 'El nombre es obligatorio.';
}
if (!esEmailValido($email)) {
    $errores[] = 'El email no es válido.';
}
if ($mensaje === '') {
    $errores[] = 'El mensaje no puede estar vacío.';
}

if ($errores) {
    responderJson(['exito' => false, 'mensaje' => implode(' ', $errores)], 400);
}

$pdo = obtenerConexion();

$insertar = $pdo->prepare(
    'INSERT INTO CONSULTA_PUBLICA (nombre, email, mensaje) VALUES (:nombre, :email, :mensaje)'
);
$insertar->execute(['nombre' => $nombre, 'email' => $email, 'mensaje' => $mensaje]);

responderJson(['exito' => true, 'mensaje' => 'Tu consulta fue enviada. Te responderemos por email a la brevedad.'], 201);
