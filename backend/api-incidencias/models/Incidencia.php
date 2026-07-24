<?php

class Incidencia {

    // Inicializa la colección de incidencias en la sesión si no existe
    public static function inicializar() {
        if (!isset($_SESSION['incidencias'])) {
            $_SESSION['incidencias'] = [
                [
                    'id' => 1,
                    'usuario_id' => 3, // ID ficticio de un vecino
                    'tipo_incidencia' => 'Contenedor Desbordado',
                    'direccion' => 'Av. 18 de Julio y Ejido',
                    'descripcion' => 'Contenedor completamente lleno, basura en la acera.',
                    'estado' => 'Abierta', // Abierta, En Proceso, Solucionada
                    'fecha' => '2026-07-12'
                ]
            ];
            $_SESSION['siguienteIncidenciaId'] = 2;
        }
    }

    public static function listar() {
        self::inicializar();
        return $_SESSION['incidencias'];
    }

    public static function listarPorUsuario($usuarioId) {
        self::inicializar();
        $filtradas = [];
        foreach ($_SESSION['incidencias'] as $i) {
            if ($i['usuario_id'] == $usuarioId) {
                $filtradas[] = $i;
            }
        }
        return $filtradas;
    }

    public static function crear($datos) {
        self::inicializar();
        
        $nueva = [
            'id' => $_SESSION['siguienteIncidenciaId'],
            'usuario_id' => $datos['usuario_id'],
            'tipo_incidencia' => $datos['tipo_incidencia'],
            'direccion' => $datos['direccion'],
            'descripcion' => $datos['descripcion'] ?? '',
            'estado' => 'Abierta', // Toda incidencia nace en estado "Abierta" por defecto
            'fecha' => date('Y-m-d')
        ];

        $_SESSION['incidencias'][] = $nueva;
        $_SESSION['siguienteIncidenciaId']++;
        return $nueva;
    }
}