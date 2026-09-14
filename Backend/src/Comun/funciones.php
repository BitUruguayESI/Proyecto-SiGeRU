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

/**
 * Valida que una fecha venga en formato "YYYY-MM-DD" y sea una fecha de
 * calendario real (rechaza cosas como "2026-02-30"). Se usa para
 * vencimientos (carnet de salud, licencia de conducir).
 */
function esFechaValida(string $fecha): bool
{
    $partes = DateTime::createFromFormat('Y-m-d', $fecha);
    return $partes !== false && $partes->format('Y-m-d') === $fecha;
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

/**
 * Igual que requerirSesionActiva(), pero además exige que el usuario
 * pueda marcar una incidencia como RESUELTA: solo ADMINISTRADOR o el
 * OPERARIO con cargo CHOFER (el Recolector puede ver el estado, pero no
 * resolverla). Se consulta el cargo directo en la base (no desde
 * $_SESSION) para reflejar de inmediato un cambio de rol hecho por un
 * Administrador mientras la sesión del operario sigue activa.
 */
function requerirPuedeResolverIncidencia(PDO $pdo): array
{
    $sesion = requerirSesionActiva();
    if ($sesion['tipo_usuario'] === 'ADMINISTRADOR') {
        return $sesion;
    }

    $consulta = $pdo->prepare('SELECT cargo FROM OPERARIO WHERE ci_usuario = :ci');
    $consulta->execute(['ci' => $sesion['ci_usuario']]);
    $fila = $consulta->fetch();

    if (!$fila || $fila['cargo'] !== 'CHOFER') {
        responderJson([
            'exito' => false,
            'mensaje' => 'Solo un Administrador o el Chofer de la cuadrilla pueden marcar una incidencia como resuelta.',
        ], 403);
    }
    return $sesion;
}

/**
 * Geocodifica una dirección de texto ("Bulevar Artigas 1234, Montevideo")
 * a coordenadas "lat,lon" usando Nominatim (OpenStreetMap), para el mapa
 * interactivo de contenedores (RF13).
 *
 * Devuelve null si no se pudo geocodificar (sin internet, dirección no
 * encontrada, timeout, límite de uso, etc.) — el llamador debe guardar
 * igual el registro con coordenadas NULL y avisar que el mapa no va a
 * poder ubicarlo hasta que se reintente con una dirección más precisa.
 *
 * Nominatim exige un User-Agent identificable y como máximo ~1 consulta
 * por segundo (política de uso); acá alcanza sobra porque se llama una
 * vez por alta/edición hecha a mano por el Administrador.
 */
function geocodificarDireccion(string $direccion): ?array
{
    $direccion = trim($direccion);
    if ($direccion === '' || !function_exists('curl_init')) {
        return null;
    }

    // Si no menciona el país, lo agregamos para que Nominatim no ubique
    // la dirección en otro lugar del mundo con el mismo nombre de calle.
    $consulta = stripos($direccion, 'uruguay') === false ? "{$direccion}, Uruguay" : $direccion;

    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'format' => 'json',
        'q'      => $consulta,
        'limit'  => 1,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_USERAGENT      => 'SiGeRU-UTU-PasajeDeGrado/1.0 (proyecto academico BitUruguay)',
        CURLOPT_HTTPHEADER     => ['Accept-Language: es'],
    ]);
    $respuesta = curl_exec($ch);
    $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($respuesta === false || $error || $codigoHttp !== 200) {
        return null;
    }

    $resultados = json_decode($respuesta, true);
    if (!is_array($resultados) || empty($resultados[0]['lat']) || empty($resultados[0]['lon'])) {
        return null;
    }

    return [
        'lat' => (float) $resultados[0]['lat'],
        'lon' => (float) $resultados[0]['lon'],
    ];
}
