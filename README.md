# SiGeRU — Backend + Frontend

Módulos funcionales: alta y CRUD completo de usuarios (Operario/
Administrador) e inicio de sesión (api-usuarios), landing page pública con
consulta pública (RN21) y reporte de incidencias por vecinos
(api-incidencias), gestión de contenedores/rutas/cuadrillas/camiones/
instalaciones/maquinaria (api-gestion), asignación diaria + descargas +
mantenimiento de camión (api-recoleccion), y dashboard + navegación para
el personal. Todo sobre la base de datos de 25 tablas ya creada, con datos
de prueba cargados, tests automatizados y Dockerfile/docker-compose para
levantarlo sin instalar nada a mano — los 4 paquetes del backend
(api-usuarios, api-gestion, api-incidencias, api-recoleccion) del Diagrama
de Paquetes ya están completos.

Probado de punta a punta contra base de datos real (MariaDB) y servidor PHP
real: registro con y sin teléfonos, registro duplicado (rechazado), login
correcto e incorrecto, consulta pública, api-gestion completo (alta/edición/
baja, control de que solo ADMINISTRADOR pueda escribir, errores de
integridad referencial con mensaje claro), y el ciclo de vida completo de
una incidencia — reporte público → asignar cuadrilla → en proceso →
resuelta (con reversión automática del contenedor a OPERATIVO), y también
el camino en proceso → escalada → reasignar cuadrilla — validando que la
máquina de estados rechace transiciones que no corresponden al Diagrama de
Estados (ej: no se puede pasar de PENDIENTE a EN_PROCESO sin asignar
cuadrilla primero).

## Estructura (mapea 1 a 1 con el Diagrama de Paquetes)

```
sigeru/
  Comun/              conexion.php (PDO) y funciones.php (helpers + auth por rol)
  api-usuarios/        registro.php, login.php, logout.php, quien_soy.php,
                        usuarios.php (CRUD completo), listar_operarios.php
  api-gestion/         contenedores.php, rutas.php, cuadrillas.php,
                        camiones.php, instalaciones.php, maquinaria.php,
                        repuestos.php, registrar_consulta.php
  api-incidencias/      contenedores_disponibles.php, registrar.php, listar.php,
                        asignar_cuadrilla.php, actualizar_estado.php
  api-recoleccion/      asignaciones.php, descargas.php, mantenimiento.php
  Frontend/             landing.html, login.html, registro.html, consulta.html,
                        reportar_incidencia.html, dashboard.html,
                        panel_gestion.html, panel_incidencias.html,
                        panel_recoleccion.html, css/, js/
  sql/                  sigeru_schema.sql (25 tablas) y datos_prueba.sql
  tests/                tests de integración de la API (ver tests/README.md)
  Dockerfile, docker-compose.yml, .dockerignore, phpunit.xml, composer.json
```

Es un solo proyecto PHP (no microservicios separados): cada paquete del
diagrama es una carpeta. Si más adelante hace falta separarlos en
contenedores Docker independientes, la división lógica ya está hecha.

## Cómo levantarlo

1. Crear la base de datos (si no la tenés creada todavía). Importante:
   siempre con `--default-character-set=utf8mb4`, si no los acentos y la
   "ñ" quedan mal guardados (no es un problema de la aplicación, es el
   cliente `mysql` interpretando mal el archivo):
   ```
   mysql -u root -p --default-character-set=utf8mb4 < sql/sigeru_schema.sql
   ```
   Opcionalmente, para tener datos de ejemplo (usuarios, contenedores,
   incidencias en distintos estados, etc. — ver "Datos de prueba" más
   abajo):
   ```
   mysql -u root -p --default-character-set=utf8mb4 < sql/datos_prueba.sql
   ```

