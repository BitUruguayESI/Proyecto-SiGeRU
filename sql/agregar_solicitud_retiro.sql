-- Migración: agrega la tabla SOLICITUD_RETIRO (solicitudes públicas de
-- retiro de residuos especiales: poda, residuos eléctricos, escombros,
-- muebles, etc. — no van al contenedor común).
--
-- Corré esto UNA sola vez contra tu base "sigeru" ya existente, para no
-- tener que reimportar sql/sigeru_schema.sql desde cero (y perder los
-- datos que ya cargaste a mano probando la app):
--
--   mysql -u root -p --default-character-set=utf8mb4 sigeru < sql/agregar_solicitud_retiro.sql
--
-- Si en algún momento reinstalás todo desde sql/sigeru_schema.sql (que ya
-- incluye esta tabla), no hace falta correr este archivo.

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
