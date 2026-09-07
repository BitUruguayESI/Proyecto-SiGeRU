<?php
/**
 * api-usuarios/listar_operarios.php
 * Listado de OPERARIO (con su nombre y cargo) para que el Administrador
 * elija a quién asignar en la asignación diaria (RF23). Solo Administrador.
 *
 * GET -> { exito, operarios: [{ id_operario, cargo, ci, nombre_completo }] }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

requerirRolAdministrador();

$pdo = obtenerConexion();
$operarios = $pdo->query(
    'SELECT o.id_operario, o.cargo, u.ci, u.nombre_completo
     FROM OPERARIO o JOIN USUARIO u ON u.ci = o.ci_usuario
     WHERE u.estado = \'ACTIVO\'
     ORDER BY u.nombre_completo'
)->fetchAll();

responderJson(['exito' => true, 'operarios' => $operarios]);
