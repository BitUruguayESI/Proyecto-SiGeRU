<?php
/**
 * index.php (raíz del proyecto)
 * Punto de entrada del sitio: por ahora manda directo al login del
 * personal. Cuando exista una landing pública, este archivo va a
 * redirigir ahí en cambio.
 */

session_start();

header('Location: Frontend/login.html');
exit;