2. Crear un usuario de MySQL para que la aplicación no use `root`:
   ```sql
   CREATE USER 'sigeru_app'@'localhost' IDENTIFIED BY 'elegí-una-clave';
   GRANT ALL PRIVILEGES ON sigeru.* TO 'sigeru_app'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. Configurar la conexión con variables de entorno (no hace falta tocar
   código). Si no se definen, `Comun/conexion.php` usa por defecto
   `127.0.0.1`, base `sigeru`, usuario `root`, sin contraseña.
   ```
   export SIGERU_DB_HOST=127.0.0.1
   export SIGERU_DB_NAME=sigeru
   export SIGERU_DB_USER=sigeru_app
   export SIGERU_DB_PASS=elegí-una-clave
   ```

4. Levantar el servidor (para probar local; en tus 3 servidores Linux
   sería Apache/Nginx + PHP-FPM apuntando a esta misma carpeta):
   ```
   php -S 127.0.0.1:8000
   ```

5. Abrir `http://127.0.0.1:8000/` en el navegador (te lleva a la landing).

## Levantarlo con Docker (alternativa a los pasos 1-4)

Con Docker no hace falta instalar MySQL/MariaDB ni PHP a mano:

```
docker compose up --build
```

Esto levanta dos contenedores: `db` (MariaDB 11, que al crearse por
primera vez carga automáticamente `sql/sigeru_schema.sql` y después
`sql/datos_prueba.sql` — ver "Datos de prueba" abajo) y `app` (PHP 8.2 +
Apache con el código de este repo, ya configurado por variables de
entorno para conectarse a `db`). La app queda en `http://localhost:8080/`.

Para arrancar de cero (borra los datos cargados; la próxima vez se
vuelven a cargar los scripts de `sql/`):
```
docker compose down -v
```

Nota: el `docker-compose.yml` y el `Dockerfile` se armaron y se
validaron (`docker compose config`, y cada script `sql/` corriendo
contra una base MariaDB real) en este entorno, pero acá no hay salida
a internet para bajar las imágenes de Docker Hub, así que el
`docker compose up --build` en sí no se pudo ejecutar de punta a punta
en esta sesión. Probalo en tu máquina y avisame si algo no levanta.

## Datos de prueba

`sql/datos_prueba.sql` carga usuarios, rutas, cuadrillas, contenedores,
camiones, instalaciones, maquinaria con repuestos, incidencias en varios
estados del Diagrama de Estados, una asignación diaria con sus
operarios, descargas, un mantenimiento y consultas públicas — para no
tener que dar de alta todo a mano antes de mostrar la app funcionando.

Todos los usuarios de prueba (`10000001`/`10000002` Administradores,
`20000001`-`20000004` Operarios) tienen la contraseña `test1234`.

## Tests automatizados

```
bash tests/run-tests.sh
```

13 tests de integración (usuarios, CRUD de contenedores + autorización
por rol, máquina de estados de incidencias completa) contra una base
de datos de test separada (`sigeru_test`, nunca toca la de desarrollo).
Ver `tests/README.md` para el detalle de qué cubren y cómo correrlos
con PHPUnit real.

## Notas de diseño

- `USUARIO.tipo_usuario` (ENUM) + triggers en la base resuelven RN01
  (generalización disjunta y total hacia OPERARIO/ADMINISTRADOR): no es
  posible que un mismo usuario quede como los dos tipos a la vez, ni que
  se inserte en la tabla hija que no corresponde.
- El alta de un usuario hace 2 (o 3, si carga teléfonos) INSERT dentro de
  una misma transacción PHP (`beginTransaction`/`commit`/`rollBack`):
  USUARIO, luego OPERARIO o ADMINISTRADOR, luego USUARIO_TELEFONO.
- Las contraseñas se guardan con `password_hash()` (bcrypt), nunca en
  texto plano; el login usa `password_verify()`.
- VECINO no pasa por el login — no tiene cuenta. La consulta pública
  (RN21) y el reporte de incidencias tampoco requieren sesión: en
  `api-incidencias/registrar.php` se identifica solo con su email (se
  reutiliza el VECINO si ese email ya existe, o se crea uno nuevo).
- Máquina de estados de INCIDENCIA (RF18-RF20): PENDIENTE/ESCALADA →
  ASIGNADA se hace en `asignar_cuadrilla.php` (requiere elegir cuadrilla,
  solo Administrador); ASIGNADA → EN_PROCESO → RESUELTA|ESCALADA se hace
  en `actualizar_estado.php` (cualquier staff logueado), que valida la
  transición contra el Diagrama de Estados y rechaza las que no
  corresponden. Si el tipo de incidencia es "Desborde", el contenedor
  pasa a DESBORDADO automáticamente al reportarse, y vuelve a OPERATIVO
  al resolverse (simplificación: no chequea si hay otra incidencia
  abierta sobre el mismo contenedor).
