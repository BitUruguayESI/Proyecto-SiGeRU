<?php
/**
 * api-usuarios/logout.php
 * Cierra la sesión PHP actual.
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/funciones.php';

session_start();
$_SESSION = [];
session_destroy();

responderJson(['exito' => true, 'mensaje' => 'Sesión cerrada.']);
