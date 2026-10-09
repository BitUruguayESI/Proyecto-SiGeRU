<?php
// ===== DATA.PHP - Gestor de datos MySQL =====

class DataManager {
    private static function db() {
        return obtener_conexion();
    }

    public static function obtener_usuarios() {
        $stmt = self::db()->query("SELECT id, nombre, email, password, rol, empresa, telefono, fecha_registro AS fechaRegistro FROM usuarios ORDER BY id");
        return $stmt->fetchAll();
    }

    public static function obtener_usuario_por_id($id) {
        $stmt = self::db()->prepare("SELECT id, nombre, email, password, rol, empresa, telefono, fecha_registro AS fechaRegistro FROM usuarios WHERE id = ?");
        $stmt->execute(array($id));
        $usuario = $stmt->fetch();
        return $usuario ? $usuario : null;
    }

    public static function obtener_usuario_por_email($email) {
        $stmt = self::db()->prepare("SELECT id, nombre, email, password, rol, empresa, telefono, fecha_registro AS fechaRegistro FROM usuarios WHERE email = ?");
        $stmt->execute(array($email));
        $usuario = $stmt->fetch();
        return $usuario ? $usuario : null;
    }

    public static function contar_admins() {
        $stmt = self::db()->query("SELECT COUNT(*) AS total FROM usuarios WHERE rol = 'admin'");
        $resultado = $stmt->fetch();
        return intval($resultado['total']);
    }

    public static function agregar_usuario($usuario) {
        $stmt = self::db()->prepare(
            "INSERT INTO usuarios (nombre, email, password, rol, empresa, telefono)
             VALUES (:nombre, :email, :password, :rol, :empresa, :telefono)"
        );

        $stmt->execute(array(
            ':nombre' => $usuario['nombre'],
            ':email' => $usuario['email'],
            ':password' => $usuario['password'],
            ':rol' => isset($usuario['rol']) ? $usuario['rol'] : 'usuario',
            ':empresa' => isset($usuario['empresa']) ? $usuario['empresa'] : '',
            ':telefono' => isset($usuario['telefono']) ? $usuario['telefono'] : ''
        ));

        return self::obtener_usuario_por_id(self::db()->lastInsertId());
    }

    public static function actualizar_usuario($id, $usuario) {
        $campos_permitidos = array('nombre', 'email', 'password', 'rol', 'empresa', 'telefono');
        $sets = array();
        $params = array(':id' => $id);

        foreach ($campos_permitidos as $campo) {
            if (isset($usuario[$campo])) {
                $sets[] = "$campo = :$campo";
                $params[":$campo"] = $usuario[$campo];
            }
        }

        if (empty($sets)) {
            return self::obtener_usuario_por_id($id);
        }

        $sql = "UPDATE usuarios SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);

        return self::obtener_usuario_por_id($id);
    }

    public static function eliminar_usuario($id) {
        $stmt = self::db()->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->rowCount() > 0;
    }

    public static function obtener_contenedores() {
        $stmt = self::db()->query("SELECT id, codigo, tipo, capacidad, estado FROM contenedores ORDER BY id");
        return $stmt->fetchAll();
    }

    public static function obtener_contenedor_por_id($id) {
        $stmt = self::db()->prepare("SELECT id, codigo, tipo, capacidad, estado FROM contenedores WHERE id = ?");
        $stmt->execute(array($id));
        $contenedor = $stmt->fetch();
        return $contenedor ? $contenedor : null;
    }

    public static function agregar_contenedor($contenedor) {
        $stmt = self::db()->prepare(
            "INSERT INTO contenedores (codigo, tipo, capacidad, estado)
             VALUES (:codigo, :tipo, :capacidad, :estado)"
        );

        $stmt->execute(array(
            ':codigo' => $contenedor['codigo'],
            ':tipo' => $contenedor['tipo'],
            ':capacidad' => $contenedor['capacidad'],
            ':estado' => isset($contenedor['estado']) ? $contenedor['estado'] : 'disponible'
        ));

        return self::obtener_contenedor_por_id(self::db()->lastInsertId());
    }

    public static function eliminar_contenedor($id) {
        $stmt = self::db()->prepare("DELETE FROM contenedores WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->rowCount() > 0;
    }

    public static function obtener_camiones() {
        $stmt = self::db()->query("SELECT id, patente, modelo, marca, capacidad, tipo, estado FROM camiones ORDER BY id");
        return $stmt->fetchAll();
    }

    public static function obtener_camion_por_id($id) {
        $stmt = self::db()->prepare("SELECT id, patente, modelo, marca, capacidad, tipo, estado FROM camiones WHERE id = ?");
        $stmt->execute(array($id));
        $camion = $stmt->fetch();
        return $camion ? $camion : null;
    }

    public static function agregar_camion($camion) {
        $stmt = self::db()->prepare(
            "INSERT INTO camiones (patente, modelo, marca, capacidad, tipo, estado)
             VALUES (:patente, :modelo, :marca, :capacidad, :tipo, :estado)"
        );

        $stmt->execute(array(
            ':patente' => $camion['patente'],
            ':modelo' => $camion['modelo'],
            ':marca' => isset($camion['marca']) ? $camion['marca'] : '',
            ':capacidad' => $camion['capacidad'],
            ':tipo' => isset($camion['tipo']) ? $camion['tipo'] : 'plataforma',
            ':estado' => isset($camion['estado']) ? $camion['estado'] : 'disponible'
        ));

        return self::obtener_camion_por_id(self::db()->lastInsertId());
    }

    public static function eliminar_camion($id) {
        $stmt = self::db()->prepare("DELETE FROM camiones WHERE id = ?");
        $stmt->execute(array($id));
        return $stmt->rowCount() > 0;
    }
}
