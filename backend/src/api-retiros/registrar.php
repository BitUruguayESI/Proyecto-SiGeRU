<?php
/**
 * api-retiros/registrar.php
 * Solicitar el retiro de residuos que no van al contenedor común (poda,
 * residuos eléctricos, escombros, muebles, etc.). Público (no requiere
 * cuenta): el vecino se identifica solo con su email, igual que
 * api-incidencias/registrar.php. Si el email ya existe como VECINO, se
 * reutiliza esa fila; si no, se crea.
 *
 * No hay un día fijo por tipo de residuo: el Administrador coordina la
 * fecha de retiro caso a caso desde el panel, una vez que llega la
 * solicitud.
 *
 * Espera POST JSON:
 * { "tipo_residuo": "Poda", "direccion": "...", "descripcion": "...", "email_vecino": "..." }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$entrada = obtenerJsonEntrada();

$tipoResiduo  = limpiarTexto($entrada['tipo_residuo'] ?? '');
$direccion    = limpiarTexto($entrada['direccion'] ?? '');
$descripcion  = limpiarTexto($entrada['descripcion'] ?? '');
$emailVecino  = limpiarTexto($entrada['email_vecino'] ?? '');

$errores = [];
if ($tipoResiduo === '') $errores[] = 'Tenés que indicar el tipo de residuo.';
if ($direccion === '') $errores[] = 'La dirección es obligatoria.';
if (!esEmailValido($emailVecino)) $errores[] = 'El email no es válido.';
if ($errores) {
    responderJson(['exito' => false, 'mensaje' => implode(' ', $errores)], 400);
}

$pdo = obtenerConexion();

try {
    $pdo->beginTransaction();

    // Reutilizar VECINO por email, o crearlo.
    $buscarVecino = $pdo->prepare('SELECT id_vecino FROM VECINO WHERE email = :email');
    $buscarVecino->execute(['email' => $emailVecino]);
    $vecino = $buscarVecino->fetch();
    if ($vecino) {
        $idVecino = (int) $vecino['id_vecino'];
    } else {
        $crearVecino = $pdo->prepare('INSERT INTO VECINO (email) VALUES (:email)');
        $crearVecino->execute(['email' => $emailVecino]);
        $idVecino = (int) $pdo->lastInsertId();
    }

    $insertarSolicitud = $pdo->prepare(
        'INSERT INTO SOLICITUD_RETIRO (tipo_residuo, direccion, descripcion, estado, id_vecino)
         VALUES (:tipo_residuo, :direccion, :descripcion, \'PENDIENTE\', :id_vecino)'
    );
    $insertarSolicitud->execute([
        'tipo_residuo' => $tipoResiduo,
        'direccion'    => $direccion,
        'descripcion'  => $descripcion !== '' ? $descripcion : null,
        'id_vecino'    => $idVecino,
    ]);
    $idSolicitud = (int) $pdo->lastInsertId();

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    responderJson(['exito' => false, 'mensaje' => 'No se pudo registrar la solicitud: ' . $e->getMessage()], 500);
}

responderJson([
    'exito' => true,
    'mensaje' => 'Solicitud registrada. Te vamos a contactar por email para coordinar el retiro.',
    'id_solicitud' => $idSolicitud,
], 201);
