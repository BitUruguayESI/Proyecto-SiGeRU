<?php
/**
 * api-usuarios/quien_soy.php
 * Devuelve los datos de la sesión activa (usado por el nav de las
 * pantallas de admin para mostrar el nombre del usuario logueado y
 * para que el frontend sepa si hay sesión sin duplicar esa lógica).
 *
 * Responde 401 si no hay sesión activa.
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/funciones.php';

$sesion = requerirSesionActiva();

responderJson([
    'exito'           => true,
    'ci'              => $sesion['ci_usuario'],
    'nombre_completo' => $sesion['nombre'],
    'tipo_usuario'    => $sesion['tipo_usuario'],
    'cargo'           => $sesion['cargo'] ?? null,
]);
