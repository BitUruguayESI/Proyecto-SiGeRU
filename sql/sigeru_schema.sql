-- =====================================================================
-- SiGeRU (Sistema de Gestión de Residuos Urbanos) — BitUruguay
-- Script de creación de base de datos — MySQL 8.0
-- Basado en el MER final (24 entidades) + RN01-RN21 + 3ra Forma Normal
-- Incluye tabla adicional USUARIO_TELEFONO (teléfono como atributo
-- multivaluado de USUARIO, normalizado según 3FN — análogo a
-- CONTENEDOR_TIPO_RESIDUO para el atributo multivaluado de CONTENEDOR).
-- Incluye también CUADRILLA_RECOLECTOR (N:M CUADRILLA-OPERARIO, para la
-- "cuadrilla estable" de cada recolector/chofer/camión).
-- Total: 26 tablas
-- =====================================================================

DROP DATABASE IF EXISTS sigeru;
CREATE DATABASE sigeru CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sigeru;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- 1) USUARIO y su generalización disjunta/total (RN01)
--    OPERARIO / ADMINISTRADOR
-- =====================================================================

-- La columna tipo_usuario es el discriminador de la generalización:
-- resuelve de forma declarativa que todo USUARIO es de un único tipo
-- (disjunta) y obligatoriamente de alguno de los dos (total). Los
-- triggers de más abajo obligan a que exista la fila correspondiente
-- en OPERARIO o ADMINISTRADOR según este valor, y evitan que un mismo
-- C.I. termine en las dos tablas a la vez.
CREATE TABLE USUARIO (
    ci                        VARCHAR(15)  NOT NULL,
    contrasena                VARCHAR(255) NOT NULL,             -- hash (bcrypt/argon2), nunca texto plano
    email                     VARCHAR(150) NOT NULL,
    nombre_completo           VARCHAR(150) NOT NULL,
    tipo_usuario              ENUM('OPERARIO','ADMINISTRADOR') NOT NULL,
    fecha_alta                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado                    ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    carnet_salud_vencimiento  DATE NULL,   -- vencimiento del carnet de salud (todo USUARIO, no solo OPERARIO)
    PRIMARY KEY (ci),
    UNIQUE KEY uq_usuario_email (email)
) ENGINE=InnoDB;

