<?php

require_once __DIR__ . '/../models/Contenedor.php';

class ContenedorController {

    public function listar() {
        return ['exito' => true, 'contenedores' => Contenedor::listar()];
    }

    public function obtener($id) {
        $c = Contenedor::obtenerPorId($id);
        if (!$c) return ['exito' => false, 'mensaje' => 'Contenedor no encontrado'];
        return ['exito' => true, 'contenedor' => $c];
    }

    public function crear($datos) {
        if (empty($datos['ubicacion']) || empty($datos['tipo_residuo'])) {
            return ['exito' => false, 'mensaje' => 'Faltan datos obligatorios'];
        }

        $nuevo = Contenedor::crear($datos);
        return ['exito' => true, 'mensaje' => 'Contenedor registrado', 'contenedor' => $nuevo];
    }

    public function editar($id, $datos) {
        $c = Contenedor::editar($id, $datos);
        if (!$c) return ['exito' => false, 'mensaje' => 'Contenedor no encontrado'];
        return ['exito' => true, 'mensaje' => 'Contenedor actualizado', 'contenedor' => $c];
    }

    public function eliminar($id) {
        $ok = Contenedor::eliminar($id);
        return ['exito' => $ok, 'mensaje' => $ok ? 'Contenedor eliminado' : 'Contenedor no encontrado'];
    }
}