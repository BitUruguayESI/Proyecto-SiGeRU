<?php
/**
 * router.php
 * Router para probar el proyecto LOCAL sin Docker/nginx, usando el
 * servidor built-in de PHP (útil por ejemplo con XAMPP: no hace falta
 * tocar la configuración de Apache). Reenvía las rutas /api-* a los
 * archivos correspondientes de Backend/src y deja que el servidor
 * sirva todo lo demás desde Frontend/src — exactamente lo mismo que
 * hace el proxy de Frontend/nginx.conf en Docker, pero sin contenedores.
 *
 * Uso: parado en la raíz del proyecto (esta misma carpeta),
 *   php -S localhost:8080 -t Frontend/src router.php
 * y abrir http://localhost:8080/ en el navegador.
 *
 * (El "-t Frontend/src" es lo que hace que TODO lo que no sea /api-*
 * se sirva desde ahí; sin eso, buscaría landing.html en la raíz del
 * proyecto y no lo iba a encontrar — por eso Apache mostraba el
 * listado de archivos en vez de la página.)
 */

$backendRoot = __DIR__ . '/Backend/src';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$prefijosApi = ['/api-usuarios/', '/api-gestion/', '/api-incidencias/', '/api-recoleccion/', '/api-retiros/'];
foreach ($prefijosApi as $prefijo) {
    if (strpos($uri, $prefijo) === 0) {
        $archivo = $backendRoot . $uri;
        if (is_file($archivo)) {
            chdir(dirname($archivo));
            require $archivo;
            return true;
        }
        http_response_code(404);
        echo "No encontrado: $uri";
        return true;
    }
}

if ($uri === '/') {
    header('Location: /landing.html');
    return true;
}

// Cualquier otra cosa (HTML/CSS/JS/imágenes): que el servidor built-in
// la sirva solo desde el document root (Frontend/src, pasado con -t).
return false;
