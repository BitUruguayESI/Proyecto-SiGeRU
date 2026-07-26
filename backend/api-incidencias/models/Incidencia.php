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
                    'fecha' => '2026-07-12',
                    'fecha_creacion' => '2026-07-12T00:00:00-03:00',
                    'fecha_en_proceso' => null,
                    'fecha_solucionada' => null
                ]
            ];
            $_SESSION['siguienteIncidenciaId'] = 2;
        }

        // Compatibilidad con incidencias creadas antes de registrar tiempos.
        foreach ($_SESSION['incidencias'] as &$incidencia) {
            if (empty($incidencia['fecha_creacion'])) {
                $incidencia['fecha_creacion'] = ($incidencia['fecha'] ?? date('Y-m-d')) . 'T00:00:00-03:00';
            }
            $incidencia['fecha_en_proceso'] = $incidencia['fecha_en_proceso'] ?? null;
            $incidencia['fecha_solucionada'] = $incidencia['fecha_solucionada'] ?? null;
        }
        unset($incidencia);
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
            'fecha' => date('Y-m-d'),
            'fecha_creacion' => date('c'),
            'fecha_en_proceso' => null,
            'fecha_solucionada' => null
        ];

        $_SESSION['incidencias'][] = $nueva;
        $_SESSION['siguienteIncidenciaId']++;
        return $nueva;
    }

    public static function actualizarEstado($id, $nuevoEstado) {
        self::inicializar();
        $estadosPermitidos = ['Abierta', 'En Proceso', 'Solucionada'];
        if (!in_array($nuevoEstado, $estadosPermitidos, true)) return false;

        foreach ($_SESSION['incidencias'] as &$incidencia) {
            if ($incidencia['id'] == $id) {
                $incidencia['estado'] = $nuevoEstado;
                $ahora = date('c');

                if ($nuevoEstado === 'En Proceso' && empty($incidencia['fecha_en_proceso'])) {
                    $incidencia['fecha_en_proceso'] = $ahora;
                }
                if ($nuevoEstado === 'Solucionada' && empty($incidencia['fecha_solucionada'])) {
                    $incidencia['fecha_solucionada'] = $ahora;
                }

                return true;
            }
        }
        unset($incidencia);

        return false;
    }

    public static function eliminar($id) {
        self::inicializar();

        foreach ($_SESSION['incidencias'] as $indice => $incidencia) {
            if ($incidencia['id'] == $id) {
                array_splice($_SESSION['incidencias'], $indice, 1);
                return true;
            }
        }

        return false;
    }
}
