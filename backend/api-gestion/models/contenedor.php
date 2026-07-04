<?php

class Contenedor {

    // Crea datos iniciales de ejemplo si todavía no existen en la sesión
    public static function inicializar() {
        if (!isset($_SESSION['contenedores'])) {
            $_SESSION['contenedores'] = [
                [
                    'id' => 1,
                    'ubicacion' => 'Av. 18 de Julio y Ejido',
                    'tipo_residuo' => 'General',
                    'estado' => 'Funcional'
                ],
                [
                    'id' => 2,
                    'ubicacion' => 'Bvar. Artigas y Rivera',
                    'tipo_residuo' => 'Reciclable',
                    'estado' => 'Desbordado'
                ]
            ];
            $_SESSION['contenedorSiguienteId'] = 3;
        }
    }

    public static function listar() {
        self::inicializar();
        return $_SESSION['contenedores'];
    }

    public static function obtenerPorId($id) {
        self::inicializar();
        foreach ($_SESSION['contenedores'] as $c) {
            if ($c['id'] == $id) return $c;
        }
        return null;
    }

    public static function crear($datos) {
        self::inicializar();
        $nuevo = [
            'id' => $_SESSION['contenedorSiguienteId'],
            'ubicacion' => $datos['ubicacion'] ?? '',
            'tipo_residuo' => $datos['tipo_residuo'] ?? '',
            'estado' => $datos['estado'] ?? 'Funcional'
        ];
        $_SESSION['contenedores'][] = $nuevo;
        $_SESSION['contenedorSiguienteId']++;
        return $nuevo;
    }

    public static function editar($id, $datos) {
        self::inicializar();
        foreach ($_SESSION['contenedores'] as &$c) {
            if ($c['id'] == $id) {
                $c['ubicacion'] = $datos['ubicacion'] ?? $c['ubicacion'];
                $c['tipo_residuo'] = $datos['tipo_residuo'] ?? $c['tipo_residuo'];
                $c['estado'] = $datos['estado'] ?? $c['estado'];
                return $c;
            }
        }
        return null;
    }

    public static function eliminar($id) {
        self::inicializar();
        foreach ($_SESSION['contenedores'] as $key => $c) {
            if ($c['id'] == $id) {
                array_splice($_SESSION['contenedores'], $key, 1);
                return true;
            }
        }
        return false;
    }
}