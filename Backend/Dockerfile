# Imagen del backend de SiGeRU: PHP 8.2 + Apache + extensión PDO MySQL.
# Sirve únicamente los paquetes de la API (api-usuarios, api-gestion,
# api-incidencias, api-recoleccion) y Comun/ (conexión + helpers
# compartidos) — el frontend vive en su propio contenedor (ver
# ../Frontend/Dockerfile).
FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite deflate

# Comprime las respuestas JSON de la API antes de mandarlas (con miles de
# contenedores reales cargados, el JSON de contenedores.php pesa bastante;
# comprimido baja varias veces de tamaño y la carga del mapa/dashboard se
# siente más rápida sin tocar nada del código PHP).
RUN { \
      echo '<IfModule mod_deflate.c>'; \
      echo '  AddOutputFilterByType DEFLATE application/json text/html text/css application/javascript'; \
      echo '</IfModule>'; \
    } > /etc/apache2/conf-available/deflate-json.conf \
    && a2enconf deflate-json

# Evita que warnings/errores de PHP se impriman como HTML dentro de las
# respuestas JSON de la API (ver errores-produccion.ini para el detalle).
COPY errores-produccion.ini /usr/local/etc/php/conf.d/zz-errores-produccion.ini

# El build context de este Dockerfile es la carpeta Backend/ (ver
# docker-compose.yml: "build: ./Backend"), así que "src/" acá es
# Backend/src/.
COPY src/ /var/www/html/

# La conexión (Comun/conexion.php) se configura 100% por variables de
# entorno (SIGERU_DB_*) — ver docker-compose.yml — no hace falta tocar
# código para apuntar a la base del contenedor "db".

EXPOSE 80
