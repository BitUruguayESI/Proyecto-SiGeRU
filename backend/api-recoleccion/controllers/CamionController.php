<?php

require_once __DIR__ . '/../models/Camion.php';

class CamionController {

    public function listar() {
        return ['exito' => true, 'camiones' => Camion::listar()];
    }

    public function obtener($id) {
        $c = Camion::obtenerPorId($id);
        if (!$c) return ['exito' => false, 'mensaje' => 'Camion no encontrado'];
        return ['exito' => true, 'camion' => $c];
    }

    public function crear($datos) {
        if (empty($datos['patente']) || empty($datos['capacidad_kg'])) {
            return ['exito' => false, 'mensaje' => 'Faltan datos obligatorios'];
        }

        if (Camion::existePatente($datos['patente'])) {
            return ['exito' => false, 'mensaje' => 'Ya existe un camion con esa patente'];
        }

        $nuevo = Camion::crear($datos);
        return ['exito' => true, 'mensaje' => 'Camion registrado', 'camion' => $nuevo];
    }

    public function editar($id, $datos) {
        $c = Camion::editar($id, $datos);
        if (!$c) return ['exito' => false, 'mensaje' => 'Camion no encontrado'];
        return ['exito' => true, 'mensaje' => 'Camion actualizado', 'camion' => $c];
    }

    public function eliminar($id) {
        $ok = Camion::eliminar($id);
        return ['exito' => $ok, 'mensaje' => $ok ? 'Camion eliminado' : 'Camion no encontrado'];
    }
}