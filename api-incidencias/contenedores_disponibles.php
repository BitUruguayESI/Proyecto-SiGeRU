<?php
/**
 * api-incidencias/contenedores_disponibles.php
 * Listado público (sin sesión) de contenedores que un vecino puede
 * elegir al reportar una incidencia. El vecino los identifica por
 * dirección (no por id ni coordenadas) — "coordenadas" se sigue
 * devolviendo para poder ubicar el contenedor elegido en el mapa,
 * pero nunca se le muestra como texto.
 *
 * GET -> { exito, contenedores: [{ id_contenedor, direccion, coordenadas, estado }] }
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$pdo = obtenerConexion();
$contenedores = $pdo->query(
    "SELECT id_contenedor, direccion, coordenadas, estado FROM CONTENEDOR
     WHERE estado <> 'FUERA_DE_SERVICIO' ORDER BY id_contenedor"
)->fetchAll();

responderJson(['exito' => true, 'contenedores' => $contenedores]);
