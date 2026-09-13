<?php
/**
 * api-usuarios/usuarios.php
 * CRUD completo de usuarios (OPERARIO y ADMINISTRADOR) para el
 * Administrador. El alta sigue siendo registro.php (autorregistro);
 * este archivo cubre listar-todos, editar y dar de baja, que es lo
 * que le falta a registro.php para ser un CRUD completo.
 *
 * Todo el archivo requiere rol ADMINISTRADOR.
 *
 * GET  ?ci=12345678          -> un usuario (con teléfonos)
 * GET  ?tipo_usuario=OPERARIO&estado=ACTIVO  (ambos opcionales) -> lista
 * PUT  { ci, nombre_completo?, email?, rol?, contrasena?, telefonos?, estado? }
 *      rol: 'ADMINISTRADOR' | 'CHOFER' | 'RECOLECTOR' — el "rol" que ve
 *      el Administrador en el panel de Usuarios. Reemplaza al viejo
 *      "cargo" (ahora es cargo el que vive dentro de rol, para OPERARIO).
 * DELETE { ci }              -> baja lógica: USUARIO.estado = INACTIVO
 *
 * Cambiar de rol entre Administrador y Recolector/Chofer SÍ cambia
 * tipo_usuario (la generalización disjunta OPERARIO/ADMINISTRADOR,
 * RN01): se hace en una transacción que borra la fila hija vieja,
 * actualiza el discriminador y crea la fila hija nueva, en ese orden
 * (los triggers de la base bloquean el UPDATE de tipo_usuario mientras
 * exista la fila hija vieja, así que hay que borrarla primero). Un
 * Administrador no puede cambiarse el rol a sí mismo (para no quedarse
 * sin acceso de administrador sin querer).
 */

declare(strict_types=1);

require_once __DIR__ . '/../Comun/conexion.php';
require_once __DIR__ . '/../Comun/funciones.php';

$sesionAdministrador = requerirRolAdministrador();

$pdo = obtenerConexion();
$metodo = $_SERVER['REQUEST_METHOD'];

function usuarioConDetalle(PDO $pdo, string $ci): ?array
{
    $consulta = $pdo->prepare(
        'SELECT u.ci, u.email, u.nombre_completo, u.tipo_usuario, u.estado, u.fecha_alta,
                o.cargo
         FROM USUARIO u
         LEFT JOIN OPERARIO o ON o.ci_usuario = u.ci
         WHERE u.ci = :ci'
    );
    $consulta->execute(['ci' => $ci]);
    $usuario = $consulta->fetch();
    if (!$usuario) {
        return null;
    }

    $telefonos = $pdo->prepare('SELECT telefono FROM USUARIO_TELEFONO WHERE ci_usuario = :ci ORDER BY id_telefono');
    $telefonos->execute(['ci' => $ci]);
    $usuario['telefonos'] = array_column($telefonos->fetchAll(), 'telefono');
    $usuario['rol'] = $usuario['tipo_usuario'] === 'ADMINISTRADOR' ? 'ADMINISTRADOR' : $usuario['cargo'];

    return $usuario;
}

if ($metodo === 'GET') {
    if (isset($_GET['ci'])) {
        $usuario = usuarioConDetalle($pdo, limpiarTexto($_GET['ci']));
        if (!$usuario) {
            responderJson(['exito' => false, 'mensaje' => 'Usuario no encontrado.'], 404);
        }
        responderJson(['exito' => true, 'usuario' => $usuario]);
    }

    $condiciones = [];
    $valores = [];
    if (!empty($_GET['tipo_usuario'])) {
        $condiciones[] = 'u.tipo_usuario = :tipo_usuario';
        $valores['tipo_usuario'] = strtoupper(limpiarTexto($_GET['tipo_usuario']));
    }
    if (!empty($_GET['estado'])) {
        $condiciones[] = 'u.estado = :estado';
        $valores['estado'] = strtoupper(limpiarTexto($_GET['estado']));
    }
    if (!empty($_GET['cargo'])) {
        $condiciones[] = 'o.cargo = :cargo';
        $valores['cargo'] = strtoupper(limpiarTexto($_GET['cargo']));
    }
    $whereSql = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

    $consulta = $pdo->prepare(
        "SELECT u.ci, u.email, u.nombre_completo, u.tipo_usuario, u.estado, u.fecha_alta, o.cargo
         FROM USUARIO u
         LEFT JOIN OPERARIO o ON o.ci_usuario = u.ci
         $whereSql
         ORDER BY u.nombre_completo"
    );
    $consulta->execute($valores);
    $usuarios = array_map(function ($u) {
        $u['rol'] = $u['tipo_usuario'] === 'ADMINISTRADOR' ? 'ADMINISTRADOR' : $u['cargo'];
        return $u;
    }, $consulta->fetchAll());
    responderJson(['exito' => true, 'usuarios' => $usuarios]);
}

