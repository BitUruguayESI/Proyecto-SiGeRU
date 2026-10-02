<?php
/**
 * api-usuarios/listar_operarios.php
 * Listado de OPERARIO (con su nombre y cargo) para que el Administrador
 * elija a quién asignar en la asignación diaria (RF23). Solo Administrador.
 *
 * GET               -> { exito, operarios: [{ id_operario, cargo, ci, nombre_completo }] }
 * GET ?cargo=CHOFER  -> solo los operarios con ese cargo (CHOFER o RECOLECTOR),
 *                       útil para armar la cuadrilla estable (1 chofer + N recolectores).
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

requerirRolAdministrador();

$pdo = obtenerConexion();

$condiciones = ["u.estado = 'ACTIVO'"];
$valores = [];
if (!empty($_GET['cargo'])) {
    $cargo = strtoupper(limpiarTexto($_GET['cargo']));
    if (in_array($cargo, ['CHOFER', 'RECOLECTOR'], true)) {
        $condiciones[] = 'o.cargo = :cargo';
        $valores['cargo'] = $cargo;
    }
}

$consulta = $pdo->prepare(
    'SELECT o.id_operario, o.cargo, u.ci, u.nombre_completo
     FROM OPERARIO o JOIN USUARIO u ON u.ci = o.ci_usuario
     WHERE ' . implode(' AND ', $condiciones) . '
     ORDER BY u.nombre_completo'
);
$consulta->execute($valores);

responderJson(['exito' => true, 'operarios' => $consulta->fetchAll()]);
