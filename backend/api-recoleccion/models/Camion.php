<?php

class Camion {

    // Crea datos iniciales de ejemplo si todavía no existen en la sesión
    public static function inicializar() {
        if (!isset($_SESSION['camiones'])) {
            $_SESSION['camiones'] = [
                [
                    'id' => 1,
                    'patente' => 'STA1234',
                    'capacidad_kg' => 5000,
                    'estado' => 'Disponible'
                ],
                [
                    'id' => 2,
                    'patente' => 'SBA5678',
                    'capacidad_kg' => 8000,
                    'estado' => 'En ruta'
                ]
            ];
            $_SESSION['camionSiguienteId'] = 3;
        }
    }

    public static function listar() {
        self::inicializar();
        return $_SESSION['camiones'];
    }

    public static function obtenerPorId($id) {
        self::inicializar();
        foreach ($_SESSION['camiones'] as $c) {
            if ($c['id'] == $id) return $c;
        }
        return null;
    }

    public static function existePatente($patente) {
        self::inicializar();
        foreach ($_SESSION['camiones'] as $c) {
            if (($c['patente'] ?? null) === $patente) return true;
        }
        return false;
    }

    public static function crear($datos) {
        self::inicializar();
        $nuevo = [
            'id' => $_SESSION['camionSiguienteId'],
            'patente' => $datos['patente'] ?? '',
            'capacidad_kg' => $datos['capacidad_kg'] ?? 0,
            'estado' => $datos['estado'] ?? 'Disponible'
        ];
        $_SESSION['camiones'][] = $nuevo;
        $_SESSION['camionSiguienteId']++;
        return $nuevo;
    }

    public static function editar($id, $datos) {
        self::inicializar();
        foreach ($_SESSION['camiones'] as &$c) {
            if ($c['id'] == $id) {
                $c['patente'] = $datos['patente'] ?? $c['patente'];
                $c['capacidad_kg'] = $datos['capacidad_kg'] ?? $c['capacidad_kg'];
                $c['estado'] = $datos['estado'] ?? $c['estado'];
                return $c;
            }
        }
        return null;
    }

    public static function eliminar($id) {
        self::inicializar();
        foreach ($_SESSION['camiones'] as $key => $c) {
            if ($c['id'] == $id) {
                array_splice($_SESSION['camiones'], $key, 1);
                return true;
            }
        }
        return false;
    }
}