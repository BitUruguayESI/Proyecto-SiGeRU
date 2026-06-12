-- ===== DDL - SISTEMA BITURUGUAY (v1.0) =====
-- Creación de Base de Datos y Tablas
-- Fecha: 2024
-- Sistema: BITURUGUAY - Sistema de Gestión de Rutas

-- ===== CREAR BASE DE DATOS =====
CREATE DATABASE IF NOT EXISTS bituruguay;
USE bituruguay;

-- ===== TABLA: USUARIOS =====
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'gerente', 'operador', 'usuario') NOT NULL DEFAULT 'usuario',
    empresa VARCHAR(150),
    telefono VARCHAR(20),
    activo BOOLEAN DEFAULT TRUE,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== TABLA: CONTENEDORES =====
CREATE TABLE IF NOT EXISTS contenedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    tipo ENUM('20ft', '40ft', 'high-cube', 'openside', 'refrigerado') NOT NULL,
    capacidad INT NOT NULL COMMENT 'Capacidad en kg',
    estado ENUM('disponible', 'en-uso', 'mantenimiento', 'retido') DEFAULT 'disponible',
    fecha_fabricacion DATE,
    proxima_inspeccion DATE,
    ubicacion VARCHAR(100),
    observaciones TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo (codigo),
    INDEX idx_tipo (tipo),
    INDEX idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== TABLA: CAMIONES =====
CREATE TABLE IF NOT EXISTS camiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patente VARCHAR(20) UNIQUE NOT NULL,
    modelo VARCHAR(100) NOT NULL,
    marca VARCHAR(50),
    año_fabricacion YEAR,
    capacidad INT NOT NULL COMMENT 'Capacidad en kg',
    tipo ENUM('plataforma', 'tolva', 'refrigerado', 'tanque', 'jaula') NOT NULL,
    estado ENUM('disponible', 'en-viaje', 'mantenimiento', 'fuera-servicio') DEFAULT 'disponible',
    chofer_id INT,
    proxima_revision DATE,
    observaciones TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_patente (patente),
    INDEX idx_estado (estado),
    FOREIGN KEY (chofer_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== TABLA: RUTAS =====
CREATE TABLE IF NOT EXISTS rutas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_ruta VARCHAR(50) UNIQUE NOT NULL,
    origen VARCHAR(100) NOT NULL,
    destino VARCHAR(100) NOT NULL,
    distancia_km DECIMAL(8, 2),
    tiempo_estimado INT COMMENT 'Tiempo en minutos',
    estado ENUM('planificada', 'en-progreso', 'completada', 'cancelada') DEFAULT 'planificada',
    camion_id INT NOT NULL,
    contenedor_id INT,
    usuario_id INT NOT NULL,
    fecha_salida DATETIME,
    fecha_llegada DATETIME,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_numero_ruta (numero_ruta),
    INDEX idx_estado (estado),
    INDEX idx_fecha_salida (fecha_salida),
    FOREIGN KEY (camion_id) REFERENCES camiones(id) ON DELETE RESTRICT,
    FOREIGN KEY (contenedor_id) REFERENCES contenedores(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== TABLA: ASIGNACIONES =====
CREATE TABLE IF NOT EXISTS asignaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camion_id INT NOT NULL,
    contenedor_id INT NOT NULL,
    usuario_id INT NOT NULL,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_desasignacion DATETIME,
    estado ENUM('activa', 'completada', 'cancelada') DEFAULT 'activa',
    observaciones TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_camion_id (camion_id),
    INDEX idx_contenedor_id (contenedor_id),
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_estado (estado),
    FOREIGN KEY (camion_id) REFERENCES camiones(id) ON DELETE CASCADE,
    FOREIGN KEY (contenedor_id) REFERENCES contenedores(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== TABLA: REGISTROS_ACTIVIDAD =====
CREATE TABLE IF NOT EXISTS registros_actividad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    accion VARCHAR(100) NOT NULL,
    entidad VARCHAR(50),
    entidad_id INT,
    descripcion TEXT,
    ip_address VARCHAR(45),
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_fecha_registro (fecha_registro),
    INDEX idx_accion (accion),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== ÍNDICES ADICIONALES PARA OPTIMIZACIÓN =====
ALTER TABLE usuarios ADD INDEX idx_activo (activo);
ALTER TABLE camiones ADD INDEX idx_tipo (tipo);
ALTER TABLE contenedores ADD INDEX idx_capacidad (capacidad);
ALTER TABLE rutas ADD INDEX idx_camion_contenedor (camion_id, contenedor_id);

-- ===== VISTAS (OPCIONAL) =====

-- Vista: Resumen de Rutas Activas
CREATE OR REPLACE VIEW v_rutas_activas AS
SELECT 
    r.id,
    r.numero_ruta,
    r.origen,
    r.destino,
    ca.patente AS camion_patente,
    co.codigo AS contenedor_codigo,
    u.nombre AS usuario,
    r.estado,
    r.fecha_salida,
    TIMEDIFF(NOW(), r.fecha_salida) AS tiempo_transcurrido
FROM rutas r
JOIN camiones ca ON r.camion_id = ca.id
LEFT JOIN contenedores co ON r.contenedor_id = co.id
JOIN usuarios u ON r.usuario_id = u.id
WHERE r.estado IN ('planificada', 'en-progreso');

-- Vista: Estado de Flota
CREATE OR REPLACE VIEW v_estado_flota AS
SELECT 
    'Camiones' AS tipo,
    estado,
    COUNT(*) AS cantidad
FROM camiones
GROUP BY estado
UNION ALL
SELECT 
    'Contenedores' AS tipo,
    estado,
    COUNT(*) AS cantidad
FROM contenedores
GROUP BY estado;

-- ===== INSERCIÓN DE DATOS DE PRUEBA =====

INSERT INTO usuarios (nombre, email, password, rol, empresa, telefono) VALUES
('Admin Sistema', 'admin@bituruguay.com', 'admin123', 'admin', 'BITURUGUAY', '098765432'),
('Gerente Logística', 'gerente@bituruguay.com', 'gerente123', 'gerente', 'BITURUGUAY', '098765431'),
('Operador 1', 'operador1@bituruguay.com', 'operador123', 'operador', 'BITURUGUAY', '098765430'),
('Usuario Demo', 'demo@example.com', 'demo123', 'usuario', 'Demo Corp', '098765429');

INSERT INTO contenedores (codigo, tipo, capacidad, estado, observaciones) VALUES
('CNT-001', '20ft', 20000, 'disponible', 'Contenedor estándar en buen estado'),
('CNT-002', '40ft', 40000, 'disponible', 'Contenedor grande - nueva unidad'),
('CNT-003', 'refrigerado', 18000, 'en-uso', 'Para transporte de productos refrigerados'),
('CNT-004', 'openside', 25000, 'disponible', 'Contenedor con apertura lateral');

INSERT INTO camiones (patente, modelo, marca, año_fabricacion, capacidad, tipo, estado) VALUES
('AAA-000', 'Volvo FH16', 'Volvo', 2022, 25000, 'plataforma', 'disponible'),
('BBB-111', 'Scania R440', 'Scania', 2021, 28000, 'plataforma', 'disponible'),
('CCC-222', 'MAN TGX', 'MAN', 2020, 26000, 'tolva', 'disponible'),
('DDD-333', 'Mercedes Actros', 'Mercedes', 2019, 24000, 'refrigerado', 'en-viaje');
