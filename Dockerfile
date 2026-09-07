# Imagen de la aplicación SiGeRU: PHP 8.2 + Apache + extensión PDO MySQL.
# El proyecto es un único árbol PHP (ver README), así que alcanza con
# copiarlo entero a la raíz web de Apache.
FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite

COPY . /var/www/html/

# La conexión (Comun/conexion.php) se configura 100% por variables de
# entorno (SIGERU_DB_*) — ver docker-compose.yml — no hace falta tocar
# código para apuntar a la base del contenedor "db".

EXPOSE 80
