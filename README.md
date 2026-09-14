# SiGeRU — Backend + Frontend

Módulos funcionales: alta y CRUD completo de usuarios (Operario/
Administrador, con rol editable: Administrador/Recolector/Chofer) e inicio
de sesión (api-usuarios), landing page pública con consulta pública (RN21)
y reporte de incidencias por vecinos (api-incidencias), gestión de
contenedores/rutas/cuadrillas/camiones/instalaciones/maquinaria
(api-gestion) — incluida la "cuadrilla estable" (1 camión + 1 chofer + N
recolectores), asignación diaria + descargas + mantenimiento de camión
(api-recoleccion), y dashboards por rol para el personal (Administrador ve
todo; Recolector/Chofer ven "Mi día"). Todo sobre la base de datos de 26
tablas ya creada, con datos de prueba cargados, tests automatizados y un
docker-compose de 3 contenedores (db + backend + frontend) para
levantarlo sin instalar nada a mano — los 4 paquetes del backend
(api-usuarios, api-gestion, api-incidencias, api-recoleccion) del
Diagrama de Paquetes ya están completos.

Probado de punta a punta contra base de datos real (MariaDB) y servidor PHP
real: registro con y sin teléfonos, registro duplicado (rechazado), login
correcto e incorrecto, consulta pública, api-gestion completo (alta/edición/
baja, control de que solo ADMINISTRADOR pueda escribir, errores de
integridad referencial con mensaje claro), el ciclo de vida completo de una
incidencia — reporte público → asignar cuadrilla (pasa directo a EN_PROCESO)
→ resuelta (con reversión automática del contenedor a OPERATIVO, y
verificando que solo Administrador o Chofer puedan resolverla) — y el
cambio de rol de un usuario (Recolector ↔ Chofer ↔ Administrador),
incluyendo que un Administrador no pueda cambiarse el rol a sí mismo y que
la cuadrilla estable (camión/chofer/recolectores) quede consistente
después de un cambio de rol.

## Estructura (mapea 1 a 1 con el Diagrama de Paquetes)

```
sigeru/
  docker-compose.yml    levanta los 3 contenedores: db, backend, frontend

  Backend/               contenedor PHP 8.2 + Apache (paquetes del backend)
    Dockerfile
    src/
      Comun/             conexion.php (PDO) y funciones.php (helpers + auth por rol)
      api-usuarios/      registro.php, login.php, logout.php, quien_soy.php,
                         usuarios.php (CRUD completo), listar_operarios.php
      api-gestion/       contenedores.php, rutas.php, cuadrillas.php,
                         camiones.php, instalaciones.php, maquinaria.php,
                         repuestos.php, registrar_consulta.php
      api-incidencias/   contenedores_disponibles.php, registrar.php, listar.php,
                         asignar_cuadrilla.php, actualizar_estado.php
      api-recoleccion/   asignaciones.php, descargas.php, mantenimiento.php
      api-retiros/       registrar.php, listar.php, actualizar_estado.php
                         (retiro de residuos especiales: poda, residuos
                         electrónicos, escombros, muebles, etc. — no van
                         al contenedor común, se coordinan por solicitud)

  Frontend/              contenedor nginx (estático + proxy de /api-* al backend)
    Dockerfile
    nginx.conf
    src/                 landing.html, login.html, registro.html, consulta.html,
                         reportar_incidencia.html, solicitar_retiro.html,
                         dashboard.html, panel_gestion.html,
                         panel_incidencias.html, panel_recoleccion.html,
                         mi_dia.html (Recolector/Chofer), css/, js/ (incluye
                         sesion.js, compartido por nav_admin.js y las
                         páginas que necesitan saber el rol de la sesión
                         activa), img/ (logo + mapa de zonas decorativo)

  sql/                   sigeru_schema.sql (27 tablas), datos_prueba.sql,
                         actualizar_contenedor_direccion.sql,
                         actualizar_roles_cuadrillas_incidencias.sql y
                         agregar_solicitud_retiro.sql
                         (migraciones, ver "Cómo levantarlo")
  tests/                 tests de integración de la API (ver tests/README.md)
  phpunit.xml, composer.json
```

