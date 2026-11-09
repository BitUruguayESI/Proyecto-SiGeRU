-- =====================================================================
-- reparar_codificacion.sql -- Repara texto con tildes/ñ corrompido
-- (UTF-8 guardado como si fuera Latin-1, ej: "Cordón" -> "CordÃ³n") en
-- TODAS las columnas de texto libre de la base, no solo USUARIO.
--
-- Es seguro correrlo mas de una vez y seguro aunque algunas columnas
-- no tengan ninguna fila afectada: el WHERE ... LIKE '%Ã%' hace que
-- SOLO se toquen las filas que de verdad tienen el patron corrompido;
-- el resto queda intacto.
--
-- Uso (phpMyAdmin -> pestaña SQL, pegar y ejecutar; o por consola):
--   mysql -u root -p sigeru < sql/reparar_codificacion.sql
-- =====================================================================

SET autocommit = 0;
START TRANSACTION;

UPDATE USUARIO
  SET nombre_completo = CONVERT(CAST(CONVERT(nombre_completo USING latin1) AS BINARY) USING utf8mb4)
  WHERE nombre_completo LIKE '%Ã%';

UPDATE RUTA
  SET nombre_zona = CONVERT(CAST(CONVERT(nombre_zona USING latin1) AS BINARY) USING utf8mb4)
  WHERE nombre_zona LIKE '%Ã%';
UPDATE RUTA
  SET frecuencia = CONVERT(CAST(CONVERT(frecuencia USING latin1) AS BINARY) USING utf8mb4)
  WHERE frecuencia LIKE '%Ã%';

UPDATE CONTENEDOR
  SET direccion = CONVERT(CAST(CONVERT(direccion USING latin1) AS BINARY) USING utf8mb4)
  WHERE direccion LIKE '%Ã%';

UPDATE CONTENEDOR_TIPO_RESIDUO
  SET tipo_residuo = CONVERT(CAST(CONVERT(tipo_residuo USING latin1) AS BINARY) USING utf8mb4)
  WHERE tipo_residuo LIKE '%Ã%';

UPDATE INCIDENCIA
  SET descripcion = CONVERT(CAST(CONVERT(descripcion USING latin1) AS BINARY) USING utf8mb4)
  WHERE descripcion LIKE '%Ã%';
UPDATE INCIDENCIA
  SET tipo_incidencia = CONVERT(CAST(CONVERT(tipo_incidencia USING latin1) AS BINARY) USING utf8mb4)
  WHERE tipo_incidencia LIKE '%Ã%';

UPDATE INSTALACION
  SET nombre = CONVERT(CAST(CONVERT(nombre USING latin1) AS BINARY) USING utf8mb4)
  WHERE nombre LIKE '%Ã%';
UPDATE INSTALACION
  SET residuos_aceptados = CONVERT(CAST(CONVERT(residuos_aceptados USING latin1) AS BINARY) USING utf8mb4)
  WHERE residuos_aceptados LIKE '%Ã%';

UPDATE MAQUINARIA
  SET nombre = CONVERT(CAST(CONVERT(nombre USING latin1) AS BINARY) USING utf8mb4)
  WHERE nombre LIKE '%Ã%';
UPDATE MAQUINARIA
  SET tipo = CONVERT(CAST(CONVERT(tipo USING latin1) AS BINARY) USING utf8mb4)
  WHERE tipo LIKE '%Ã%';

UPDATE REPUESTO
  SET nombre = CONVERT(CAST(CONVERT(nombre USING latin1) AS BINARY) USING utf8mb4)
  WHERE nombre LIKE '%Ã%';
UPDATE REPUESTO
  SET tipo = CONVERT(CAST(CONVERT(tipo USING latin1) AS BINARY) USING utf8mb4)
  WHERE tipo LIKE '%Ã%';

UPDATE CUADRILLA
  SET nombre_cuadrilla = CONVERT(CAST(CONVERT(nombre_cuadrilla USING latin1) AS BINARY) USING utf8mb4)
  WHERE nombre_cuadrilla LIKE '%Ã%';

UPDATE ASIGNACION_OPERARIO
  SET rol_en_asignacion = CONVERT(CAST(CONVERT(rol_en_asignacion USING latin1) AS BINARY) USING utf8mb4)
  WHERE rol_en_asignacion LIKE '%Ã%';

