-- =====================================================================
-- datos_prueba.sql — Datos de prueba para SiGeRU
--
-- Pensado para cargarse DESPUÉS de sigeru_schema.sql (con Docker se
-- ejecuta solo, en orden, la primera vez que se levanta el contenedor
-- de base de datos — ver docker-compose.yml). Para cargarlo a mano:
--
--   mysql -u root sigeru < sql/sigeru_schema.sql
--   mysql -u root sigeru < sql/datos_prueba.sql
--
-- Todos los usuarios de prueba tienen la contraseña: test1234
-- (el hash de abajo es bcrypt de esa contraseña, generado con la misma
-- password_hash() que usa la aplicación).
-- =====================================================================

-- ---- Usuarios: 2 Administradores, 4 Operarios ----
INSERT INTO USUARIO (ci, contrasena, email, nombre_completo, tipo_usuario) VALUES
('10000001', '$2y$12$Ula9EjR7y6NdQfGfHJxvSeZGS/AgXH9tDd8jG/nhtFwSkzXN7.fsS', 'admin1@sigeru.uy', 'Lucía Fernández', 'ADMINISTRADOR'),
('10000002', '$2y$12$Ula9EjR7y6NdQfGfHJxvSeZGS/AgXH9tDd8jG/nhtFwSkzXN7.fsS', 'admin2@sigeru.uy', 'Martín Sosa', 'ADMINISTRADOR'),
('20000001', '$2y$12$Ula9EjR7y6NdQfGfHJxvSeZGS/AgXH9tDd8jG/nhtFwSkzXN7.fsS', 'operario1@sigeru.uy', 'Pablo Techera', 'OPERARIO'),
('20000002', '$2y$12$Ula9EjR7y6NdQfGfHJxvSeZGS/AgXH9tDd8jG/nhtFwSkzXN7.fsS', 'operario2@sigeru.uy', 'Rosana Ibarra', 'OPERARIO'),
('20000003', '$2y$12$Ula9EjR7y6NdQfGfHJxvSeZGS/AgXH9tDd8jG/nhtFwSkzXN7.fsS', 'operario3@sigeru.uy', 'Gonzalo Pereyra', 'OPERARIO'),
('20000004', '$2y$12$Ula9EjR7y6NdQfGfHJxvSeZGS/AgXH9tDd8jG/nhtFwSkzXN7.fsS', 'operario4@sigeru.uy', 'Valentina Acosta', 'OPERARIO');

INSERT INTO ADMINISTRADOR (ci_usuario) VALUES ('10000001'), ('10000002');

INSERT INTO OPERARIO (cargo, ci_usuario) VALUES
('Chofer', '20000001'),
('Recolector', '20000002'),
('Recolector', '20000003'),
('Chofer', '20000004');

INSERT INTO USUARIO_TELEFONO (ci_usuario, telefono) VALUES
('10000001', '099100001'),
('20000001', '099200001'),
('20000001', '26002001'),
('20000002', '099200002');

-- ---- Rutas y cuadrillas ----
INSERT INTO RUTA (nombre_zona, frecuencia) VALUES
('Zona Centro', 'diaria'),
('Zona Cordón', 'lunes, miércoles y viernes'),
('Zona Pocitos', 'diaria');

INSERT INTO CUADRILLA (nombre_cuadrilla, cantidad_integrantes) VALUES
('Cuadrilla A', 2),
('Cuadrilla B', 2);

INSERT INTO CUADRILLA_RUTA_HABITUAL (id_cuadrilla, id_ruta) VALUES
(1, 1), (1, 2),
(2, 3);

-- ---- Contenedores ----
INSERT INTO CONTENEDOR (coordenadas, estado, nivel_llenado, id_ruta) VALUES
('-34.9058,-56.1913', 'OPERATIVO', 40, 1),
('-34.9070,-56.1950', 'OPERATIVO', 65, 1),
('-34.9105,-56.1780', 'DESBORDADO', 95, 2),
('-34.9012,-56.1620', 'OPERATIVO', 20, 3),
('-34.9200,-56.1500', 'FUERA_DE_SERVICIO', 0, NULL),
('-34.9150,-56.1650', 'OPERATIVO', 55, 3);

INSERT INTO CONTENEDOR_TIPO_RESIDUO (id_contenedor, tipo_residuo) VALUES
(1, 'organico'), (1, 'plastico'),
(2, 'organico'),
(3, 'organico'), (3, 'vidrio'),
(4, 'plastico'), (4, 'papel'),
(6, 'organico');

