-- Migración: agrega el carnet de salud (todo USUARIO) y los datos de
-- licencia de conducir (solo OPERARIO con cargo CHOFER).
--
-- Corré esto UNA sola vez contra tu base "sigeru" ya existente, para no
-- tener que reimportar sql/sigeru_schema.sql desde cero (y perder los
-- datos que ya cargaste a mano probando la app):
--
--   mysql -u root -p --default-character-set=utf8mb4 sigeru < sql/agregar_licencia_y_carnet_salud.sql
--
-- Si en algún momento reinstalás todo desde sql/sigeru_schema.sql (que ya
-- incluye estas columnas), no hace falta correr este archivo.

ALTER TABLE USUARIO
    ADD COLUMN carnet_salud_vencimiento DATE NULL AFTER estado;

ALTER TABLE OPERARIO
    ADD COLUMN licencia_categoria   VARCHAR(10) NULL AFTER ci_usuario,
    ADD COLUMN licencia_vencimiento DATE NULL AFTER licencia_categoria;
