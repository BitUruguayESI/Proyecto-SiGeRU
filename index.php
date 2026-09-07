<?php
/**
 * index.php (raíz del proyecto)
 * Punto de entrada del sitio: manda a la Landing Page pública (RN21),
 * que es donde cualquier visitante sin cuenta puede hacer una consulta
 * o (más adelante) reportar una incidencia, y desde donde el personal
 * accede a Login/Registro.
 *
 * Cuando existan los paneles por tipo de usuario, acá se puede agregar
 * lógica para mandar directo al panel si ya hay sesión activa
 * ($_SESSION['tipo_usuario']), en vez de pasar por la landing.
 */

session_start();

header('Location: Frontend/landing.html');
exit;
