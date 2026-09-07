<?php
/**
 * api-usuarios/registro.php
 * Alta de un USUARIO (OPERARIO o ADMINISTRADOR).
 *
 * Espera un POST con cuerpo JSON:
 * {
 *   "ci": "12345678",
 *   "contrasena": "algo-secreto",
 *   "email": "persona@correo.com",
 *   "nombre_completo": "Juan Pérez",
 *   "tipo_usuario": "OPERARIO" | "ADMINISTRADOR",
 *   "cargo": "Recolector",              // obligatorio solo si tipo_usuario = OPERARIO
 *   "telefonos": ["099123456", "26001122"]   // opcional, 0 o más
 * }
 *
 * Responde JSON: { "exito": true|false, "mensaje": "..." }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$entrada = obtenerJsonEntrada();

$ci               = limpiarTexto($entrada['ci'] ?? '');
$contrasena       = (string) ($entrada['contrasena'] ?? '');
$email            = limpiarTexto($entrada['email'] ?? '');
$nombreCompleto   = limpiarTexto($entrada['nombre_completo'] ?? '');
$tipoUsuario      = strtoupper(limpiarTexto($entrada['tipo_usuario'] ?? ''));
$cargo            = limpiarTexto($entrada['cargo'] ?? '');
$telefonos        = is_array($entrada['telefonos'] ?? null) ? $entrada['telefonos'] : [];

// ---- Validaciones ----
$errores = [];

if (!esCedulaValida($ci)) {
    $errores[] = 'La cédula debe tener entre 7 y 8 dígitos, sin puntos ni guion.';
}
if (strlen($contrasena) < 8) {
    $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
}
if (!esEmailValido($email)) {
    $errores[] = 'El email no es válido.';
}
if ($nombreCompleto === '') {
    $errores[] = 'El nombre completo es obligatorio.';
}
if (!in_array($tipoUsuario, ['OPERARIO', 'ADMINISTRADOR'], true)) {
    $errores[] = 'tipo_usuario debe ser OPERARIO o ADMINISTRADOR.';
}
if ($tipoUsuario === 'OPERARIO' && $cargo === '') {
    $errores[] = 'El cargo es obligatorio para un OPERARIO.';
}

if ($errores) {
    responderJson(['exito' => false, 'mensaje' => implode(' ', $errores)], 400);
}

$pdo = obtenerConexion();

// ---- Unicidad de CI y email (RN: USUARIO.ci es PK, email es UNIQUE) ----
$verificacion = $pdo->prepare('SELECT ci FROM USUARIO WHERE ci = :ci OR email = :email');
$verificacion->execute(['ci' => $ci, 'email' => $email]);
if ($verificacion->fetch()) {
    responderJson(['exito' => false, 'mensaje' => 'Ya existe un usuario registrado con esa cédula o ese email.'], 409);
}

$hashContrasena = password_hash($contrasena, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    // 1) Fila base en USUARIO (tipo_usuario resuelve RN01 - generalización)
    $insertarUsuario = $pdo->prepare(
        'INSERT INTO USUARIO (ci, contrasena, email, nombre_completo, tipo_usuario)
         VALUES (:ci, :contrasena, :email, :nombre_completo, :tipo_usuario)'
    );
    $insertarUsuario->execute([
        'ci'              => $ci,
        'contrasena'      => $hashContrasena,
        'email'           => $email,
        'nombre_completo' => $nombreCompleto,
        'tipo_usuario'    => $tipoUsuario,
    ]);

    // 2) Fila hija según el tipo (los triggers de la base validan que coincida con tipo_usuario)
    if ($tipoUsuario === 'OPERARIO') {
        $insertarHijo = $pdo->prepare('INSERT INTO OPERARIO (cargo, ci_usuario) VALUES (:cargo, :ci)');
        $insertarHijo->execute(['cargo' => $cargo, 'ci' => $ci]);
    } else {
        $insertarHijo = $pdo->prepare('INSERT INTO ADMINISTRADOR (ci_usuario) VALUES (:ci)');
        $insertarHijo->execute(['ci' => $ci]);
    }

    // 3) Teléfonos (0 o más — atributo multivaluado normalizado en USUARIO_TELEFONO)
    if ($telefonos) {
        $insertarTelefono = $pdo->prepare(
            'INSERT INTO USUARIO_TELEFONO (ci_usuario, telefono) VALUES (:ci, :telefono)'
        );
        foreach ($telefonos as $telefono) {
            $telefono = limpiarTexto((string) $telefono);
            if ($telefono !== '') {
                $insertarTelefono->execute(['ci' => $ci, 'telefono' => $telefono]);
            }
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderJson(['exito' => false, 'mensaje' => 'No se pudo completar el registro: ' . $e->getMessage()], 500);
}

responderJson(['exito' => true, 'mensaje' => 'Usuario registrado correctamente.'], 201);
