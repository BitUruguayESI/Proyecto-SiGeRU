<?php

require_once __DIR__ . '/../models/Usuario.php';

class UsuarioController {

    public function login($datos) {
        $usuario = $datos['usuario'] ?? '';
        $password = $datos['password'] ?? '';

        $encontrado = Usuario::obtenerPorCredenciales($usuario, $password);

        if ($encontrado) {
            $_SESSION['usuarioActivo'] = $encontrado;
            return [
                'exito' => true,
                'mensaje' => 'Inicio de sesion correcto',
                'usuario' => [
                    'id' => $encontrado['id'],
                    'nombre' => $encontrado['nombre'],
                    'rol' => $encontrado['rol']
                ]
            ];
        }

        return [
            'exito' => false,
            'mensaje' => 'Usuario o contrasena incorrectos'
        ];
    }

    public function registrar($datos) {
        if (empty($datos['nombre']) || empty($datos['usuario']) || empty($datos['password']) || empty($datos['rol'])) {
            return ['exito' => false, 'mensaje' => 'Faltan datos obligatorios'];
        }

        if (Usuario::existeUsuario($datos['usuario'])) {
            return ['exito' => false, 'mensaje' => 'El nombre de usuario ya existe'];
        }

        $nuevo = Usuario::crear($datos);
        return ['exito' => true, 'mensaje' => 'Usuario registrado', 'usuario' => $nuevo];
    }

    public function listar() {
        $usuarios = Usuario::listar();

        // No devolvemos la contrasena al frontend
        $usuarios = array_map(function ($u) {
            unset($u['password']);
            return $u;
        }, $usuarios);

        return ['exito' => true, 'usuarios' => $usuarios];
    }

    public function obtener($id) {
        $u = Usuario::obtenerPorId($id);
        if (!$u) return ['exito' => false, 'mensaje' => 'Usuario no encontrado'];
        unset($u['password']);
        return ['exito' => true, 'usuario' => $u];
    }

    public function editar($id, $datos) {
        $u = Usuario::editar($id, $datos);
        if (!$u) return ['exito' => false, 'mensaje' => 'Usuario no encontrado'];
        unset($u['password']);
        return ['exito' => true, 'mensaje' => 'Usuario actualizado', 'usuario' => $u];
    }

    public function eliminar($id) {
        $ok = Usuario::eliminar($id);
        return ['exito' => $ok, 'mensaje' => $ok ? 'Usuario eliminado' : 'Usuario no encontrado'];
    }
}