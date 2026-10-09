<?php
// ===== USUARIOS.PHP - API de Usuarios =====

require_once '../config/config.php';
require_once '../models/data.php';

// Obtener método HTTP
$metodo = $_SERVER['REQUEST_METHOD'];

// Procesar solicitudes GET
if ($metodo === 'GET') {
    $accion = isset($_GET['accion']) ? sanitizar($_GET['accion']) : '';
    
    switch ($accion) {
        case 'listar':
            requerir_roles(array('admin', 'gerente'));
            $usuarios = DataManager::obtener_usuarios();
            // No enviar contraseñas en la respuesta
            foreach ($usuarios as &$usuario) {
                unset($usuario['password']);
            }
            responder(true, 'Usuarios obtenidos exitosamente', $usuarios);
            break;
            
        case 'obtener':
            $usuario_actual = requerir_login();
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

            if ($usuario_actual['rol'] !== 'admin' && $usuario_actual['rol'] !== 'gerente' && intval($usuario_actual['id']) !== $id) {
                responder(false, 'No tienes permisos para ver este usuario', null, 403);
            }

            $usuario = DataManager::obtener_usuario_por_id($id);
            if ($usuario) {
                unset($usuario['password']);
                responder(true, 'Usuario obtenido', $usuario);
            } else {
                responder(false, 'Usuario no encontrado', null, 404);
            }
            break;
            
        default:
            responder(false, 'Acción no válida', null, 400);
    }
}

// Procesar solicitudes POST
else if ($metodo === 'POST') {
    $datos = obtener_datos_post();
    $accion = isset($datos['accion']) ? sanitizar($datos['accion']) : '';
    
    switch ($accion) {
        case 'registrar':
            registrar_usuario($datos);
            break;
            
        case 'login':
            login_usuario($datos);
            break;

        case 'logout':
            logout_usuario();
            break;

        case 'cambiar_password':
            cambiar_password($datos);
            break;
            
        case 'agregar':
            requerir_roles(array('admin'));
            agregar_usuario($datos);
            break;
            
        default:
            responder(false, 'Acción no válida', null, 400);
    }
}

// Procesar solicitudes PUT
else if ($metodo === 'PUT') {
    requerir_roles(array('admin'));
    $datos = obtener_datos_post();
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        responder(false, 'ID no válido', null, 400);
    }
    
    // Validar email único
    $email = isset($datos['email']) ? sanitizar($datos['email']) : '';
    if ($email) {
        $usuario_existente = DataManager::obtener_usuario_por_email($email);
        if ($usuario_existente && $usuario_existente['id'] != $id) {
            responder(false, 'El email ya está registrado', null, 400);
        }
    }
    
    $usuario = DataManager::actualizar_usuario($id, $datos);
    if ($usuario) {
        unset($usuario['password']);
        responder(true, 'Usuario actualizado exitosamente', $usuario);
    } else {
        responder(false, 'Usuario no encontrado', null, 404);
    }
}

// Procesar solicitudes DELETE
else if ($metodo === 'DELETE') {
    $usuario_actual = requerir_roles(array('admin'));
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        responder(false, 'ID no válido', null, 400);
    }

    if (intval($usuario_actual['id']) === $id) {
        responder(false, 'No puedes eliminar tu propio usuario', null, 400);
    }

    $usuario_a_eliminar = DataManager::obtener_usuario_por_id($id);
    if (!$usuario_a_eliminar) {
        responder(false, 'Usuario no encontrado', null, 404);
    }

    if ($usuario_a_eliminar['rol'] === 'admin' && DataManager::contar_admins() <= 1) {
        responder(false, 'No se puede eliminar el último administrador del sistema', null, 400);
    }
    
    if (DataManager::eliminar_usuario($id)) {
        responder(true, 'Usuario eliminado exitosamente');
    }

    responder(false, 'Usuario no encontrado', null, 404);
}

// ===== FUNCIONES AUXILIARES =====

function registrar_usuario($datos) {
    // Validar datos requeridos
    if (!isset($datos['datos']['nombre']) || !isset($datos['datos']['email']) || !isset($datos['datos']['password'])) {
        responder(false, 'Faltan datos requeridos', null, 400);
    }
    
    $nombre = sanitizar($datos['datos']['nombre']);
    $email = sanitizar($datos['datos']['email']);
    $password = $datos['datos']['password']; // No sanitizar password
    $empresa = isset($datos['datos']['empresa']) ? sanitizar($datos['datos']['empresa']) : '';
    $telefono = isset($datos['datos']['telefono']) ? sanitizar($datos['datos']['telefono']) : '';
    $rol = 'usuario';
    
    // Validar email
    if (!validar_email($email)) {
        responder(false, 'Email no válido', null, 400);
    }
    
    // Validar contraseña
    if (strlen($password) < 6) {
        responder(false, 'La contraseña debe tener al menos 6 caracteres', null, 400);
    }
    
    // Verificar si el email ya existe
    $usuario_existente = DataManager::obtener_usuario_por_email($email);
    if ($usuario_existente) {
        responder(false, 'Este email ya está registrado', null, 400);
    }
    
    // Crear nuevo usuario
    $nuevo_usuario = array(
        'nombre' => $nombre,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'empresa' => $empresa,
        'telefono' => $telefono,
        'rol' => $rol,
        'fechaRegistro' => date('Y-m-d H:i:s')
    );
    
    $usuario = DataManager::agregar_usuario($nuevo_usuario);
    unset($usuario['password']);
    
    responder(true, 'Usuario registrado exitosamente', $usuario, 201);
}

