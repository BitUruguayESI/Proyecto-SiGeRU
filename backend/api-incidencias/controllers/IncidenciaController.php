<?php

require_once __DIR__ . '/../models/Incidencia.php';

class IncidenciaController {

    public function crear($datos) {
        // Validación de datos obligatorios provenientes del formulario del vecino
        if (empty($datos['tipo_incidencia']) || empty($datos['direccion']) || empty($datos['usuario_id'])) {
            return ['exito' => false, 'mensaje' => 'Faltan datos obligatorios para el reporte'];
        }

        $nueva = Incidencia::crear($datos);
        return [
            'exito' => true, 
            'mensaje' => 'Reporte municipal enviado con éxito. ¡Gracias por colaborar!', 
            'incidencia' => $nueva
        ];
    }

    public function listar() {
        // Útil para que el administrador vea absolutamente todas las incidencias
        $incidencias = Incidencia::listar();
        return ['exito' => true, 'incidencias' => $incidencias];
    }

    public function listarPorUsuario($usuarioId) {
        // Útil para la sección "Mis Reportes" en el panel del vecino
        $incidencias = Incidencia::listarPorUsuario($usuarioId);
        return ['exito' => true, 'incidencias' => $incidencias];
    }

    public function actualizarEstado($id, $estado) {
        $actualizada = Incidencia::actualizarEstado($id, $estado);
        return [
            'exito' => $actualizada,
            'mensaje' => $actualizada ? 'Estado de la incidencia actualizado' : 'No se encontró la incidencia o el estado no es válido'
        ];
    }

    public function eliminar($id) {
        $eliminada = Incidencia::eliminar($id);
        return [
            'exito' => $eliminada,
            'mensaje' => $eliminada ? 'Incidencia eliminada correctamente' : 'No se encontró la incidencia'
        ];
    }
}