if ($metodo === 'PUT') {
    $entrada = obtenerJsonEntrada();
    $ci = limpiarTexto($entrada['ci'] ?? '');

    if ($ci === '') {
        responderJson(['exito' => false, 'mensaje' => 'ci es obligatorio.'], 400);
    }

    $consulta = $pdo->prepare('SELECT tipo_usuario FROM USUARIO WHERE ci = :ci');
    $consulta->execute(['ci' => $ci]);
    $usuarioActual = $consulta->fetch();
    if (!$usuarioActual) {
        responderJson(['exito' => false, 'mensaje' => 'Usuario no encontrado.'], 404);
    }

    $campos = [];
    $valores = ['ci' => $ci];

    if (isset($entrada['nombre_completo'])) {
        $nombre = limpiarTexto($entrada['nombre_completo']);
        if ($nombre === '') {
            responderJson(['exito' => false, 'mensaje' => 'El nombre completo no puede quedar vacío.'], 400);
        }
        $campos[] = 'nombre_completo = :nombre_completo';
        $valores['nombre_completo'] = $nombre;
    }

    if (isset($entrada['email'])) {
        $email = limpiarTexto($entrada['email']);
        if (!esEmailValido($email)) {
            responderJson(['exito' => false, 'mensaje' => 'El email no es válido.'], 400);
        }
        $duplicado = $pdo->prepare('SELECT ci FROM USUARIO WHERE email = :email AND ci <> :ci');
        $duplicado->execute(['email' => $email, 'ci' => $ci]);
        if ($duplicado->fetch()) {
            responderJson(['exito' => false, 'mensaje' => 'Ese email ya está en uso por otro usuario.'], 409);
        }
        $campos[] = 'email = :email';
        $valores['email'] = $email;
    }

    if (isset($entrada['contrasena']) && $entrada['contrasena'] !== '') {
        $nuevaContrasena = (string) $entrada['contrasena'];
        if (strlen($nuevaContrasena) < 8) {
            responderJson(['exito' => false, 'mensaje' => 'La contraseña debe tener al menos 8 caracteres.'], 400);
        }
        $campos[] = 'contrasena = :contrasena';
        $valores['contrasena'] = password_hash($nuevaContrasena, PASSWORD_DEFAULT);
    }

    if (isset($entrada['estado'])) {
        $estado = strtoupper(limpiarTexto($entrada['estado']));
        if (!in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            responderJson(['exito' => false, 'mensaje' => 'Estado inválido: debe ser ACTIVO o INACTIVO.'], 400);
        }
        $campos[] = 'estado = :estado';
        $valores['estado'] = $estado;
    }

    // Rol: ADMINISTRADOR, CHOFER o RECOLECTOR. Se valida acá (antes de la
    // transacción) para responder rápido con 400/409 sin dejar nada a medio
    // aplicar; el cambio de fila hija (si corresponde) se hace más abajo.
    $rolNuevo = null;
    $tipoNuevo = null;
    if (isset($entrada['rol'])) {
        $rolNuevo = strtoupper(limpiarTexto($entrada['rol']));
        if (!in_array($rolNuevo, ['ADMINISTRADOR', 'CHOFER', 'RECOLECTOR'], true)) {
            responderJson(['exito' => false, 'mensaje' => 'rol inválido: debe ser ADMINISTRADOR, CHOFER o RECOLECTOR.'], 400);
        }
        if ($ci === $sesionAdministrador['ci_usuario']) {
            responderJson(['exito' => false, 'mensaje' => 'No podés cambiar tu propio rol.'], 409);
        }
        $tipoNuevo = $rolNuevo === 'ADMINISTRADOR' ? 'ADMINISTRADOR' : 'OPERARIO';
    }

    try {
        $pdo->beginTransaction();

        if ($campos) {
            $sql = 'UPDATE USUARIO SET ' . implode(', ', $campos) . ' WHERE ci = :ci';
            $pdo->prepare($sql)->execute($valores);
        }

        if ($rolNuevo !== null) {
            $tipoActual = $usuarioActual['tipo_usuario'];
            if ($tipoActual === $tipoNuevo) {
                // Mismo tipo_usuario: si es OPERARIO, puede estar
                // cambiando de CHOFER a RECOLECTOR (o viceversa). Si
                // cambia, hay que sacarlo de donde ya no corresponde
                // (una cuadrilla lo tenía como chofer o como recolector
                // según el cargo viejo) para no dejar datos inconsistentes.
                if ($tipoNuevo === 'OPERARIO') {
                    $idOperario = $pdo->prepare('SELECT id_operario FROM OPERARIO WHERE ci_usuario = :ci');
                    $idOperario->execute(['ci' => $ci]);
                    $filaOperario = $idOperario->fetch();

                    $pdo->prepare('UPDATE OPERARIO SET cargo = :cargo WHERE ci_usuario = :ci')
                        ->execute(['cargo' => $rolNuevo, 'ci' => $ci]);

                    if ($filaOperario) {
                        if ($rolNuevo === 'CHOFER') {
                            $pdo->prepare('DELETE FROM CUADRILLA_RECOLECTOR WHERE id_operario = :id')
                                ->execute(['id' => $filaOperario['id_operario']]);
                        } else {
                            $pdo->prepare('UPDATE CUADRILLA SET id_chofer = NULL WHERE id_chofer = :id')
                                ->execute(['id' => $filaOperario['id_operario']]);
                        }
                    }
                }
            } else {
                // Cambia la generalización (RN01): borrar la fila hija
                // vieja primero (el trigger trg_usuario_check_cambio_tipo
                // bloquea el UPDATE de tipo_usuario mientras siga existiendo),
                // actualizar el discriminador, y crear la fila hija nueva.
                // Borrar de OPERARIO además libera automáticamente (por las
                // FK ON DELETE SET NULL/CASCADE) cualquier cuadrilla estable
                // donde este usuario fuera chofer o recolector.
                if ($tipoActual === 'OPERARIO') {
                    $pdo->prepare('DELETE FROM OPERARIO WHERE ci_usuario = :ci')->execute(['ci' => $ci]);
                } else {
                    $pdo->prepare('DELETE FROM ADMINISTRADOR WHERE ci_usuario = :ci')->execute(['ci' => $ci]);
                }
                $pdo->prepare('UPDATE USUARIO SET tipo_usuario = :tipo WHERE ci = :ci')
                    ->execute(['tipo' => $tipoNuevo, 'ci' => $ci]);
                if ($tipoNuevo === 'OPERARIO') {
                    $pdo->prepare('INSERT INTO OPERARIO (cargo, ci_usuario) VALUES (:cargo, :ci)')
                        ->execute(['cargo' => $rolNuevo, 'ci' => $ci]);
                } else {
                    $pdo->prepare('INSERT INTO ADMINISTRADOR (ci_usuario) VALUES (:ci)')->execute(['ci' => $ci]);
                }
            }
        }

        // Teléfonos: reemplazo completo (mismo patrón que CONTENEDOR_TIPO_RESIDUO).
        if (isset($entrada['telefonos']) && is_array($entrada['telefonos'])) {
            $pdo->prepare('DELETE FROM USUARIO_TELEFONO WHERE ci_usuario = :ci')->execute(['ci' => $ci]);
            $insertarTelefono = $pdo->prepare(
                'INSERT INTO USUARIO_TELEFONO (ci_usuario, telefono) VALUES (:ci, :telefono)'
            );
            foreach ($entrada['telefonos'] as $telefono) {
                $telefono = limpiarTexto((string) $telefono);
                if ($telefono !== '') {
                    $insertarTelefono->execute(['ci' => $ci, 'telefono' => $telefono]);
                }
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        responderJson(['exito' => false, 'mensaje' => 'No se pudo actualizar el usuario: ' . $e->getMessage()], 500);
    }

    responderJson(['exito' => true, 'mensaje' => 'Usuario actualizado.']);
}

if ($metodo === 'DELETE') {
    $entrada = obtenerJsonEntrada();
    $ci = limpiarTexto($entrada['ci'] ?? '');

    if ($ci === '') {
        responderJson(['exito' => false, 'mensaje' => 'ci es obligatorio.'], 400);
    }

    $sesion = requerirRolAdministrador();
    if ($ci === $sesion['ci_usuario']) {
        responderJson(['exito' => false, 'mensaje' => 'No podés dar de baja tu propia cuenta.'], 409);
    }

    // Baja lógica: preserva el historial (incidencias reportadas/resueltas,
    // asignaciones, mantenimientos, etc.) que referencia a este usuario.
    $actualizar = $pdo->prepare("UPDATE USUARIO SET estado = 'INACTIVO' WHERE ci = :ci");
    $actualizar->execute(['ci' => $ci]);

    if ($actualizar->rowCount() === 0) {
        responderJson(['exito' => false, 'mensaje' => 'Usuario no encontrado.'], 404);
    }

    responderJson(['exito' => true, 'mensaje' => 'Usuario dado de baja.']);
}

responderJson(['exito' => false, 'mensaje' => 'Método no permitido.'], 405);
