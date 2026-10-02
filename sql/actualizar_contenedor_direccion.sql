-- Migración: agrega dirección + mapa interactivo a CONTENEDOR (RF13).
--
-- Corré esto UNA sola vez contra tu base "sigeru" ya existente, para no
-- tener que reimportar sigeru_schema.sql desde cero (y perder los
-- contenedores/rutas/cuadrillas que ya cargaste a mano probando la app):
--
--   mysql -u root -p --default-character-set=utf8mb4 sigeru < sql/actualizar_contenedor_direccion.sql
--
-- Si en algún momento reinstalás todo desde sql/sigeru_schema.sql (que ya
-- incluye esta columna), no hace falta correr este archivo.

ALTER TABLE CONTENEDOR
    ADD COLUMN direccion VARCHAR(150) NULL AFTER id_contenedor,
    MODIFY COLUMN coordenadas VARCHAR(60) NULL;