Cada paquete del Diagrama de Paquetes sigue siendo una carpeta bien
separada (ahora dentro de `Backend/src/`); lo que cambió es que
backend y frontend corren en contenedores Docker independientes en vez
de un único proyecto PHP. El frontend le habla al backend por HTTP
(`fetch('../api-usuarios/...')` etc., sin cambios en el código JS): el
nginx del contenedor `frontend` hace de proxy de esas rutas hacia el
contenedor `backend` (ver `Frontend/nginx.conf`), así el navegador ve
todo bajo el mismo origen — sin CORS, y las cookies de sesión de PHP
viajan igual que si fuera un único servidor.

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

   Si ya tenías la base `sigeru` creada de antes (con contenedores/rutas
   cargados a mano) y no querés perder esos datos reimportando todo desde
   cero, corré en cambio estas migraciones una sola vez, en este orden:
   ```
   mysql -u root -p --default-character-set=utf8mb4 sigeru < sql/actualizar_contenedor_direccion.sql
   mysql -u root -p --default-character-set=utf8mb4 sigeru < sql/actualizar_roles_cuadrillas_incidencias.sql
   mysql -u root -p --default-character-set=utf8mb4 sigeru < sql/agregar_solicitud_retiro.sql
   ```
   ⚠️ **Importante**: la segunda es la que agrega el rol editable
   (Administrador/Recolector/Chofer), la cuadrilla estable (camión + chofer
   + recolectores) y simplifica los estados de INCIDENCIA a 3 (PENDIENTE/
   EN_PROCESO/RESUELTA). Si no la corrés sobre una base ya existente, vas a
   ver el mismo tipo de error de "columna no existe" que dio la falta de la
   migración de `direccion` (JSON roto en el Dashboard y en Usuarios/
   Cuadrillas) — hacé un respaldo antes si tenés datos que te importen; el
   archivo tiene el detalle de qué hace cada paso. La tercera agrega la
   tabla `SOLICITUD_RETIRO` (retiro de residuos especiales, ver más abajo).

2. Crear un usuario de MySQL para que la aplicación no use `root`:
   ```sql
   CREATE USER 'sigeru_app'@'localhost' IDENTIFIED BY 'elegí-una-clave';
   GRANT ALL PRIVILEGES ON sigeru.* TO 'sigeru_app'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. Configurar la conexión con variables de entorno (no hace falta tocar
   código). Si no se definen, `Backend/src/Comun/conexion.php` usa por
   defecto `127.0.0.1`, base `sigeru`, usuario `root`, sin contraseña.
   ```
   export SIGERU_DB_HOST=127.0.0.1
   export SIGERU_DB_NAME=sigeru
   export SIGERU_DB_USER=sigeru_app
   export SIGERU_DB_PASS=elegí-una-clave
   ```

4. Levantar el backend (sirve los 5 paquetes de la API):
   ```
   php -S 127.0.0.1:8000 -t Backend/src
   ```

5. Servir el frontend apuntando al mismo origen que el backend. Sin
   Docker, no alcanza con abrir la carpeta del proyecto directo en
   Apache/XAMPP: como el sitio ahora vive repartido en `Backend/src` y
   `Frontend/src` (no hay `index.php` en la raíz), vas a ver el listado
   de archivos en vez de la página. La forma más simple de probarlo
   local sin instalar nginx es con el `router.php` incluido en la raíz
   del proyecto (hace exactamente lo que hace el proxy de
   `Frontend/nginx.conf` en Docker, pero para el servidor built-in de
   PHP): parado en la raíz del proyecto,
   ```
   php -S localhost:8080 -t Frontend/src router.php
   ```
   y abrir `http://localhost:8080/`. Para levantar los 3 contenedores
   reales (con ese proxy ya armado), ver "Levantarlo con Docker" abajo
   — es la forma recomendada, porque reproduce la separación real de
   contenedores.