- Asignación diaria (RF23): la crea el Administrador (se resuelve
  `id_administrador` a partir de su sesión) y junta cuadrilla + ruta +
  camión + una lista de operarios con rol ("Chofer"/"Recolector") vía
  `ASIGNACION_OPERARIO`. RF25 ("consultar rutas asignadas") se resuelve
  con `?mias=1` en `asignaciones.php`, que filtra por el `id_operario`
  del usuario logueado — así el Operario ve solo lo suyo sin necesitar
  un endpoint aparte.
- Registrar mantenimiento (RF27) inserta en el historial
  (`MANTENIMIENTO_CAMION`) y además actualiza el resumen en `CAMION`
  (kilometraje, fecha_ultimo_mantenimiento) en la misma transacción — ojo
  si tocás ese código: hay que capturar `lastInsertId()` del INSERT antes
  del UPDATE que sigue, porque un UPDATE posterior en la misma conexión
  lo resetea a 0 (bug real que apareció y se corrigió durante las pruebas).
- `Comun/funciones.php` tiene `requerirSesionActiva()` (cualquier
  Operario/Administrador logueado) y `requerirRolAdministrador()` (solo
  Administrador). Todos los endpoints de escritura en api-gestion usan la
  segunda; listar (GET) usa la primera. Sin sesión: HTTP 401. Con sesión
  pero sin el rol necesario: HTTP 403.
- "Dar de baja" en contenedores y camiones es baja lógica (estado
  `FUERA_DE_SERVICIO`), no DELETE físico, para no perder el historial de
  incidencias/descargas/mantenimientos asociado. Rutas, cuadrillas e
  instalaciones sí se eliminan físicamente si nada las referencia; si
  algo las referencia (ej. una asignación diaria), el DELETE se rechaza
  con un mensaje claro en vez de romper con un error de MySQL.

## Dashboard y navegación

Al loguearse, el ADMINISTRADOR cae en `dashboard.html` (RF12: tarjetas con
conteos reales — contenedores totales/desbordados, camiones disponibles,
cuadrillas, rutas, instalaciones); el OPERARIO cae en `panel_incidencias.html`
directamente, porque es su responsabilidad principal por ahora. El nav de
arriba (`js/nav_admin.js`, inyectado en cualquier página que tenga
`<div id="navAdmin">` y `<body data-pagina="...">`) lleva a Dashboard,
"Gestión de recursos" (`panel_gestion.html`) e "Incidencias"
(`panel_incidencias.html`) y "Recolección" (`panel_recoleccion.html`). El
mismo nav resuelve "Cerrar sesión" (llama a `logout.php` y vuelve a la
landing) y muestra el nombre del usuario logueado vía
`api-usuarios/quien_soy.php`.

Con esto, los 4 paquetes del backend del Diagrama de Paquetes
(api-usuarios, api-gestion, api-incidencias, api-recoleccion) ya existen y
están conectados entre sí (ej: `panel_recoleccion.html` usa cuadrillas y
camiones de api-gestion, y operarios de api-usuarios, para armar la
asignación diaria).

## Próximos pasos sugeridos

- Notificaciones (RF33-RF35): hoy la tabla NOTIFICACION existe pero nada la
  escribe todavía; se puede insertar una fila ahí cuando cambia el estado
  de una incidencia, se asigna una cuadrilla, o se crea una asignación diaria.
- Reportes exportables (RF15, PDF/Excel) y mapa interactivo (RF13) para el
  Dashboard — quedaron mencionados ahí como pendientes.
- Un panel propio para el Operario más orientado a "mis tareas de hoy"
  (incidencias asignadas a su cuadrilla + su asignación diaria del día)
  en vez de los listados completos que hoy comparte con el Administrador.
- Auditoría (RF16, RN18): la tabla AUDITORIA existe pero ningún endpoint
  todavía inserta ahí cuando un Administrador hace una acción crítica.
