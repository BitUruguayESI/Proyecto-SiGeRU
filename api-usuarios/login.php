<?php
/**
 * api-usuarios/login.php
 * Inicio de sesión. Verifica credenciales contra USUARIO, abre sesión
 * PHP y deja registro en la tabla SESION.
 *
 * Espera un POST con cuerpo JSON: { "ci": "...", "contrasena": "..." }
 * Responde JSON: { "exito": true, "tipo_usuario": "...", "nombre_completo": "..." }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$entrada    = obtenerJsonEntrada();
$ci         = limpiarTexto($entrada['ci'] ?? '');
$contrasena = (string) ($entrada['contrasena'] ?? '');

if ($ci === '' || $contrasena === '') {
    responderJson(['exito' => false, 'mensaje' => 'Cédula y contraseña son obligatorias.'], 400);
}

$pdo = obtenerConexion();

$consulta = $pdo->prepare(
    'SELECT ci, contrasena, nombre_completo, tipo_usuario, estado
     FROM USUARIO WHERE ci = :ci'
);
$consulta->execute(['ci' => $ci]);
$usuario = $consulta->fetch();

if (!$usuario || !password_verify($contrasena, $usuario['contrasena'])) {
    responderJson(['exito' => false, 'mensaje' => 'Cédula o contraseña incorrecta.'], 401);
}

if ($usuario['estado'] !== 'ACTIVO') {
    responderJson(['exito' => false, 'mensaje' => 'El usuario está inactivo. Contactá a un administrador.'], 403);
}

// Registrar la sesión en la tabla SESION
$insertarSesion = $pdo->prepare(
    'INSERT INTO SESION (ci_usuario) VALUES (:ci)'
);
$insertarSesion->execute(['ci' => $ci]);
$idSesion = (int) $pdo->lastInsertId();

session_start();
$_SESSION['ci_usuario']    = $usuario['ci'];
$_SESSION['tipo_usuario']  = $usuario['tipo_usuario'];
$_SESSION['nombre']        = $usuario['nombre_completo'];
$_SESSION['id_sesion_bd']  = $idSesion;

responderJson([
    'exito'           => true,
    'tipo_usuario'    => $usuario['tipo_usuario'],
    'nombre_completo' => $usuario['nombre_completo'],
]);