-- ---- Camiones ----
INSERT INTO CAMION (matricula, marca, modelo, capacidad_carga, estado, kilometraje, fecha_ultimo_mantenimiento) VALUES
('SAB1234', 'Volkswagen', 'Constellation', 8000, 'DISPONIBLE', 45000, '2026-06-15'),
('SBC5678', 'Iveco', 'Tector', 6000, 'EN_RUTA', 62000, '2026-07-01'),
('SCD9012', 'Mercedes-Benz', 'Atego', 7500, 'EN_MANTENIMIENTO', 88000, '2026-08-20');

-- ---- Instalaciones ----
INSERT INTO INSTALACION (nombre, coordenadas, capacidad_maxima, residuos_aceptados, tipo_instalacion) VALUES
('Centro de Acopio Norte', '-34.8500,-56.1200', 5000, 'organico, plastico, papel, vidrio', 'CENTRO_ACOPIO'),
('Vertedero Municipal', '-34.9500,-56.2200', 20000, 'organico, restos de poda, escombros', 'VERTEDERO');

-- ---- Maquinaria y repuestos ----
INSERT INTO REPUESTO (nombre, tipo, stock, estado) VALUES
('Correa transportadora', 'Mecánico', 4, 'DISPONIBLE'),
('Filtro hidráulico', 'Hidráulico', 10, 'DISPONIBLE'),
('Cuchilla trituradora', 'Mecánico', 0, 'AGOTADO');

INSERT INTO MAQUINARIA (nombre, tipo, estado, id_centro) VALUES
('Compactadora 1', 'Compactadora', 'OPERATIVA', 1),
('Cinta clasificadora', 'Cinta transportadora', 'EN_MANTENIMIENTO', 1);

INSERT INTO MAQUINARIA_REPUESTO (id_maquinaria, id_repuesto) VALUES
(1, 2),
(2, 1), (2, 3);

-- ---- Vecinos e incidencias (distintos estados del Diagrama de Estados) ----
INSERT INTO VECINO (email) VALUES
('vecino1@correo.com'),
('vecino2@correo.com'),
('vecino3@correo.com');

INSERT INTO INCIDENCIA (descripcion, fecha_registro, fecha_resolucion, fecha_escalacion, estado, tipo_incidencia, id_contenedor, id_vecino, id_cuadrilla) VALUES
('El contenedor de la esquina está desbordado hace dos días.', '2026-09-01 09:15:00', NULL, NULL, 'PENDIENTE', 'Desborde', 3, 1, NULL),
('Contenedor con la tapa rota.', '2026-08-28 14:30:00', NULL, NULL, 'ASIGNADA', 'Dañado', 2, 2, 1),
('Mal olor persistente, parece que no se recolectó esta semana.', '2026-08-20 08:00:00', NULL, NULL, 'EN_PROCESO', 'Otro', 4, 3, 2),
('Contenedor caído sobre la vereda.', '2026-08-10 11:00:00', '2026-08-11 16:45:00', NULL, 'RESUELTA', 'Dañado', 6, 1, 1);

-- ---- Asignación diaria + operarios asignados ----
INSERT INTO ASIGNACION_DIARIA (fecha, turno, id_administrador, id_cuadrilla, id_ruta, id_camion) VALUES
('2026-09-07', 'MANANA', 1, 1, 1, 1),
('2026-09-07', 'TARDE', 1, 2, 3, 2);

INSERT INTO ASIGNACION_OPERARIO (id_asignacion, id_operario, rol_en_asignacion) VALUES
(1, 1, 'Chofer'),
(1, 2, 'Recolector'),
(2, 4, 'Chofer'),
(2, 3, 'Recolector');

-- ---- Descargas y mantenimiento ----
INSERT INTO DESCARGA (fecha_descarga, hora_descarga, peso, id_camion, id_centro) VALUES
('2026-09-05', '11:20:00', 1250.50, 1, 2),
('2026-09-06', '10:05:00', 980.00, 2, 1);

INSERT INTO MANTENIMIENTO_CAMION (fecha, kilometraje, tipo_mantenimiento, descripcion, id_camion) VALUES
('2026-08-20', 88000, 'Service completo', 'Cambio de aceite, filtros y revisión de frenos.', 3);

-- ---- Consultas públicas (RN21) ----
INSERT INTO CONSULTA_PUBLICA (nombre, email, mensaje, estado) VALUES
('Ana Gómez', 'ana.gomez@correo.com', '¿Qué días pasa el camión por Zona Pocitos?', 'PENDIENTE'),
('Carlos Núñez', 'carlos.nunez@correo.com', 'Quería felicitar al equipo, mejoró mucho la frecuencia de recolección.', 'RESPONDIDA');