-- Teléfono: atributo multivaluado de USUARIO (confirmado: una persona
-- puede tener más de un teléfono) -> tabla propia en 3FN.
CREATE TABLE USUARIO_TELEFONO (
    id_telefono   INT AUTO_INCREMENT,
    ci_usuario    VARCHAR(15) NOT NULL,
    telefono      VARCHAR(20) NOT NULL,
    PRIMARY KEY (id_telefono),
    UNIQUE KEY uq_usuario_telefono (ci_usuario, telefono),
    CONSTRAINT fk_telefono_usuario FOREIGN KEY (ci_usuario)
        REFERENCES USUARIO(ci) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- cargo distingue, dentro de OPERARIO, entre Chofer y Recolector (los dos
-- roles de personal de campo que pide el modelo de dashboards por rol);
-- se resolvió como ENUM (y no texto libre) para que sea un valor cerrado
-- y editable de forma segura desde el panel de Usuarios.
-- licencia_categoria/licencia_vencimiento solo aplican al cargo CHOFER
-- (quedan NULL para RECOLECTOR); se validan a nivel de aplicación, no
-- con un CHECK, porque MySQL/MariaDB no puede condicionar un CHECK a
-- otra columna de forma portable en todas las versiones.
CREATE TABLE OPERARIO (
    id_operario           INT AUTO_INCREMENT,
    cargo                 ENUM('CHOFER','RECOLECTOR') NOT NULL,
    ci_usuario            VARCHAR(15) NOT NULL,
    licencia_categoria    VARCHAR(10) NULL,   -- ej: "B", "D", "E" — obligatorio si cargo = CHOFER
    licencia_vencimiento  DATE NULL,          -- obligatorio si cargo = CHOFER
    PRIMARY KEY (id_operario),
    UNIQUE KEY uq_operario_usuario (ci_usuario),
    CONSTRAINT fk_operario_usuario FOREIGN KEY (ci_usuario)
        REFERENCES USUARIO(ci) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ADMINISTRADOR (
    id_admin      INT AUTO_INCREMENT,
    ci_usuario    VARCHAR(15) NOT NULL,
    PRIMARY KEY (id_admin),
    UNIQUE KEY uq_admin_usuario (ci_usuario),
    CONSTRAINT fk_admin_usuario FOREIGN KEY (ci_usuario)
        REFERENCES USUARIO(ci) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE VECINO (
    id_vecino   INT AUTO_INCREMENT,
    email       VARCHAR(150) NOT NULL,
    PRIMARY KEY (id_vecino),
    UNIQUE KEY uq_vecino_email (email)
) ENGINE=InnoDB;

CREATE TABLE SESION (
    id_sesion       INT AUTO_INCREMENT,
    fecha           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ci_usuario      VARCHAR(15) NOT NULL,
    PRIMARY KEY (id_sesion),
    CONSTRAINT fk_sesion_usuario FOREIGN KEY (ci_usuario)
        REFERENCES USUARIO(ci) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 2) Cuadrillas, rutas y contenedores
-- =====================================================================

-- id_camion / id_chofer son la "cuadrilla estable" (quién maneja qué
-- camión y con qué chofer día a día), independiente de la asignación
-- diaria puntual (ASIGNACION_DIARIA/ASIGNACION_OPERARIO, que sigue
-- existiendo para el detalle de fecha/turno/ruta). Son NULL mientras no
-- se asignan, y UNIQUE porque un camión o un chofer no puede estar en
-- más de una cuadrilla estable a la vez.
CREATE TABLE CUADRILLA (
    id_cuadrilla          INT AUTO_INCREMENT,
    nombre_cuadrilla      VARCHAR(100) NOT NULL,
    cantidad_integrantes  INT NOT NULL DEFAULT 0,
    id_camion             INT NULL,
    id_chofer             INT NULL,
    PRIMARY KEY (id_cuadrilla),
    UNIQUE KEY uq_cuadrilla_camion (id_camion),
    UNIQUE KEY uq_cuadrilla_chofer (id_chofer),
    CONSTRAINT fk_cuadrilla_camion FOREIGN KEY (id_camion)
        REFERENCES CAMION(id_camion) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_cuadrilla_chofer FOREIGN KEY (id_chofer)
        REFERENCES OPERARIO(id_operario) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE RUTA (
    id_ruta       INT AUTO_INCREMENT,
    nombre_zona   VARCHAR(100) NOT NULL,
    frecuencia    VARCHAR(50)  NOT NULL,   -- ej: "diaria", "lunes y jueves"
    PRIMARY KEY (id_ruta)
) ENGINE=InnoDB;

-- Relación N:M CUADRILLA-OPERARIO (recolectores de la cuadrilla estable).
-- id_operario es UNIQUE porque un recolector integra una sola cuadrilla
-- estable a la vez (a diferencia de CUADRILLA_RUTA_HABITUAL, que sí es
-- N:M en ambos sentidos).
CREATE TABLE CUADRILLA_RECOLECTOR (
    id_cuadrilla   INT NOT NULL,
    id_operario    INT NOT NULL,
    PRIMARY KEY (id_cuadrilla, id_operario),
    UNIQUE KEY uq_recolector_cuadrilla (id_operario),
    CONSTRAINT fk_crec_cuadrilla FOREIGN KEY (id_cuadrilla)
        REFERENCES CUADRILLA(id_cuadrilla) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_crec_operario FOREIGN KEY (id_operario)
        REFERENCES OPERARIO(id_operario) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Relación N:M CUADRILLA-RUTA (ruta habitual de cada cuadrilla)
CREATE TABLE CUADRILLA_RUTA_HABITUAL (
    id_cuadrilla   INT NOT NULL,
    id_ruta        INT NOT NULL,
    PRIMARY KEY (id_cuadrilla, id_ruta),
    CONSTRAINT fk_cru_cuadrilla FOREIGN KEY (id_cuadrilla)
        REFERENCES CUADRILLA(id_cuadrilla) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cru_ruta FOREIGN KEY (id_ruta)
        REFERENCES RUTA(id_ruta) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE CONTENEDOR (
    id_contenedor   INT AUTO_INCREMENT,
    direccion       VARCHAR(150) NULL,        -- dirección cargada por el Administrador (RF07)
    coordenadas     VARCHAR(60) NULL,         -- "lat,long"; se completa automáticamente
                                               -- geocodificando "direccion" (RF13, mapa interactivo).
                                               -- Queda NULL si no se pudo geocodificar todavía.
    estado          ENUM('OPERATIVO','DESBORDADO','FUERA_DE_SERVICIO') NOT NULL DEFAULT 'OPERATIVO',
    nivel_llenado   TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- 0-100 (%)
    id_ruta         INT NULL,
    PRIMARY KEY (id_contenedor),
    CONSTRAINT ck_contenedor_nivel CHECK (nivel_llenado BETWEEN 0 AND 100),
    CONSTRAINT fk_contenedor_ruta FOREIGN KEY (id_ruta)
        REFERENCES RUTA(id_ruta) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Tipo de residuo: atributo multivaluado de CONTENEDOR (un contenedor
-- puede admitir más de un tipo de residuo) -> tabla propia en 3FN.
CREATE TABLE CONTENEDOR_TIPO_RESIDUO (
    id_contenedor   INT NOT NULL,
    tipo_residuo    VARCHAR(40) NOT NULL,   -- ej: "organico", "plastico", "vidrio"
    PRIMARY KEY (id_contenedor, tipo_residuo),
    CONSTRAINT fk_ctr_contenedor FOREIGN KEY (id_contenedor)
        REFERENCES CONTENEDOR(id_contenedor) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 3) Incidencias
-- =====================================================================

-- Máquina de estados simplificada a 3 valores: PENDIENTE (sin cuadrilla
-- asignada) -> EN_PROCESO (el Administrador le asignó una cuadrilla,
-- directo, sin paso intermedio) -> RESUELTA (la marca el Administrador
-- o el Chofer de la cuadrilla).
CREATE TABLE INCIDENCIA (
    id_incidencia     INT AUTO_INCREMENT,
    descripcion       TEXT NOT NULL,
    fecha_registro    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion  DATETIME NULL,
    estado            ENUM('PENDIENTE','EN_PROCESO','RESUELTA') NOT NULL DEFAULT 'PENDIENTE',
    tipo_incidencia   VARCHAR(60) NOT NULL,
    id_contenedor     INT NULL,
    id_vecino         INT NULL,
    id_cuadrilla      INT NULL,
    PRIMARY KEY (id_incidencia),
    CONSTRAINT fk_incidencia_contenedor FOREIGN KEY (id_contenedor)
        REFERENCES CONTENEDOR(id_contenedor) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_incidencia_vecino FOREIGN KEY (id_vecino)
        REFERENCES VECINO(id_vecino) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_incidencia_cuadrilla FOREIGN KEY (id_cuadrilla)
        REFERENCES CUADRILLA(id_cuadrilla) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 4) Camiones, instalaciones, asignaciones diarias y descargas
-- =====================================================================

CREATE TABLE CAMION (
    id_camion                   INT AUTO_INCREMENT,
    matricula                   VARCHAR(15) NOT NULL,
    marca                       VARCHAR(60) NOT NULL,
    modelo                      VARCHAR(60) NOT NULL,
    capacidad_carga             DECIMAL(8,2) NOT NULL,  -- kg o m3
    estado                      ENUM('DISPONIBLE','EN_RUTA','EN_MANTENIMIENTO','FUERA_DE_SERVICIO') NOT NULL DEFAULT 'DISPONIBLE',
    kilometraje                 INT UNSIGNED NOT NULL DEFAULT 0,
    fecha_ultimo_mantenimiento  DATE NULL,
    PRIMARY KEY (id_camion),
    UNIQUE KEY uq_camion_matricula (matricula)
) ENGINE=InnoDB;

CREATE TABLE INSTALACION (
    id_centro           INT AUTO_INCREMENT,
    nombre              VARCHAR(100) NOT NULL,
    coordenadas         VARCHAR(60) NOT NULL,
    capacidad_maxima    DECIMAL(10,2) NOT NULL,
    residuos_aceptados  VARCHAR(200) NOT NULL,
    tipo_instalacion    ENUM('CENTRO_ACOPIO','VERTEDERO') NOT NULL,
    PRIMARY KEY (id_centro)
) ENGINE=InnoDB;

CREATE TABLE ASIGNACION_DIARIA (
    id_asignacion    INT AUTO_INCREMENT,
    fecha            DATE NOT NULL,
    turno            ENUM('MANANA','TARDE','NOCTURNO') NOT NULL,
    id_administrador INT NOT NULL,
    id_cuadrilla     INT NOT NULL,
    id_ruta          INT NOT NULL,
    id_camion        INT NOT NULL,
    PRIMARY KEY (id_asignacion),
    CONSTRAINT fk_asigdiaria_admin FOREIGN KEY (id_administrador)
        REFERENCES ADMINISTRADOR(id_admin) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_asigdiaria_cuadrilla FOREIGN KEY (id_cuadrilla)
        REFERENCES CUADRILLA(id_cuadrilla) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_asigdiaria_ruta FOREIGN KEY (id_ruta)
        REFERENCES RUTA(id_ruta) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_asigdiaria_camion FOREIGN KEY (id_camion)
        REFERENCES CAMION(id_camion) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Relación N:M ASIGNACION_DIARIA-OPERARIO (cuadrilla puede tener varios
-- operarios asignados el mismo día, cada uno con su rol)
CREATE TABLE ASIGNACION_OPERARIO (
    id_asignacion     INT NOT NULL,
    id_operario       INT NOT NULL,
    rol_en_asignacion VARCHAR(60) NOT NULL,
    PRIMARY KEY (id_asignacion, id_operario),
    CONSTRAINT fk_asigop_asignacion FOREIGN KEY (id_asignacion)
        REFERENCES ASIGNACION_DIARIA(id_asignacion) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_asigop_operario FOREIGN KEY (id_operario)
        REFERENCES OPERARIO(id_operario) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Registro individual de descarga (RN16): un camión puede descargar
-- más de una vez, en instalaciones distintas.
CREATE TABLE DESCARGA (
    id_descarga     INT AUTO_INCREMENT,
    fecha_descarga  DATE NOT NULL,
    hora_descarga   TIME NOT NULL,
    peso            DECIMAL(10,2) NOT NULL,  -- kg
    id_camion       INT NOT NULL,
    id_centro       INT NOT NULL,
    PRIMARY KEY (id_descarga),
    CONSTRAINT fk_descarga_camion FOREIGN KEY (id_camion)
        REFERENCES CAMION(id_camion) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_descarga_instalacion FOREIGN KEY (id_centro)
        REFERENCES INSTALACION(id_centro) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 5) Maquinaria y repuestos (RN15, RN17 — relaciones N:M)
-- =====================================================================

CREATE TABLE MAQUINARIA (
    id_maquinaria   INT AUTO_INCREMENT,
    nombre          VARCHAR(100) NOT NULL,
    tipo            VARCHAR(60)  NOT NULL,
    estado          ENUM('OPERATIVA','EN_MANTENIMIENTO','FUERA_DE_SERVICIO') NOT NULL DEFAULT 'OPERATIVA',
    id_centro       INT NOT NULL,
    PRIMARY KEY (id_maquinaria),
    CONSTRAINT fk_maquinaria_instalacion FOREIGN KEY (id_centro)
        REFERENCES INSTALACION(id_centro) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE REPUESTO (
    id_repuesto   INT AUTO_INCREMENT,
    nombre        VARCHAR(100) NOT NULL,
    tipo          VARCHAR(60)  NOT NULL,
    stock         INT UNSIGNED NOT NULL DEFAULT 0,
    estado        ENUM('DISPONIBLE','AGOTADO','DESCONTINUADO') NOT NULL DEFAULT 'DISPONIBLE',
    PRIMARY KEY (id_repuesto)
) ENGINE=InnoDB;

CREATE TABLE MAQUINARIA_REPUESTO (
    id_maquinaria   INT NOT NULL,
    id_repuesto     INT NOT NULL,
    PRIMARY KEY (id_maquinaria, id_repuesto),
    CONSTRAINT fk_maqrep_maquinaria FOREIGN KEY (id_maquinaria)
        REFERENCES MAQUINARIA(id_maquinaria) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_maqrep_repuesto FOREIGN KEY (id_repuesto)
        REFERENCES REPUESTO(id_repuesto) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE CAMION_REPUESTO (
    id_camion     INT NOT NULL,
    id_repuesto   INT NOT NULL,
    PRIMARY KEY (id_camion, id_repuesto),
    CONSTRAINT fk_camrep_camion FOREIGN KEY (id_camion)
        REFERENCES CAMION(id_camion) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_camrep_repuesto FOREIGN KEY (id_repuesto)
        REFERENCES REPUESTO(id_repuesto) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 6) Tablas agregadas (RN16, RN18-RN21): auditoría, notificaciones,
--    historial de mantenimiento de camión y consultas públicas
-- =====================================================================

CREATE TABLE AUDITORIA (
    id_auditoria      INT AUTO_INCREMENT,
    accion            VARCHAR(200) NOT NULL,
    fecha_hora        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_administrador  INT NULL,
    PRIMARY KEY (id_auditoria),
    -- SET NULL: si se elimina el administrador, el historial de
    -- auditoría se conserva (queda con responsable "desconocido").
    CONSTRAINT fk_auditoria_admin FOREIGN KEY (id_administrador)
        REFERENCES ADMINISTRADOR(id_admin) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE NOTIFICACION (
    id_notificacion   INT AUTO_INCREMENT,
    mensaje           VARCHAR(300) NOT NULL,
    fecha_hora        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo              VARCHAR(60) NOT NULL,
    leida             BOOLEAN NOT NULL DEFAULT FALSE,
    ci_usuario        VARCHAR(15) NOT NULL,
    PRIMARY KEY (id_notificacion),
    CONSTRAINT fk_notificacion_usuario FOREIGN KEY (ci_usuario)
        REFERENCES USUARIO(ci) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE MANTENIMIENTO_CAMION (
    id_mantenimiento   INT AUTO_INCREMENT,
    fecha              DATE NOT NULL,
    kilometraje        INT UNSIGNED NOT NULL,
    tipo_mantenimiento VARCHAR(60) NOT NULL,
    descripcion        TEXT NULL,
    id_camion          INT NOT NULL,
    PRIMARY KEY (id_mantenimiento),
    CONSTRAINT fk_mantcamion_camion FOREIGN KEY (id_camion)
        REFERENCES CAMION(id_camion) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE CONSULTA_PUBLICA (
    id_consulta   INT AUTO_INCREMENT,
    nombre        VARCHAR(150) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    mensaje       TEXT NOT NULL,
    fecha         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    estado        ENUM('PENDIENTE','RESPONDIDA') NOT NULL DEFAULT 'PENDIENTE',
    PRIMARY KEY (id_consulta)
) ENGINE=InnoDB;

-- Pedidos públicos de retiro de residuos que no van al contenedor común
-- (poda, residuos eléctricos, escombros, muebles, etc.). No tienen un
-- día fijo por tipo: el Administrador coordina la fecha de retiro caso
-- a caso una vez que llega la solicitud (por eso fecha_retiro es NULL
-- hasta que se marca RETIRADO).
CREATE TABLE SOLICITUD_RETIRO (
    id_solicitud    INT AUTO_INCREMENT,
    tipo_residuo    VARCHAR(60) NOT NULL,
    direccion       VARCHAR(200) NOT NULL,
    descripcion     TEXT NULL,
    fecha_registro  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_retiro    DATETIME NULL,
    estado          ENUM('PENDIENTE','RETIRADO') NOT NULL DEFAULT 'PENDIENTE',
    id_vecino       INT NULL,
    id_cuadrilla    INT NULL,
    PRIMARY KEY (id_solicitud),
    CONSTRAINT fk_retiro_vecino FOREIGN KEY (id_vecino)
        REFERENCES VECINO(id_vecino) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_retiro_cuadrilla FOREIGN KEY (id_cuadrilla)
        REFERENCES CUADRILLA(id_cuadrilla) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- 7) Generalización disjunta y total de USUARIO (RN01) — triggers
--
-- USUARIO.tipo_usuario ya obliga (NOT NULL + ENUM) a que cada usuario
-- sea de un único tipo (disjunta) y de alguno de los dos (total),
-- pero por sí sola esa columna no garantiza que exista la fila
-- correspondiente en OPERARIO o ADMINISTRADOR, ni impide que alguien
-- inserte en la tabla "equivocada". Estos triggers resuelven eso:
-- =====================================================================

DELIMITER $$

-- No dejar crear un OPERARIO si el usuario no está marcado como tal
CREATE TRIGGER trg_operario_check_tipo
BEFORE INSERT ON OPERARIO
FOR EACH ROW
BEGIN
    DECLARE v_tipo VARCHAR(20);
    SELECT tipo_usuario INTO v_tipo FROM USUARIO WHERE ci = NEW.ci_usuario;
    IF v_tipo IS NULL OR v_tipo <> 'OPERARIO' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'RN01: el USUARIO debe tener tipo_usuario = OPERARIO para insertarse en OPERARIO';
    END IF;
END$$

-- No dejar crear un ADMINISTRADOR si el usuario no está marcado como tal
CREATE TRIGGER trg_administrador_check_tipo
BEFORE INSERT ON ADMINISTRADOR
FOR EACH ROW
BEGIN
    DECLARE v_tipo VARCHAR(20);
    SELECT tipo_usuario INTO v_tipo FROM USUARIO WHERE ci = NEW.ci_usuario;
    IF v_tipo IS NULL OR v_tipo <> 'ADMINISTRADOR' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'RN01: el USUARIO debe tener tipo_usuario = ADMINISTRADOR para insertarse en ADMINISTRADOR';
    END IF;
END$$

-- No dejar cambiar tipo_usuario si ya existe la fila hija correspondiente
-- (evita que un mismo C.I. termine con fila en OPERARIO y en ADMINISTRADOR)
CREATE TRIGGER trg_usuario_check_cambio_tipo
BEFORE UPDATE ON USUARIO
FOR EACH ROW
BEGIN
    IF OLD.tipo_usuario <> NEW.tipo_usuario THEN
        IF OLD.tipo_usuario = 'OPERARIO' AND EXISTS (SELECT 1 FROM OPERARIO WHERE ci_usuario = OLD.ci) THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'RN01: no se puede cambiar el tipo de usuario mientras exista la fila en OPERARIO';
        END IF;
        IF OLD.tipo_usuario = 'ADMINISTRADOR' AND EXISTS (SELECT 1 FROM ADMINISTRADOR WHERE ci_usuario = OLD.ci) THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'RN01: no se puede cambiar el tipo de usuario mientras exista la fila en ADMINISTRADOR';
        END IF;
    END IF;
END$$

DELIMITER ;

-- Nota: la participación TOTAL (todo USUARIO debe terminar con una fila
-- en OPERARIO o en ADMINISTRADOR inmediatamente después de darse de alta)
-- no se puede exigir con FK/trigger en el mismo INSERT sin usar una
-- transacción explícita. Se resuelve a nivel de aplicación: el alta de
-- un USUARIO y la del OPERARIO/ADMINISTRADOR correspondiente se hacen
-- dentro de una misma transacción (dos INSERT, un solo commit).
