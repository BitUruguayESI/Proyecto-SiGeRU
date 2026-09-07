<?php
/**
 * Comun/funciones.php
 * Funciones de apoyo compartidas por todos los módulos.
 */

function limpiarTexto(?string $valor): string
{
    return trim(htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8'));
}

function esEmailValido(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valida formato básico de Cédula de Identidad uruguaya: solo dígitos,
 * entre 7 y 8 caracteres (sin puntos ni guion). No calcula el dígito
 * verificador, solo valida formato de entrada.
 */
function esCedulaValida(string $ci): bool
{
    return (bool) preg_match('/^\d{7,8}$/', $ci);
}

function responderJson(array $datos, int $codigoHttp = 200): void
{
    http_response_code($codigoHttp);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function obtenerJsonEntrada(): array
{
    $crudo = file_get_contents('php://input');
    $datos = json_decode($crudo, true);
    return is_array($datos) ? $datos : [];
}

/**
 * Asegura que haya una sesión de USUARIO (Operario o Administrador)
 * iniciada (via api-usuarios/login.php). Si no la hay, corta la
 * ejecución con 401. Devuelve los datos de sesión para quien los use.
 */
function requerirSesionActiva(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['ci_usuario'])) {
        responderJson(['exito' => false, 'mensaje' => 'Tenés que iniciar sesión.'], 401);
    }
    return $_SESSION;
}

/**
 * Igual que requerirSesionActiva(), pero además exige que el usuario
 * sea ADMINISTRADOR. Pensado para los endpoints de api-gestion, donde
 * solo el administrador da de alta/baja contenedores, rutas, cuadrillas,
 * camiones e instalaciones.
 */
function requerirRolAdministrador(): array
{
    $sesion = requerirSesionActiva();
    if ($sesion['tipo_usuario'] !== 'ADMINISTRADOR') {
        responderJson(['exito' => false, 'mensaje' => 'Esta acción requiere una cuenta de Administrador.'], 403);
    }
    return $sesion;
}
