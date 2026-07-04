<?php

class Usuario {

    // Crea datos iniciales de ejemplo si todavía no existen en la sesión
    public static function inicializar() {
        if (!isset($_SESSION['usuarios'])) {
            $_SESSION['usuarios'] = [
                [
                    'id' => 1,
                    'nombre' => 'Admin Municipal',
                    'usuario' => 'admin',
                    'password' => 'admin123',
                    'rol' => 'Administrador'
                ],
                [
                    'id' => 2,
                    'nombre' => 'Juan Perez',
                    'usuario' => 'jperez',
                    'password' => '1234',
                    'rol' => 'Cuadrilla'
                ]
            ];
            $_SESSION['siguienteId'] = 3;
        }
    }

    public static function listar() {
        self::inicializar();
        return $_SESSION['usuarios'];
    }

    public static function obtenerPorId($id) {
        self::inicializar();
        foreach ($_SESSION['usuarios'] as $u) {
            if ($u['id'] == $id) return $u;
        }
        return null;
    }

    public static function obtenerPorCredenciales($usuario, $password) {
        self::inicializar();
        foreach ($_SESSION['usuarios'] as $u) {
            if (($u['usuario'] ?? null) === $usuario && ($u['password'] ?? null) === $password) {
                return $u;
            }
        }
        return null;
    }

    public static function existeUsuario($usuario) {
        self::inicializar();
        foreach ($_SESSION['usuarios'] as $u) {
            if (($u['usuario'] ?? null) === $usuario) return true;
        }
        return false;
    }

    public static function crear($datos) {
        self::inicializar();
        $nuevo = [
            'id' => $_SESSION['siguienteId'],
            'nombre' => $datos['nombre'],
            'usuario' => $datos['usuario'],
            'password' => $datos['password'],
            'rol' => $datos['rol']
        ];
        $_SESSION['usuarios'][] = $nuevo;
        $_SESSION['siguienteId']++;
        return $nuevo;
    }

    public static function editar($id, $datos) {
        self::inicializar();
        foreach ($_SESSION['usuarios'] as &$u) {
            if ($u['id'] == $id) {
                $u['nombre'] = $datos['nombre'] ?? $u['nombre'];
                $u['usuario'] = $datos['usuario'] ?? $u['usuario'];
                $u['rol'] = $datos['rol'] ?? $u['rol'];
                if (!empty($datos['password'])) {
                    $u['password'] = $datos['password'];
                }
                return $u;
            }
        }
        return null;
    }

    public static function eliminar($id) {
        self::inicializar();
        foreach ($_SESSION['usuarios'] as $key => $u) {
            if ($u['id'] == $id) {
                array_splice($_SESSION['usuarios'], $key, 1);
                return true;
            }
        }
        return false;
    }
}