UPDATE AUDITORIA
  SET accion = CONVERT(CAST(CONVERT(accion USING latin1) AS BINARY) USING utf8mb4)
  WHERE accion LIKE '%Ã%';

UPDATE NOTIFICACION
  SET mensaje = CONVERT(CAST(CONVERT(mensaje USING latin1) AS BINARY) USING utf8mb4)
  WHERE mensaje LIKE '%Ã%';

UPDATE MANTENIMIENTO_CAMION
  SET tipo_mantenimiento = CONVERT(CAST(CONVERT(tipo_mantenimiento USING latin1) AS BINARY) USING utf8mb4)
  WHERE tipo_mantenimiento LIKE '%Ã%';
UPDATE MANTENIMIENTO_CAMION
  SET descripcion = CONVERT(CAST(CONVERT(descripcion USING latin1) AS BINARY) USING utf8mb4)
  WHERE descripcion LIKE '%Ã%';

UPDATE MANTENIMIENTO_MAQUINARIA
  SET tipo_mantenimiento = CONVERT(CAST(CONVERT(tipo_mantenimiento USING latin1) AS BINARY) USING utf8mb4)
  WHERE tipo_mantenimiento LIKE '%Ã%';
UPDATE MANTENIMIENTO_MAQUINARIA
  SET descripcion = CONVERT(CAST(CONVERT(descripcion USING latin1) AS BINARY) USING utf8mb4)
  WHERE descripcion LIKE '%Ã%';

UPDATE TRASLADO_MAQUINARIA
  SET observaciones = CONVERT(CAST(CONVERT(observaciones USING latin1) AS BINARY) USING utf8mb4)
  WHERE observaciones LIKE '%Ã%';

UPDATE CONSULTA_PUBLICA
  SET nombre = CONVERT(CAST(CONVERT(nombre USING latin1) AS BINARY) USING utf8mb4)
  WHERE nombre LIKE '%Ã%';
UPDATE CONSULTA_PUBLICA
  SET mensaje = CONVERT(CAST(CONVERT(mensaje USING latin1) AS BINARY) USING utf8mb4)
  WHERE mensaje LIKE '%Ã%';
UPDATE CONSULTA_PUBLICA
  SET respuesta_admin = CONVERT(CAST(CONVERT(respuesta_admin USING latin1) AS BINARY) USING utf8mb4)
  WHERE respuesta_admin LIKE '%Ã%';

UPDATE SOLICITUD_RETIRO
  SET direccion = CONVERT(CAST(CONVERT(direccion USING latin1) AS BINARY) USING utf8mb4)
  WHERE direccion LIKE '%Ã%';
UPDATE SOLICITUD_RETIRO
  SET descripcion = CONVERT(CAST(CONVERT(descripcion USING latin1) AS BINARY) USING utf8mb4)
  WHERE descripcion LIKE '%Ã%';
UPDATE SOLICITUD_RETIRO
  SET respuesta_admin = CONVERT(CAST(CONVERT(respuesta_admin USING latin1) AS BINARY) USING utf8mb4)
  WHERE respuesta_admin LIKE '%Ã%';

COMMIT;

-- Verificacion: si despues de correr esto cualquiera de estas consultas
-- devuelve filas, es que ese campo tenia el patron pero en una forma que
-- esta UPDATE no supo resolver (poco probable, pero por las dudas).
SELECT 'USUARIO.nombre_completo' AS campo, COUNT(*) AS filas_con_patron_restante FROM USUARIO WHERE nombre_completo LIKE '%Ã%'
UNION ALL SELECT 'RUTA.nombre_zona', COUNT(*) FROM RUTA WHERE nombre_zona LIKE '%Ã%'
UNION ALL SELECT 'RUTA.frecuencia', COUNT(*) FROM RUTA WHERE frecuencia LIKE '%Ã%'
UNION ALL SELECT 'CONTENEDOR.direccion', COUNT(*) FROM CONTENEDOR WHERE direccion LIKE '%Ã%'
UNION ALL SELECT 'INCIDENCIA.descripcion', COUNT(*) FROM INCIDENCIA WHERE descripcion LIKE '%Ã%'
UNION ALL SELECT 'AUDITORIA.accion', COUNT(*) FROM AUDITORIA WHERE accion LIKE '%Ã%'
UNION ALL SELECT 'CONSULTA_PUBLICA.mensaje', COUNT(*) FROM CONSULTA_PUBLICA WHERE mensaje LIKE '%Ã%';
