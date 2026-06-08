<?php
// ===== CAMIONES.PHP - API de Camiones =====

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
            $camiones = DataManager::obtener_camiones();
            responder(true, 'Camiones obtenidos exitosamente', $camiones);
            break;
            
        case 'obtener':
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $camion = DataManager::obtener_camion_por_id($id);
            if ($camion) {
                responder(true, 'Camión obtenido', $camion);
            } else {
                responder(false, 'Camión no encontrado', null, 404);
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
            agregar_camion($datos);
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

    if (DataManager::eliminar_camion($id)) {
        responder(true, 'Camión eliminado exitosamente');
    }

    responder(false, 'Camión no encontrado', null, 404);
}

// ===== FUNCIONES AUXILIARES =====

function agregar_camion($datos) {
    if (!isset($datos['datos'])) {
        responder(false, 'Datos no válidos', null, 400);
    }
    
    $datos_camion = $datos['datos'];
    
    // Validar datos requeridos
    if (!isset($datos_camion['patente']) || !isset($datos_camion['modelo']) || !isset($datos_camion['capacidad'])) {
        responder(false, 'Patente, modelo y capacidad son requeridos', null, 400);
    }
    
    $nuevo_camion = array(
        'patente' => sanitizar($datos_camion['patente']),
        'modelo' => sanitizar($datos_camion['modelo']),
        'capacidad' => intval($datos_camion['capacidad'])
    );
    
    $camion = DataManager::agregar_camion($nuevo_camion);
    responder(true, 'Camión agregado exitosamente', $camion, 201);
}