## Levantarlo con Docker (recomendado)

Con Docker no hace falta instalar MySQL/MariaDB, PHP ni nginx a mano:

```
docker compose up --build
```

Esto levanta 3 contenedores:
- `db` — MariaDB 11; al crearse por primera vez carga automáticamente
  `sql/sigeru_schema.sql` y después `sql/datos_prueba.sql` (ver "Datos
  de prueba" abajo).
- `backend` — PHP 8.2 + Apache sirviendo `Backend/src/` (los 5
  paquetes de la API), configurado por variables de entorno para
  conectarse a `db`.
- `frontend` — nginx sirviendo `Frontend/src/` (HTML/CSS/JS) y
  haciendo de proxy de `/api-usuarios/`, `/api-gestion/`,
  `/api-incidencias/`, `/api-recoleccion/` y `/api-retiros/` hacia
  `backend` (ver `Frontend/nginx.conf`) — así el navegador ve todo
  bajo un mismo origen, sin problemas de CORS ni de cookies de sesión
  entre contenedores.

La app queda en `http://localhost:8080/` (landing). El backend también
queda expuesto directo en `http://localhost:8081/` por si hace falta
pegarle a la API sin pasar por el frontend al debuggear.

Para arrancar de cero (borra los datos cargados; la próxima vez se
vuelven a cargar los scripts de `sql/`):
```
docker compose down -v
```

Nota: el `docker-compose.yml` y los `Dockerfile` de `Backend/` y
`Frontend/` se armaron y se validaron (`docker compose config`, y cada
script `sql/` corriendo contra una base MariaDB real) en este entorno,
pero acá no hay salida a internet para bajar imágenes de Docker Hub,
así que el `docker compose up --build` en sí no se pudo ejecutar de
punta a punta en esta sesión. Probalo en tu máquina y avisame si algo
no levanta.

## Mapa de contenedores (RF13)

En la pestaña "Contenedores" del panel de gestión, el alta/edición se hace
con una **dirección** de texto (ej. "Bulevar Artigas 1234, Montevideo"), no
con coordenadas a mano. El backend (`api-gestion/contenedores.php`,
función `geocodificarDireccion()` en `Comun/funciones.php`) geocodifica esa
dirección contra **Nominatim (OpenStreetMap)** y guarda el resultado en
`coordenadas`; el mapa interactivo (Leaflet + tiles de OpenStreetMap, ambos
cargados desde CDN) se arma solo con eso, mostrando un marcador por
contenedor coloreado según su estado (verde operativo, rojo desbordado,
gris fuera de servicio).

Esto necesita que el servidor tenga salida a internet (para geocodificar y
para bajar Leaflet/las tiles del mapa desde el navegador). Si no hay
conexión, o Nominatim no encuentra la dirección, el contenedor se guarda
igual — solo que sin ubicación en el mapa todavía — y el aviso arriba de la
tabla te dice cuántos quedaron así; podés reintentar editándolos con una
dirección más precisa una vez que haya internet.

Del lado del vecino, `reportar_incidencia.html` (público, sin sesión) usa
esa misma dirección para identificar el contenedor: en vez de un `<select>`
con "Contenedor #N" y la `coordenadas` cruda, hay un campo de texto con
sugerencias (`<datalist>`, se completa solo con las direcciones ya
cargadas) — el vecino escribe o elige la dirección, y recién ahí se manda
el `id_contenedor` real por debajo. Al elegir una dirección con
coincidencia exacta se muestra un mapa Leaflet con un marcador en esa
ubicación (reutilizando la `coordenadas` ya geocodificada, sin volver a
llamar a Nominatim) para que el vecino confirme visualmente que es el
contenedor correcto; si la dirección no tiene ubicación cargada, o el
texto no matchea ninguna sugerencia, se avisa en texto y no se deja
enviar el formulario sin una dirección válida. El panel de incidencias
del Administrador (`panel_incidencias.js`) también muestra la dirección
del contenedor en vez de sus coordenadas.

## Solicitar retiro de residuos especiales

Reemplaza el lugar que antes ocupaba "Realizar una consulta" en la
landing: `solicitar_retiro.html` (público, sin sesión) es un formulario
para pedir el retiro de residuos que **no van al contenedor común**
(poda, residuos electrónicos, escombros, muebles, etc.). El vecino
indica el tipo de residuo, la dirección y, opcionalmente, una
descripción, y se identifica solo con su email — mismo patrón de
"buscar o crear VECINO por email" que usa `reportar_incidencia.html`.
Incluye además un mapa de zonas como imagen puramente decorativa
(`img/mapa-zonas.png`), sin datos reales de rutas ni de días de
recolección detrás.

No hay un día fijo por tipo de residuo: cada solicitud queda
`PENDIENTE` y el Administrador coordina el día/horario con el vecino
por email, caso a caso. El seguimiento se hace desde la pestaña
"Retiros especiales" del panel de gestión (`panel_gestion.html`):
lista las solicitudes (filtrables por estado) y permite marcarlas como
`RETIRADO` (backend: `api-retiros/registrar.php`, `listar.php`,
`actualizar_estado.php`; tabla `SOLICITUD_RETIRO`).

La consulta pública original (`consulta.html`, RN21) no se eliminó:
sigue funcionando igual que antes, solo dejó de estar linkeada desde
la landing.

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

42 tests de integración (usuarios, CRUD de contenedores + autorización
por rol, máquina de estados de incidencias de 3 pasos + quién puede
resolver, rol editable y cuadrilla estable, alta/edición de asignación
diaria, reporte de incidencias por dirección, el ciclo de vida
completo — alta/edición/baja + control de acceso por rol — de
camiones, instalaciones, maquinaria y repuestos, y solicitudes de
retiro de residuos especiales) contra una base de datos de test
separada (`sigeru_test`, nunca toca la de desarrollo).
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
- **Rol editable (Administrador/Recolector/Chofer)**: lo que el panel de
  Usuarios llama "rol" combina `USUARIO.tipo_usuario` (la generalización
  RN01) con `OPERARIO.cargo` (ahora `ENUM('CHOFER','RECOLECTOR')`, ya no
  texto libre). Cambiar el rol vía `PUT api-usuarios/usuarios.php { ci,
  rol }` puede significar solo un `UPDATE OPERARIO.cargo` (Recolector ↔
  Chofer) o, si cambia entre Administrador y Operario, una transacción que
  borra la fila hija vieja → actualiza `tipo_usuario` → inserta la fila
  hija nueva (los triggers de RN01 bloquean el `UPDATE` de `tipo_usuario`
  mientras exista la fila hija vieja, así que hay que borrarla primero).
  Un Administrador no puede cambiarse el rol a sí mismo (409), para no
  quedarse afuera sin querer. Al mover a alguien fuera de OPERARIO, o
  cambiarle el cargo, se limpia automáticamente cualquier cuadrilla
  estable donde figurara como chofer o recolector (por las FK `ON DELETE
  SET NULL`/`CASCADE`, más una limpieza explícita cuando solo cambia el
  cargo dentro de OPERARIO).
- **Cuadrilla estable** (`CUADRILLA.id_camion`/`id_chofer` + la tabla N:M
  `CUADRILLA_RECOLECTOR`): es "quién maneja qué camión y con quién" de
  forma permanente, para que el Recolector/Chofer sepan "la cuadrilla que
  les toca" sin depender de que exista una `ASIGNACION_DIARIA` para hoy.
  Es un concepto **adicional** a `ASIGNACION_DIARIA`/`ASIGNACION_OPERARIO`
  (que sigue existiendo para el detalle de fecha/turno/ruta puntual, RF23/
  RF25) — no lo reemplaza. Un camión o un operario (como chofer o como
  recolector) solo puede estar en una cuadrilla estable a la vez (UNIQUE
  KEY en la base); `api-gestion/cuadrillas.php` valida esto y devuelve un
  mensaje claro en vez de un error de MySQL.
- **Máquina de estados de INCIDENCIA simplificada a 3 estados** (RF18-RF20):
  PENDIENTE → EN_PROCESO se hace en `asignar_cuadrilla.php` (elegir
  cuadrilla, solo Administrador, pasa directo a EN_PROCESO — ya no hay un
  estado intermedio "ASIGNADA"); EN_PROCESO → RESUELTA se hace en
  `actualizar_estado.php`, restringido a Administrador o al Operario con
  cargo CHOFER (`requerirPuedeResolverIncidencia()` en `Comun/
  funciones.php` — el Recolector puede ver el estado pero no cerrarla). Si
  el tipo de incidencia es "Desborde", el contenedor pasa a DESBORDADO
  automáticamente al reportarse, y vuelve a OPERATIVO al resolverse
  (simplificación: no chequea si hay otra incidencia abierta sobre el
  mismo contenedor).
- Asignación diaria (RF23): la crea el Administrador (se resuelve
  `id_administrador` a partir de su sesión) y junta cuadrilla + ruta +
  camión + una lista de operarios con rol ("Chofer"/"Recolector") vía
  `ASIGNACION_OPERARIO`. También se puede editar (PUT, mismo endpoint):
  reemplaza fecha/turno/cuadrilla/ruta/camión y la lista completa de
  operarios (borra y vuelve a insertar en `ASIGNACION_OPERARIO`, mismo
  patrón "reemplazar todo" que se usa en `CUADRILLA_RECOLECTOR`), así se
  puede corregir una asignación sin borrarla y crearla de nuevo. La API
  sigue pidiendo `id_camion` y `operarios` tanto en el POST como en el
  PUT, pero en el formulario (`panel_recoleccion.js`) ya no se eligen a
  mano: al elegir la Cuadrilla se completan solos con su camión y chofer
  estables (`cuadrillas.php` ya los trae en la lista), y el select de
  Recolectores se limita a los de esa cuadrilla, todos premarcados por
  si alguno falta ese día (al editar, se preseleccionan los que
  realmente quedaron en esa asignación). RF25 ("consultar rutas
  asignadas") se resuelve con `?mias=1` en `asignaciones.php`, que
  filtra por el `id_operario` del usuario logueado — así el Operario ve
  solo lo suyo sin necesitar un endpoint aparte.
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

## Dashboards por rol y navegación

Cada rol ve un panel distinto (según lo que necesita para su tarea, no el
sistema completo):

- **Administrador**: al loguearse cae en `dashboard.html` (RF12: tarjetas
  con conteos reales, gráfico de incidencias por estado y las 5 más
  recientes). Ve el nav completo: Dashboard, "Gestión de recursos"
  (`panel_gestion.html`, incluye Usuarios con el rol editable y Cuadrillas
  con la cuadrilla estable), "Incidencias" (`panel_incidencias.html`,
  gestión de TODAS las incidencias), "Recolección"
  (`panel_recoleccion.html`) y "Asignación diaria" (acceso directo a esa
  pestaña de Recolección). Desde "Gestión de recursos" → Usuarios tiene
  un botón "+ Registrar nuevo usuario" que lleva a `registro.html` —
  esa página dejó de ser pública: ya no está linkeada desde la landing
  ni desde `login.html`, y al entrar hace `exigirAdministrador()` (te
  manda a `login.html`/`mi_dia.html` si no sos Administrador logueado).
  La landing (`landing.html`) ahora solo ofrece "Reportar una
  incidencia" y "Solicitar retiro de residuos especiales" al público;
  "Iniciar sesión" quedó como link en la esquina superior derecha del
  encabezado. `consulta.html` (la consulta pública, RN21) sigue
  existiendo y funcionando igual que antes — solo se desvinculó de la
  landing al reemplazar ese lugar con el nuevo flujo de retiro.
- **Recolector**: cae en `mi_dia.html` — su cuadrilla (camión, chofer,
  compañeros), su asignación diaria de hoy (reutiliza `?mias=1` de
  `asignaciones.php`) y las incidencias de su cuadrilla (sin poder
  resolverlas). Su nav solo tiene "Mi día".
- **Chofer**: lo mismo que el Recolector en `mi_dia.html`, más el botón
  para marcar una incidencia de su cuadrilla como resuelta, y un link a
  "Recolección" — ahí solo ve la pestaña Descargas (las de Asignación
  diaria/Mantenimiento son de Administrador), con su propio camión
  preseleccionado, para registrar sus descargas (RF24).

`js/sesion.js` (`obtenerSesion()`/`rolDeSesion()`/`exigirAdministrador()`)
centraliza el fetch a `api-usuarios/quien_soy.php` y lo usa tanto
`js/nav_admin.js` (arma el menú según el rol) como cada página para
decidir qué mostrar; `dashboard.js`, `panel_gestion.js` y ahora también
`registro.js` redirigen a `mi_dia.html` (o a `login.html` si no hay
sesión) si quien entra no es Administrador. Esto es una restricción
de UI (como ya hacía el nav mostrando/ocultando links), no de seguridad:
`api-usuarios/registro.php` en sí sigue siendo un endpoint público (sin
`requerirRolAdministrador()`) — se mantuvo así a propósito porque es lo
que usan los tests para crear sus propios usuarios de prueba sin depender
de una sesión previa, y porque en una instalación nueva sin
`datos_prueba.sql` es la única forma de crear el primer Administrador.
La autorización real para las acciones que sí importan sigue estando en
el backend (`requerirRolAdministrador()`, `requerirPuedeResolverIncidencia()`,
etc.) — ver "Notas de diseño" y "Próximos pasos sugeridos".

El nav resuelve "Cerrar sesión" (llama a `logout.php` y vuelve a la
landing) y muestra el nombre y rol del usuario logueado.

Con esto, los 4 paquetes del backend del Diagrama de Paquetes
(api-usuarios, api-gestion, api-incidencias, api-recoleccion) ya existen y
están conectados entre sí (ej: `panel_recoleccion.html` usa cuadrillas y
camiones de api-gestion, y operarios de api-usuarios, para armar la
asignación diaria).

## Próximos pasos sugeridos

- Notificaciones (RF33-RF35): hoy la tabla NOTIFICACION existe pero nada la
  escribe todavía; se puede insertar una fila ahí cuando cambia el estado
  de una incidencia, se asigna una cuadrilla, o se crea una asignación diaria.
- Reportes exportables (RF15, PDF/Excel) para el Dashboard.
- El mapa interactivo (RF13) hoy vive en la pestaña Contenedores del panel
  de gestión; se podría replicar/enlazar también desde el Dashboard, y
  extender la misma lógica de dirección+geocodificación a Instalaciones
  (que todavía usa coordenadas a mano).
- Auditoría (RF16, RN18): la tabla AUDITORIA existe pero ningún endpoint
  todavía inserta ahí cuando un Administrador hace una acción crítica —
  un buen primer caso sería justo el cambio de rol de un usuario.
- Restringir "resolver incidencia" al Chofer de ESA cuadrilla específica
  (hoy cualquier Chofer puede resolver cualquier incidencia EN_PROCESO,
  no solo las de su propia cuadrilla — simplificación consciente de esta
  vuelta, ver `requerirPuedeResolverIncidencia()`).
- Agregarle a `api-usuarios/registro.php` un `requerirRolAdministrador()`
  para que crear usuarios sea también una restricción de backend, no solo
  de UI (hoy `registro.html` está protegida en la página, pero el
  endpoint en sí sigue aceptando el alta sin sesión si se lo llama
  directo). Se dejó así a propósito esta vuelta para no tener que
  reescribir el bootstrap de los 42 tests (todos se autoregistran su
  propio Administrador de prueba antes de loguearse); si se hace este
  cambio, esos tests necesitan loguearse primero con un Administrador
  ya sembrado por `datos_prueba.sql`.
