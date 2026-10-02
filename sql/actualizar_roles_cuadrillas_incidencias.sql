-- =====================================================================
-- Migración: roles editables (Administrador/Recolector/Chofer),
-- cuadrilla estable (camión + chofer + recolectores) e incidencias
-- simplificadas a 3 estados.
--
-- Para una base YA CREADA con sql/sigeru_schema.sql (o con una versión
-- anterior de este proyecto). Si vas a crear la base desde cero, no
-- hace falta correr esto: sql/sigeru_schema.sql ya incluye estos
-- cambios.
--
-- Cómo ejecutarlo (ejemplo con XAMPP, desde la carpeta del proyecto):
--   mysql --default-character-set=utf8mb4 -u root sigeru < sql/actualizar_roles_cuadrillas_incidencias.sql
-- (ajustá el usuario/base según tu instalación; en phpMyAdmin también
-- podés pegar este archivo entero en la pestaña SQL de la base sigeru).
--
-- IMPORTANTE: hacé un respaldo antes si tenés datos que te importen.
-- Este script normaliza los datos existentes ANTES de endurecer los
-- tipos de columna, así que no debería fallar con datos ya cargados,
-- pero un backup nunca está de más.
-- =====================================================================

USE sigeru;

-- ---------------------------------------------------------------------
-- 1) OPERARIO.cargo: de texto libre a ENUM('CHOFER','RECOLECTOR')
-- ---------------------------------------------------------------------
-- Normalizamos primero cualquier variante que haya quedado cargada como
-- texto libre (p. ej. "Chofer", "chofer ", "Conductor") a uno de los dos
-- valores válidos, para que el ALTER de abajo no falle.
UPDATE OPERARIO
   SET cargo = 'CHOFER'
 WHERE UPPER(TRIM(cargo)) IN ('CHOFER', 'CONDUCTOR');

UPDATE OPERARIO
   SET cargo = 'RECOLECTOR'
 WHERE UPPER(TRIM(cargo)) NOT IN ('CHOFER');

ALTER TABLE OPERARIO
    MODIFY COLUMN cargo ENUM('CHOFER','RECOLECTOR') NOT NULL;

-- ---------------------------------------------------------------------
-- 2) CUADRILLA: cuadrilla "estable" (1 camión + 1 chofer + N recolectores)
-- ---------------------------------------------------------------------
ALTER TABLE CUADRILLA
    ADD COLUMN id_camion INT NULL AFTER cantidad_integrantes,
    ADD COLUMN id_chofer INT NULL AFTER id_camion,
    ADD UNIQUE KEY uq_cuadrilla_camion (id_camion),
    ADD UNIQUE KEY uq_cuadrilla_chofer (id_chofer),
    ADD CONSTRAINT fk_cuadrilla_camion FOREIGN KEY (id_camion)
        REFERENCES CAMION(id_camion) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_cuadrilla_chofer FOREIGN KEY (id_chofer)
        REFERENCES OPERARIO(id_operario) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS CUADRILLA_RECOLECTOR (
    id_cuadrilla   INT NOT NULL,
    id_operario    INT NOT NULL,
    PRIMARY KEY (id_cuadrilla, id_operario),
    UNIQUE KEY uq_recolector_cuadrilla (id_operario),
    CONSTRAINT fk_crec_cuadrilla FOREIGN KEY (id_cuadrilla)
        REFERENCES CUADRILLA(id_cuadrilla) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_crec_operario FOREIGN KEY (id_operario)
        REFERENCES OPERARIO(id_operario) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3) INCIDENCIA.estado: de 5 estados a 3 (PENDIENTE/EN_PROCESO/RESUELTA)
-- ---------------------------------------------------------------------
-- ASIGNADA y ESCALADA pasan a EN_PROCESO (ya tenían cuadrilla asignada,
-- que es justo lo que ahora significa EN_PROCESO).
UPDATE INCIDENCIA
   SET estado = 'EN_PROCESO'
 WHERE estado IN ('ASIGNADA', 'ESCALADA');

ALTER TABLE INCIDENCIA
    MODIFY COLUMN estado ENUM('PENDIENTE','EN_PROCESO','RESUELTA') NOT NULL DEFAULT 'PENDIENTE';

-- fecha_escalacion ya no se usa (no existe más el estado ESCALADA).
ALTER TABLE INCIDENCIA
    DROP COLUMN fecha_escalacion;
