-- Migración: agrega la columna "municipio" a RUTA (de qué municipio de
-- Montevideo es la ruta — la usan contenedores.php, el mapa interactivo
-- RF13 y los reportes por zona).
--
-- Corré esto UNA sola vez contra tu base "sigeru" ya existente, para no
-- tener que reimportar sql/sigeru_schema.sql desde cero (y perder los
-- datos que ya cargaste a mano probando la app, incluyendo el CSV
-- municipal de contenedores si ya lo importaste):
--
--   mysql -u root -p --default-character-set=utf8mb4 sigeru < sql/agregar_municipio_ruta.sql
--
-- Si en algún momento reinstalás todo desde sql/sigeru_schema.sql (que ya
-- incluye esta columna), no hace falta correr este archivo.

ALTER TABLE RUTA
    ADD COLUMN municipio ENUM('A','B','C','CH','D','E','F','G') NULL AFTER frecuencia;
