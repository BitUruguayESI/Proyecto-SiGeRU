# tests/run-tests.ps1
# Equivalente en PowerShell de tests/run-tests.sh para esta PC (sin
# bash/WSL y sin PHP instalado en el host). Todo corre dentro de
# contenedores Docker: la base via "docker compose exec" y el propio
# PHP de prueba reusando la imagen ya construida del backend
# (proyecto-sigeruv2-backend), que ya tiene pdo_mysql instalado.

$ErrorActionPreference = "Stop"

$DB_TEST = "sigeru_test"
$PUERTO_TEST = 8988
$RED = "proyecto-sigeruv2_default"
$IMAGEN_BACKEND = "proyecto-sigeruv2-backend"

Write-Host "==> Asegurando permisos de sigeru_app sobre $DB_TEST"
docker compose exec -T db mysql -u root -prootpass_dev -e "GRANT ALL PRIVILEGES ON $DB_TEST.* TO 'sigeru_app'@'%'; FLUSH PRIVILEGES;" | Out-Null

Write-Host "==> Recreando base de datos de test: $DB_TEST"
docker compose exec -T db mysql -u sigeru_app -psigeru_pass_dev -e "DROP DATABASE IF EXISTS $DB_TEST; CREATE DATABASE $DB_TEST CHARACTER SET utf8mb4;"
if ($LASTEXITCODE -ne 0) { throw "Fallo recreando $DB_TEST" }

Write-Host "==> Cargando esquema en $DB_TEST"
$schema = Get-Content sql/sigeru_schema.sql -Raw
$lineas = $schema -split "`r?`n" | Where-Object {
    $_ -notmatch '^DROP DATABASE IF EXISTS sigeru;' -and
    $_ -notmatch '^CREATE DATABASE sigeru' -and
    $_ -notmatch '^USE sigeru;'
}
$schemaFiltrado = ($lineas -join "`n")
$schemaFiltrado | docker compose exec -T db mysql -u sigeru_app -psigeru_pass_dev --default-character-set=utf8mb4 $DB_TEST
if ($LASTEXITCODE -ne 0) { throw "Fallo cargando el esquema en $DB_TEST" }

Write-Host "==> Corriendo la suite dentro de un contenedor (imagen $IMAGEN_BACKEND)"
$proyectoDir = (Get-Location).Path
docker run --rm `
  --network $RED `
  -v "${proyectoDir}:/proyecto" `
  -w /proyecto `
  -e SIGERU_DB_HOST=db `
  -e SIGERU_DB_PORT=3306 `
  -e SIGERU_DB_NAME=$DB_TEST `
  -e SIGERU_DB_USER=sigeru_app `
  -e SIGERU_DB_PASS=sigeru_pass_dev `
  $IMAGEN_BACKEND `
  sh -c "php -S 127.0.0.1:${PUERTO_TEST} -t Backend/src >/tmp/srv.log 2>&1 & sleep 2; SIGERU_TEST_BASE_URL=http://127.0.0.1:${PUERTO_TEST}; export SIGERU_TEST_BASE_URL; if [ -x vendor/bin/phpunit ] || [ -f vendor/bin/phpunit ]; then php vendor/bin/phpunit --configuration phpunit.xml; else php tests/run.php; fi; EXIT_CODE=`$?; cat /tmp/srv.log; exit `$EXIT_CODE"

exit $LASTEXITCODE
