<?php
// ===== CONFIG.PHP - Configuración de la API =====

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Headers para CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Prevenir output buffering issues
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bituruguay');
define('DB_CHARSET', 'utf8mb4');

// Versión de la API
define('API_VERSION', '1.0.0');

// Información de la API
$api_info = array(
    'version' => API_VERSION,
    'nombre' => 'BITURUGUAY API',
    'descripcion' => 'Sistema de Gestión de Rutas - API Backend',
    'estado' => 'En desarrollo',
    'fecha' => date('Y-m-d H:i:s')
);

// Función para responder con JSON
function responder($exito, $mensaje, $datos = null, $codigo_http = 200) {
    http_response_code($codigo_http);
    $respuesta = array(
        'exito' => $exito,
        'mensaje' => $mensaje,
        'datos' => $datos,
        'timestamp' => date('Y-m-d H:i:s')
    );
    echo json_encode($respuesta, JSON_PRETTY_PRINT);
    exit();
}

// Función para conectar con MySQL/MariaDB usando XAMPP
function obtener_conexion() {
    static $conexion = null;

    if ($conexion === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        try {
            $conexion = new PDO($dsn, DB_USER, DB_PASS, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ));
        } catch (PDOException $e) {
            responder(false, 'No se pudo conectar a la base de datos. Verifica que MySQL esté iniciado en XAMPP y que exista la base bituruguay.', array(
                'error' => $e->getMessage()
            ), 500);
        }
    }

    return $conexion;
}

// Función para obtener datos POST
function obtener_datos_post() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

// Función para validar email
function validar_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Función para sanitizar entrada
function sanitizar($dato) {
    return htmlspecialchars(trim($dato), ENT_QUOTES, 'UTF-8');
}

function usuario_actual() {
    if (!isset($_SESSION['usuario_id'])) {
        return null;
    }

    $usuario = DataManager::obtener_usuario_por_id($_SESSION['usuario_id']);
    if (!$usuario) {
        session_unset();
        session_destroy();
        return null;
    }

    unset($usuario['password']);
    return $usuario;
}

function requerir_login() {
    $usuario = usuario_actual();
    if (!$usuario) {
        responder(false, 'Debes iniciar sesión para realizar esta acción', null, 401);
    }

    return $usuario;
}

function requerir_roles($roles) {
    $usuario = requerir_login();
    if (!in_array($usuario['rol'], $roles, true)) {
        responder(false, 'No tienes permisos para realizar esta acción', null, 403);
    }

    return $usuario;
}
