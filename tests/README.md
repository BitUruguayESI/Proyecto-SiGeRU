# Tests automatizados de la API

Son tests de integración: cada uno levanta usuarios de prueba vía la
propia API (`registro.php`/`login.php`) y pega por HTTP contra un
servidor real, corriendo sobre una base de datos de test separada
(`sigeru_test`, nunca la de desarrollo). No son tests unitarios puros
porque los endpoints son scripts que llaman a `exit()`/`header()`
directamente — no hay forma de instanciarlos ni de llamarlos como
funciones aisladas — así que probar "la API funcionando mediante
código" significa esto: automatizar exactamente lo mismo que se venía
probando a mano con `curl` durante todo el desarrollo.

Cubren:

- **UsuariosApiTest** — alta con y sin teléfonos, CI/email duplicado
  rechazado, cargo obligatorio para Operario, login correcto e
  incorrecto, y que un usuario dado de baja no pueda loguearse.
- **ContenedoresApiTest** — CRUD completo como Administrador (alta,
  edición de estado, baja lógica), y el contrato de autorización
  (401 sin sesión, 403 con sesión de Operario intentando escribir).
- **IncidenciasApiTest** — la máquina de estados de 3 pasos: reporte
  público, que "Desborde" marque el contenedor, que no se pueda saltar
  de PENDIENTE a EN_PROCESO sin pasar por asignar cuadrilla (que ahora
  deja la incidencia directo en EN_PROCESO), el ciclo completo hasta
  RESUELTA con la reversión automática del contenedor, que el
  Recolector no pueda resolver una incidencia pero el Chofer sí, y que
  tanto `listar.php` como `contenedores_disponibles.php` devuelvan la
  dirección del contenedor (no la "coordenadas" cruda).
- **RolesYCuadrillasApiTest** — cambiar el rol de un usuario (Recolector
  ↔ Chofer ↔ Administrador) y que el discriminador RN01 y el cargo
  queden consistentes; que un Administrador no pueda cambiarse el rol a
  sí mismo; rol inválido rechazado; alta/edición de una cuadrilla con
  camión + chofer + recolectores editable; y que no se pueda asignar el
  mismo chofer a dos cuadrillas.
- **AsignacionesApiTest** — alta de una asignación diaria (RF23) y su
  edición (PUT: cambiar fecha/turno/ruta y la lista de operarios sin
  borrar y recrear la asignación), que editar una asignación inexistente
  devuelva 404, y que un Operario no pueda editar asignaciones (403).

## Cómo correrlos

```
bash tests/run-tests.sh
```

Este script solo:
1. Recrea `sigeru_test` desde `sql/sigeru_schema.sql` (nunca toca
   la base `sigeru` de desarrollo).
2. Levanta un servidor PHP dedicado apuntando a esa base.
3. Corre los tests y apaga el servidor al final.

## Sobre PHPUnit

Los tests están escritos como clases `PHPUnit\Framework\TestCase`
normales — es el estándar de la industria y lo que pide la consigna.
Para usar PHPUnit real:

```
composer require --dev phpunit/phpunit
bash tests/run-tests.sh   # ahora detecta vendor/bin/phpunit y lo usa
```

Este entorno de desarrollo no tenía salida a internet para bajar
PHPUnit de Packagist, así que además se incluye un runner mínimo
(`tests/support/TestCaseShim.php` + `tests/run.php`) que implementa
nada más que los métodos de aserción que usan estos tests
(`assertSame`, `assertTrue`, etc.), para poder ejecutar la suite igual
mientras tanto — **sin cambiar una sola línea de los archivos de
test**. `tests/bootstrap.php` elige automáticamente uno u otro: usa
PHPUnit real si existe `vendor/autoload.php`, o el shim si no.

Corrida en este entorno (con el runner incluido, ver más arriba):
`Tests: 24, Fallos: 0`.
