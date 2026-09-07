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
