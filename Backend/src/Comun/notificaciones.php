<?php
/**
 * Comun/notificaciones.php
 * Generación de notificaciones/alertas (nivel avanzado 3.1). Este
 * proyecto no tiene un proceso en segundo plano (cron/worker), así que
 * las notificaciones se generan "a demanda": cada vez que el panel pide
 * el listado (api-gestion/notificaciones.php GET) se llama primero a
 * generarNotificacionesPendientes(), que revisa el estado actual del
 * sistema y crea una fila en NOTIFICACION por cada alerta nueva —
 * evitando duplicar una que ya esté sin leer para ese usuario. Para las
 * alertas más urgentes, además intenta mandar un email real
 * (Comun/correo.php) al Administrador, de forma best-effort.
 */

declare(strict_types=1);

require_once __DIR__ . '/correo.php';

const SIGERU_TIPOS_NOTIFICACION_URGENTES = [
    'CONTENEDOR_DESBORDADO',
    'CARNET_POR_VENCER',
    'LICENCIA_POR_VENCER',
];

/**
 * Crea una notificación para $ciUsuario si no existe ya una igual
 * (mismo tipo y mensaje), leída o no. Es deliberado que también cuente
 * las ya leídas: si no, marcar una como leída no serviría de nada
 * mientras la condición siga vigente (por ejemplo, una incidencia que
 * sigue pendiente) — el próximo refresco la volvería a crear sin leer.
 * Si cambia el mensaje (otro contenedor, otra fecha de vencimiento,
 * etc.) sí se genera una notificación nueva, porque es una alerta
 * distinta.
 *
 * No manda el email acá ni abre su propia transacción: si el tipo es
 * "urgente" y hay un email de destino, agrega el envío a
 * $correosPendientes (por referencia) para que
 * generarNotificacionesPendientes() los mande recién después del
 * commit — así la conexión a la base no queda abierta esperando una
 * respuesta de red del servidor SMTP.
 */
function crearNotificacionSiNoExiste(
    PDO $pdo,
    string $ciUsuario,
    string $tipo,
    string $mensaje,
    ?string $emailDestino,
    ?string $referencia,
    array &$correosPendientes
): void {
    $existe = $pdo->prepare(
        'SELECT id_notificacion FROM NOTIFICACION
         WHERE ci_usuario = :ci AND tipo = :tipo AND mensaje = :mensaje'
    );
    $existe->execute(['ci' => $ciUsuario, 'tipo' => $tipo, 'mensaje' => $mensaje]);
    if ($existe->fetch()) {
        return;
    }

    $pdo->prepare(
        'INSERT INTO NOTIFICACION (mensaje, tipo, ci_usuario, referencia) VALUES (:mensaje, :tipo, :ci, :referencia)'
    )->execute(['mensaje' => $mensaje, 'tipo' => $tipo, 'ci' => $ciUsuario, 'referencia' => $referencia]);

    if ($emailDestino && in_array($tipo, SIGERU_TIPOS_NOTIFICACION_URGENTES, true)) {
        $correosPendientes[] = ['destinatario' => $emailDestino, 'mensaje' => $mensaje];
    }
}

/**
 * Revisa el estado actual del sistema y genera las notificaciones que
 * falten, para todos los Administradores activos.
 *
 * Todos los INSERT de NOTIFICACION van en una única transacción: con
 * auto-commit (el default), cada INSERT se confirma por separado, y con
 * varios Administradores activos y varias alertas pendientes eso
 * significa muchos commits chicos seguidos — notoriamente más lento que
 * agrupar todo en un solo commit al final, sin cambiar qué se inserta ni
 * la lógica de deduplicación. Los emails (best-effort) se mandan recién
 * después del commit, fuera de la transacción.
 */
