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
            $id = $datos['id'] ?? 0;
            $nuevoEstado = $datos['estado'] ?? '';
            $respuesta = $controller->actualizarEstado($id, $nuevoEstado);
        }
        break;

    case 'eliminar':
        if ($metodo === 'DELETE' || $metodo === 'POST') {
            $respuesta = $controller->eliminar($_GET['id'] ?? 0);
        }
        break;
}

echo json_encode($respuesta);
