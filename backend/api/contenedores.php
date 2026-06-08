<?php
// ===== CONTENEDORES.PHP - API de Contenedores =====

require_once '../config/config.php';
require_once '../models/data.php';

// Obtener método HTTP
$metodo = $_SERVER['REQUEST_METHOD'];

// Procesar solicitudes GET
if ($metodo === 'GET') {
    requerir_login();
    $accion = isset($_GET['accion']) ? sanitizar($_GET['accion']) : '';
    
    switch ($accion) {
        case 'listar':
            $contenedores = DataManager::obtener_contenedores();
            responder(true, 'Contenedores obtenidos exitosamente', $contenedores);
            break;
            
        case 'obtener':
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $contenedor = DataManager::obtener_contenedor_por_id($id);
            if ($contenedor) {
                responder(true, 'Contenedor obtenido', $contenedor);
            } else {
                responder(false, 'Contenedor no encontrado', null, 404);
            }
            break;
            
        default:
            responder(false, 'Acción no válida', null, 400);
    }
}

// Procesar solicitudes POST
else if ($metodo === 'POST') {
    requerir_roles(array('admin', 'gerente'));
    $datos = obtener_datos_post();
    $accion = isset($datos['accion']) ? sanitizar($datos['accion']) : '';
    
    switch ($accion) {
        case 'agregar':
            agregar_contenedor($datos);
            break;
            
        default:
            responder(false, 'Acción no válida', null, 400);
    }
}

// Procesar solicitudes DELETE
else if ($metodo === 'DELETE') {
    requerir_roles(array('admin', 'gerente'));
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id <= 0) {
        responder(false, 'ID no válido', null, 400);
    }

    if (DataManager::eliminar_contenedor($id)) {
        responder(true, 'Contenedor eliminado exitosamente');
    }

    responder(false, 'Contenedor no encontrado', null, 404);
}

// ===== FUNCIONES AUXILIARES =====

function agregar_contenedor($datos) {
    if (!isset($datos['datos'])) {
        responder(false, 'Datos no válidos', null, 400);
    }
    
    $datos_contenedor = $datos['datos'];
    
    // Validar datos requeridos
    if (!isset($datos_contenedor['codigo']) || !isset($datos_contenedor['tipo']) || !isset($datos_contenedor['capacidad'])) {
        responder(false, 'Código, tipo y capacidad son requeridos', null, 400);
    }
    
    $nuevo_contenedor = array(
        'codigo' => sanitizar($datos_contenedor['codigo']),
        'tipo' => sanitizar($datos_contenedor['tipo']),
        'capacidad' => intval($datos_contenedor['capacidad'])
    );
    
    $contenedor = DataManager::agregar_contenedor($nuevo_contenedor);
    responder(true, 'Contenedor agregado exitosamente', $contenedor, 201);
}
