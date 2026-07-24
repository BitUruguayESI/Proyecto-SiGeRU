<?php

session_start();

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// El navegador manda esto antes del fetch real, solo respondemos OK
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/controllers/IncidenciaController.php';

$controller = new IncidenciaController();
$accion = $_GET['accion'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'];

// Leemos el body en JSON (para POST y PUT)
$datos = json_decode(file_get_contents('php://input'), true);
if (!is_array($datos)) {
    $datos = [];
}

$respuesta = ['exito' => false, 'mensaje' => 'Accion no valida'];

switch ($accion) {

    case 'crear':
        if ($metodo === 'POST') {
            $respuesta = $controller->crear($datos);
        }
        break;

    case 'listar':
        if ($metodo === 'GET') {
            $respuesta = $controller->listar();
        }
        break;

    case 'listarPorUsuario':
        if ($metodo === 'GET') {
            $respuesta = $controller->listarPorUsuario($_GET['usuario_id'] ?? 0);
        }
        break;

    case 'actualizarEstado':
        if ($metodo === 'POST') {
            // Buscamos la incidencia en la sesión y le cambiamos el estado
            $id = $datos['id'] ?? 0;
            $nuevoEstado = $datos['estado'] ?? '';
            
            $actualizado = false;
            if (isset($_SESSION['incidencias'])) {
                foreach ($_SESSION['incidencias'] as &$i) {
                    if ($i['id'] == $id) {
                        $i['estado'] = $nuevoEstado;
                        $actualizado = true;
                        break;
                    }
                }
            }
            
            if ($actualizado) {
                $respuesta = ['exito' => true, 'mensaje' => 'Estado de la incidencia actualizado'];
            } else {
                $respuesta = ['exito' => false, 'mensaje' => 'No se encontró la incidencia'];
            }
        }
        break;
}

echo json_encode($respuesta);