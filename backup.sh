#!/bin/bash
#
# backup.sh — Respaldo de la base de datos SiGeRU.
#
# Genera un dump de la base MySQL (contenedor "db") y lo empaqueta en
# un .tar.gz con fecha y hora, dentro de la carpeta backups/. Pensado
# para correr en el servidor donde vive docker-compose.yml, con los
# contenedores levantados (docker compose up).
#
# Uso:
#   ./backup.sh
#
# Requiere: docker, docker compose, tar. No requiere mysqldump en el
# host: se ejecuta dentro del contenedor "db" con docker compose exec.

set -euo pipefail

cd "$(dirname "$0")"

FECHA=$(date +%Y%m%d_%H%M%S)
CARPETA_BACKUPS="backups"
DUMP_SQL="sigeru_${FECHA}.sql"
ARCHIVO_TAR="${CARPETA_BACKUPS}/sigeru_backup_${FECHA}.tar.gz"

mkdir -p "$CARPETA_BACKUPS"

echo "==> Generando dump de la base de datos (contenedor db)..."
docker compose exec -T db sh -c 'exec mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" sigeru' > "$DUMP_SQL"

if [ ! -s "$DUMP_SQL" ]; then
    echo "ERROR: el dump quedo vacio. Verifica que el contenedor 'db' este levantado (docker compose ps)." >&2
    rm -f "$DUMP_SQL"
    exit 1
fi

echo "==> Comprimiendo dump en ${ARCHIVO_TAR}..."
tar -czf "$ARCHIVO_TAR" "$DUMP_SQL"

rm -f "$DUMP_SQL"

echo "==> Respaldo generado correctamente:"
ls -lh "$ARCHIVO_TAR"

echo "==> Verificando integridad del .tar.gz..."
tar -tzf "$ARCHIVO_TAR" > /dev/null && echo "    OK: el archivo .tar.gz es valido y se puede listar sin errores."
