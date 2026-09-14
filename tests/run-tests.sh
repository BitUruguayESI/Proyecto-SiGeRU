#!/usr/bin/env bash
#
# tests/run-tests.sh
# Orquesta una corrida completa de la suite: crea/limpia una base de
# datos de test dedicada (sigeru_test, separada de la de desarrollo),
# levanta un servidor PHP built-in apuntando a esa base, corre los
# tests (PHPUnit real si está instalado, si no el runner incluido
# tests/run.php) y al final apaga el servidor.
#
# Variables de entorno opcionales (mismos nombres que usa la app):
#   SIGERU_DB_HOST, SIGERU_DB_PORT, SIGERU_DB_USER, SIGERU_DB_PASS
#   SIGERU_TEST_DB_NAME   (default: sigeru_test)
#   SIGERU_TEST_PORT      (default: 8988, puerto del servidor de test)
#
# Por default usa el mismo usuario "sigeru_app" que el resto del
# proyecto (ver README, sección "Cómo levantarlo") — necesita permisos
# también sobre sigeru_test, además de sobre sigeru:
#   GRANT ALL PRIVILEGES ON sigeru_test.* TO 'sigeru_app'@'localhost';
#   GRANT ALL PRIVILEGES ON sigeru_test.* TO 'sigeru_app'@'127.0.0.1';

set -euo pipefail

DIR_PROYECTO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$DIR_PROYECTO"

DB_HOST="${SIGERU_DB_HOST:-127.0.0.1}"
DB_PORT="${SIGERU_DB_PORT:-3306}"
DB_USER="${SIGERU_DB_USER:-sigeru_app}"
DB_PASS="${SIGERU_DB_PASS:-sigeru_pass_dev}"
DB_TEST="${SIGERU_TEST_DB_NAME:-sigeru_test}"
PUERTO_TEST="${SIGERU_TEST_PORT:-8988}"

echo "==> Recreando base de datos de test: ${DB_TEST}"
MYSQL_PWD="$DB_PASS" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" \
  -e "DROP DATABASE IF EXISTS ${DB_TEST}; CREATE DATABASE ${DB_TEST} CHARACTER SET utf8mb4;"

# El script sql/sigeru_schema.sql arranca con DROP DATABASE/CREATE
# DATABASE/USE sobre "sigeru" a secas (ver el propio archivo). Hay que
# filtrar esas tres líneas SIEMPRE: si no, esto borraría la base de
# desarrollo "sigeru" sin importar a qué base esté conectado el cliente.
grep -v -E '^(DROP DATABASE IF EXISTS sigeru;|CREATE DATABASE sigeru|USE sigeru;)' sql/sigeru_schema.sql \
  | MYSQL_PWD="$DB_PASS" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" --default-character-set=utf8mb4 "$DB_TEST"

echo "==> Levantando servidor PHP de test en 127.0.0.1:${PUERTO_TEST}"
SIGERU_DB_HOST="$DB_HOST" SIGERU_DB_PORT="$DB_PORT" SIGERU_DB_NAME="$DB_TEST" \
  SIGERU_DB_USER="$DB_USER" SIGERU_DB_PASS="$DB_PASS" \
  php -S "127.0.0.1:${PUERTO_TEST}" -t "$DIR_PROYECTO/Backend/src" > /tmp/sigeru_test_server.log 2>&1 &
PID_SERVIDOR=$!

limpiar() {
  kill "$PID_SERVIDOR" 2>/dev/null || true
}
trap limpiar EXIT

# Esperar a que el servidor responda antes de arrancar los tests
# (cualquier respuesta HTTP sirve, incluso 401 — solo importa que ya
# esté aceptando conexiones).
for intento in $(seq 1 20); do
  if curl -s -o /dev/null "http://127.0.0.1:${PUERTO_TEST}/api-usuarios/quien_soy.php"; then
    break
  fi
  sleep 0.25
done

export SIGERU_TEST_BASE_URL="http://127.0.0.1:${PUERTO_TEST}"

if [ -x "vendor/bin/phpunit" ]; then
  echo "==> Corriendo con PHPUnit (vendor/bin/phpunit)"
  vendor/bin/phpunit --configuration phpunit.xml
else
  echo "==> PHPUnit no está instalado (no hay vendor/bin/phpunit)."
  echo "    Corriendo con el runner incluido (tests/run.php)."
  php tests/run.php
fi
