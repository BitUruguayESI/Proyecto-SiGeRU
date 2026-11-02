-- Migración: agrega "referencia" a NOTIFICACION — el ID (o la cédula,
-- para carnet/licencia) de la fila a la que apunta esa notificación, así
-- la campana puede llevar directo a ella (responder una consulta,
-- resolver una incidencia, coordinar un retiro especial, etc.) en vez de
-- solo abrir la sección en general.
--
-- Corré esto UNA sola vez contra tu base "sigeru" ya existente, para no
-- tener que reimportar sql/sigeru_schema.sql desde cero (y perder los
-- datos que ya cargaste a mano probando la app):
--
--   docker compose exec -T db mysql -u root -prootpass_dev sigeru < sql/agregar_referencia_notificacion.sql
--
-- Si en algún momento reinstalás todo desde sql/sigeru_schema.sql (que ya
-- incluye esta columna), no hace falta correr este archivo.

ALTER TABLE NOTIFICACION
    ADD COLUMN referencia VARCHAR(30) NULL AFTER tipo;