function generarNotificacionesPendientes(PDO $pdo): void
{
    $administradores = $pdo->query(
        "SELECT ci, email FROM USUARIO WHERE tipo_usuario = 'ADMINISTRADOR' AND estado = 'ACTIVO'"
    )->fetchAll();

    if (!$administradores) {
        return;
    }

    $correosPendientes = [];

    $pdo->beginTransaction();
    try {
        // 1) Contenedores desbordados (urgente: además email).
        $desbordados = $pdo->query(
            "SELECT id_contenedor, direccion FROM CONTENEDOR WHERE estado = 'DESBORDADO'"
        )->fetchAll();
        foreach ($desbordados as $c) {
            $direccion = $c['direccion'] ?: "contenedor #{$c['id_contenedor']}";
            $mensaje = "El contenedor #{$c['id_contenedor']} ({$direccion}) está desbordado.";
            foreach ($administradores as $admin) {
                crearNotificacionSiNoExiste($pdo, $admin['ci'], 'CONTENEDOR_DESBORDADO', $mensaje, $admin['email'], (string) $c['id_contenedor'], $correosPendientes);
            }
        }

        // 2) Incidencias pendientes (sin cuadrilla asignada todavía).
        $incidencias = $pdo->query(
            "SELECT id_incidencia, tipo_incidencia FROM INCIDENCIA WHERE estado = 'PENDIENTE'"
        )->fetchAll();
        foreach ($incidencias as $i) {
            $mensaje = "La incidencia #{$i['id_incidencia']} ({$i['tipo_incidencia']}) está pendiente de asignar.";
            foreach ($administradores as $admin) {
                crearNotificacionSiNoExiste($pdo, $admin['ci'], 'INCIDENCIA_PENDIENTE', $mensaje, null, (string) $i['id_incidencia'], $correosPendientes);
            }
        }

        // 3) Solicitudes de retiro sin respuesta para el vecino.
        $retiros = $pdo->query(
            "SELECT id_solicitud, tipo_residuo FROM SOLICITUD_RETIRO WHERE estado = 'PENDIENTE' AND respuesta_admin IS NULL"
        )->fetchAll();
        foreach ($retiros as $r) {
            $mensaje = "La solicitud de retiro #{$r['id_solicitud']} ({$r['tipo_residuo']}) todavía no tiene respuesta para el vecino.";
            foreach ($administradores as $admin) {
                crearNotificacionSiNoExiste($pdo, $admin['ci'], 'RETIRO_SIN_RESPONDER', $mensaje, null, (string) $r['id_solicitud'], $correosPendientes);
            }
        }

        // 4) Consultas públicas sin responder.
        $consultas = $pdo->query(
            "SELECT id_consulta, nombre FROM CONSULTA_PUBLICA WHERE estado = 'PENDIENTE'"
        )->fetchAll();
        foreach ($consultas as $c) {
            $mensaje = "La consulta #{$c['id_consulta']} de {$c['nombre']} todavía no tiene respuesta.";
            foreach ($administradores as $admin) {
                crearNotificacionSiNoExiste($pdo, $admin['ci'], 'CONSULTA_SIN_RESPONDER', $mensaje, null, (string) $c['id_consulta'], $correosPendientes);
            }
        }

        // 5) Vencimientos próximos (30 días) o ya vencidos: carnet de salud
        //    (cualquier usuario) y licencia de conducir (choferes). Urgente:
        //    además email.
        $limite = (new DateTime('+30 days'))->format('Y-m-d');

        $carnets = $pdo->prepare(
            "SELECT ci, nombre_completo, carnet_salud_vencimiento FROM USUARIO
             WHERE estado = 'ACTIVO' AND carnet_salud_vencimiento IS NOT NULL
               AND carnet_salud_vencimiento <= :limite"
        );
        $carnets->execute(['limite' => $limite]);
        foreach ($carnets->fetchAll() as $u) {
            $mensaje = "El carnet de salud de {$u['nombre_completo']} vence el {$u['carnet_salud_vencimiento']}.";
            foreach ($administradores as $admin) {
                crearNotificacionSiNoExiste($pdo, $admin['ci'], 'CARNET_POR_VENCER', $mensaje, $admin['email'], $u['ci'], $correosPendientes);
            }
        }

        $licencias = $pdo->prepare(
            "SELECT u.ci, u.nombre_completo, o.licencia_vencimiento FROM OPERARIO o
             JOIN USUARIO u ON u.ci = o.ci_usuario
             WHERE u.estado = 'ACTIVO' AND o.cargo = 'CHOFER' AND o.licencia_vencimiento IS NOT NULL
               AND o.licencia_vencimiento <= :limite"
        );
        $licencias->execute(['limite' => $limite]);
        foreach ($licencias->fetchAll() as $o) {
            $mensaje = "La licencia de conducir de {$o['nombre_completo']} vence el {$o['licencia_vencimiento']}.";
            foreach ($administradores as $admin) {
                crearNotificacionSiNoExiste($pdo, $admin['ci'], 'LICENCIA_POR_VENCER', $mensaje, $admin['email'], $o['ci'], $correosPendientes);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    // Best-effort, fuera de la transacción: si no hay SMTP configurado o
    // falla el envío, no corta nada — las notificaciones ya quedaron
    // creadas en el paso anterior.
    foreach ($correosPendientes as $correo) {
        try {
            enviarCorreo($correo['destinatario'], 'SiGeRU - Alerta del sistema', $correo['mensaje']);
        } catch (Throwable $e) {
            // Ignorado deliberadamente (best-effort).
        }
    }
}
