# Imagen del frontend de SiGeRU: nginx sirviendo los archivos estáticos
# (HTML/CSS/JS) y haciendo de proxy de /api-usuarios, /api-gestion,
# /api-incidencias y /api-recoleccion hacia el contenedor del backend
# (ver nginx.conf) — así el navegador ve todo bajo el mismo origen
# (mismo host:puerto), sin problemas de CORS ni de cookies de sesión
# entre contenedores distintos.
FROM nginx:1.27-alpine

# El build context de este Dockerfile es la carpeta Frontend/ (ver
# docker-compose.yml: "build: ./Frontend"), así que "src/" acá es
# Frontend/src/.
COPY src/ /usr/share/nginx/html/
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