function login_usuario($datos) {
    // Validar datos requeridos
    if (!isset($datos['email']) || !isset($datos['password'])) {
        responder(false, 'Email y contraseña son requeridos', null, 400);
    }
    
    $email = sanitizar($datos['email']);
    $password = $datos['password'];
    
    // Buscar usuario
    $usuario = DataManager::obtener_usuario_por_email($email);
    
    if (!$usuario) {
        responder(false, 'Email o contraseña incorrectos', null, 401);
    }
    
    // Acepta contraseñas nuevas hasheadas y datos de prueba antiguos en texto plano.
    $password_valida = password_verify($password, $usuario['password']) || $usuario['password'] === $password;
    if (!$password_valida) {
        responder(false, 'Email o contraseña incorrectos', null, 401);
    }
    
    // Retornar usuario sin contraseña
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $usuario['id'];

    unset($usuario['password']);
    responder(true, 'Login exitoso', $usuario);
}

function logout_usuario() {
    session_unset();
    session_destroy();
    responder(true, 'Sesión cerrada exitosamente');
}

function cambiar_password($datos) {
    $usuario_actual = requerir_login();

    if (!isset($datos['password_actual']) || !isset($datos['password_nueva']) || !isset($datos['password_confirmacion'])) {
        responder(false, 'Debes completar todos los campos de contraseña', null, 400);
    }

    $password_actual = $datos['password_actual'];
    $password_nueva = $datos['password_nueva'];
    $password_confirmacion = $datos['password_confirmacion'];

    if ($password_nueva !== $password_confirmacion) {
        responder(false, 'La nueva contraseña y su confirmación no coinciden', null, 400);
    }

    if (strlen($password_nueva) < 6) {
        responder(false, 'La nueva contraseña debe tener al menos 6 caracteres', null, 400);
    }

    $usuario = DataManager::obtener_usuario_por_id($usuario_actual['id']);
    if (!$usuario) {
        responder(false, 'Usuario no encontrado', null, 404);
    }

    $password_actual_valida = password_verify($password_actual, $usuario['password']) || $usuario['password'] === $password_actual;
    if (!$password_actual_valida) {
        responder(false, 'La contraseña actual es incorrecta', null, 401);
    }

    if ($password_actual === $password_nueva) {
        responder(false, 'La nueva contraseña debe ser diferente a la actual', null, 400);
    }

    $usuario_actualizado = DataManager::actualizar_usuario($usuario['id'], array(
        'password' => password_hash($password_nueva, PASSWORD_DEFAULT)
    ));

    unset($usuario_actualizado['password']);
    responder(true, 'Contraseña actualizada exitosamente', $usuario_actualizado);
}

function agregar_usuario($datos) {
    if (!isset($datos['datos'])) {
        responder(false, 'Datos no válidos', null, 400);
    }
    
    $datos_usuario = $datos['datos'];
    
    // Validar datos requeridos
    if (!isset($datos_usuario['nombre']) || !isset($datos_usuario['email'])) {
        responder(false, 'Nombre y email son requeridos', null, 400);
    }
    
    $email = sanitizar($datos_usuario['email']);
    
    // Verificar si el email ya existe
    $usuario_existente = DataManager::obtener_usuario_por_email($email);
    if ($usuario_existente) {
        responder(false, 'Este email ya está registrado', null, 400);
    }
    
    $nuevo_usuario = array(
        'nombre' => sanitizar($datos_usuario['nombre']),
        'email' => $email,
        'password' => password_hash(isset($datos_usuario['password']) ? $datos_usuario['password'] : 'default123', PASSWORD_DEFAULT),
        'rol' => isset($datos_usuario['rol']) ? sanitizar($datos_usuario['rol']) : 'usuario',
        'empresa' => isset($datos_usuario['empresa']) ? sanitizar($datos_usuario['empresa']) : '',
        'telefono' => isset($datos_usuario['telefono']) ? sanitizar($datos_usuario['telefono']) : ''
    );
    
    $usuario = DataManager::agregar_usuario($nuevo_usuario);
    unset($usuario['password']);
    
    responder(true, 'Usuario agregado exitosamente', $usuario, 201);
}
