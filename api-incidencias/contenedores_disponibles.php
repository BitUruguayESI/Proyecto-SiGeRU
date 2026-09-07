<?php
/**
 * api-incidencias/contenedores_disponibles.php
 * Listado público (sin sesión) de contenedores que un vecino puede
 * elegir al reportar una incidencia. Expone solo lo mínimo necesario
 * (id, coordenadas, estado) — nada de datos administrativos.
 *
 * GET -> { exito, contenedores: [{ id_contenedor, coordenadas, estado }] }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$pdo = obtenerConexion();
$contenedores = $pdo->query(
    "SELECT id_contenedor, coordenadas, estado FROM CONTENEDOR
     WHERE estado <> 'FUERA_DE_SERVICIO' ORDER BY id_contenedor"
)->fetchAll();

responderJson(['exito' => true, 'contenedores' => $contenedores]);
