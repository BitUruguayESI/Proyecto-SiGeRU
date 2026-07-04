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

require_once __DIR__ . '/controllers/UsuarioController.php';

$controller = new UsuarioController();
$accion = $_GET['accion'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'];

// Leemos el body en JSON (para POST y PUT)
$datos = json_decode(file_get_contents('php://input'), true);
if (!is_array($datos)) {
    $datos = [];
}

$respuesta = ['exito' => false, 'mensaje' => 'Accion no valida'];

switch ($accion) {

    case 'login':
        if ($metodo === 'POST') {
            $respuesta = $controller->login($datos);
        }
        break;

    case 'registrar':
        if ($metodo === 'POST') {
            $respuesta = $controller->registrar($datos);
        }
        break;

    case 'listar':
        if ($metodo === 'GET') {
            $respuesta = $controller->listar();
        }
        break;

    case 'obtener':
        if ($metodo === 'GET') {
            $respuesta = $controller->obtener($_GET['id'] ?? 0);
        }
        break;

    case 'editar':
        if ($metodo === 'PUT' || $metodo === 'POST') {
            $respuesta = $controller->editar($_GET['id'] ?? 0, $datos);
        }
        break;

    case 'eliminar':
        if ($metodo === 'DELETE' || $metodo === 'POST') {
            $respuesta = $controller->eliminar($_GET['id'] ?? 0);
        }
        break;
}

echo json_encode($respuesta);