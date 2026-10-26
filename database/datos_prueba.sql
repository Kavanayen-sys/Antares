-- =============================================================================
-- SiGeRU - Sistema de Gestión de Residuos Urbanos
-- Base de Datos: datos_prueba.sql (Datos de prueba coherentes y realistas)
-- =============================================================================

USE sigeru;

SET FOREIGN_KEY_CHECKS = 0;

-- Limpiar tablas antes de insertar
TRUNCATE TABLE repara;
TRUNCATE TABLE estaciona;
TRUNCATE TABLE mantenimiento;
TRUNCATE TABLE garaje;
TRUNCATE TABLE enviares;
TRUNCATE TABLE opera;
TRUNCATE TABLE compone;
TRUNCATE TABLE atiende;
TRUNCATE TABLE resuelve;
TRUNCATE TABLE incidencia;
TRUNCATE TABLE contenedorcomunitario;
TRUNCATE TABLE contenedordomiciliario;
TRUNCATE TABLE contenedor;
TRUNCATE TABLE recorrido;
TRUNCATE TABLE ruta;
TRUNCATE TABLE vehiculo;
TRUNCATE TABLE cuadrilla;
TRUNCATE TABLE usuario;
TRUNCATE TABLE maquinaria;
TRUNCATE TABLE vertedero;
TRUNCATE TABLE centro;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- 1. CENTROS DE TRATAMIENTO Y ACOPIO
-- -----------------------------------------------------------------------------
INSERT INTO centro (idCentro, ubiCentro, tipoCentro, capCentro) VALUES
(1, 'Camino Corrales 2800, Villa Española', 'comun', 50000),
(2, 'Av. 8 de Octubre 3400, La Blanqueada', 'reciclables', 30000),
(3, 'Bulevar Artigas 4100, Prado', 'electronicos', 15000),
(4, 'Ruta 8 km 17, Manga', 'aceite', 10000);

-- -----------------------------------------------------------------------------
-- 2. VERTEDERO MUNICIPAL
-- -----------------------------------------------------------------------------
INSERT INTO vertedero (idVertedero, ubicacionVertedero) VALUES
(1, 'Felipe Cardoso s/n - Disposición Final Usina 6, Bañados de Carrasco');

-- -----------------------------------------------------------------------------
-- 3. MAQUINARIA EN PLANTAS DE TRATAMIENTO
-- -----------------------------------------------------------------------------
INSERT INTO maquinaria (idMaq, idCentro, propositoMaq, capMaq, marcaMaq, modeloMaq, numSerie) VALUES
(1, 1, 'Prensa compactadora hidráulica estacionaria', 5000.00, 'Macpresse', 'MAC 108/1', 'SN-2023-0145'),
(2, 2, 'Separador magnético de metales ferrosos', 2500.00, 'Eriez', 'SE-7000', 'SN-2022-0891'),
(3, 2, 'Triturador de papel, cartón y plásticos reciclables', 4000.00, 'Vecoplan', 'VAZ 1300', 'SN-2024-0312'),
(4, 4, 'Bomba centrífuga de trasvase de aceites vegetales', 1500.00, 'Flygt', 'N-3153', 'SN-2021-0420');

-- -----------------------------------------------------------------------------
-- 4. USUARIOS (Contraseña de todos: admin123)
-- Hash bcrypt: $2y$10$fPgqe4UAFA6lmZiqhTHySeNdTBHh512m0w44sGZdGBKvtuY8vGVeS
-- -----------------------------------------------------------------------------
INSERT INTO usuario (idUsu, priNom, telUsu, fchNac, email, passwordHash, rol, estUsu, idCentro) VALUES
(1, 'Administrador SiGeRU', '099111222', '1985-04-12', 'admin@sigeru.local', '$2y$10$fPgqe4UAFA6lmZiqhTHySeNdTBHh512m0w44sGZdGBKvtuY8vGVeS', 'administrador', 'activo', NULL),
(2, 'Funcionario Municipal', '099333444', '1990-08-25', 'municipal@sigeru.local', '$2y$10$fPgqe4UAFA6lmZiqhTHySeNdTBHh512m0w44sGZdGBKvtuY8vGVeS', 'municipal', 'activo', NULL),
(3, 'Jorge Chofer', '098555666', '1988-02-14', 'chofer@sigeru.local', '$2y$10$fPgqe4UAFA6lmZiqhTHySeNdTBHh512m0w44sGZdGBKvtuY8vGVeS', 'cuadrilla', 'activo', NULL),
(4, 'Carlos Peón', '098777888', '1995-11-03', 'peon@sigeru.local', '$2y$10$fPgqe4UAFA6lmZiqhTHySeNdTBHh512m0w44sGZdGBKvtuY8vGVeS', 'cuadrilla', 'activo', NULL),
(5, 'Roberto Operario', '099999000', '1982-06-30', 'operario@sigeru.local', '$2y$10$fPgqe4UAFA6lmZiqhTHySeNdTBHh512m0w44sGZdGBKvtuY8vGVeS', 'operario', 'activo', 1),
(6, 'Mario Chofer 2', '091222333', '1989-09-17', 'chofer2@sigeru.local', '$2y$10$fPgqe4UAFA6lmZiqhTHySeNdTBHh512m0w44sGZdGBKvtuY8vGVeS', 'cuadrilla', 'activo', NULL),
(7, 'Esteban Peón 2', '091444555', '1993-01-22', 'peon2@sigeru.local', '$2y$10$fPgqe4UAFA6lmZiqhTHySeNdTBHh512m0w44sGZdGBKvtuY8vGVeS', 'cuadrilla', 'activo', NULL),
(8, 'Vecino Ciudadano', '092888999', '1998-05-10', 'vecino@sigeru.local', '$2y$10$fPgqe4UAFA6lmZiqhTHySeNdTBHh512m0w44sGZdGBKvtuY8vGVeS', 'vecino', 'activo', NULL);

-- -----------------------------------------------------------------------------
-- 5. CUADRILLAS DE RECOLECCIÓN (Chofer + Peón)
-- -----------------------------------------------------------------------------
INSERT INTO cuadrilla (idCuadrilla, idChofer, idPeon) VALUES
(1, 3, 4),  -- Cuadrilla Alfa: Jorge (chofer) y Carlos (peón)
(2, 6, 7);  -- Cuadrilla Beta: Mario (chofer) y Esteban (peón)

-- -----------------------------------------------------------------------------
-- 6. VEHÍCULOS DE LA FLOTA MUNICIPAL
-- -----------------------------------------------------------------------------
INSERT INTO vehiculo (idVehi, tipoVehi, matriculaVehi, marcaVehi, modeloVehi, capVehi, estVehi) VALUES
(1, 'comunitario', 'STX-1042', 'Mercedes-Benz', 'Atego 1729 Recolector', 15000.00, 'en uso'),
(2, 'comunitario', 'STX-1043', 'Scania', 'P280 Compactador', 18000.00, 'disponible'),
(3, 'intradomiciliario', 'SMI-2340', 'Iveco', 'Daily 70C17 Caja Abierta', 7000.00, 'disponible'),
(4, 'limpieza', 'SOM-5511', 'Volkswagen', 'Delivery 9.170 Lavador', 6000.00, 'disponible'),
(5, 'centroAcopio', 'STX-3301', 'Scania', 'R450 Volqueta Batea', 28000.00, 'roto');

-- -----------------------------------------------------------------------------
-- 7. RUTAS DE RECOLECCIÓN
-- -----------------------------------------------------------------------------
INSERT INTO ruta (idRuta, frecuencia, idCentro) VALUES
(1, 'Diaria - Municipio B (Centro)', 1),
(2, 'Nocturna - Municipio CH (Pocitos)', 2),
(3, 'Lunes a Viernes - Municipio C', 1);

-- -----------------------------------------------------------------------------
-- 8. CONTENEDORES Y COORDENADAS (Montevideo, Uruguay)
-- -----------------------------------------------------------------------------
INSERT INTO contenedor (idCon, capacidad, calle, esquina, zona, estCon, tipoCon, repuesto) VALUES
(1, 3200.00, 'Av. 18 de Julio', 'Ejido', 'Municipio B', 'activo', 'comun', 0),
(2, 3200.00, 'Colonia', 'Río Negro', 'Municipio B', 'activo', 'comun', 0),
(3, 3200.00, 'San José', 'Paraguay', 'Municipio B', 'activo', 'comun', 0),
(4, 2400.00, '21 de Setiembre', 'Coronel Mora', 'Municipio CH', 'activo', 'reciclables', 0),
(5, 3200.00, 'Bvar. España', 'Ellauri', 'Municipio CH', 'activo', 'comun', 0),
(6, 3200.00, 'Benito Blanco', 'Guayaquí', 'Municipio CH', 'activo', 'comun', 0),
(7, 1500.00, 'Av. Gral. Rivera', 'Dr. Francisco Soca', 'Municipio CH', 'activo', 'aceite', 0),
(8, 2400.00, 'Av. 8 de Octubre', 'Av. Gral. Garibaldi', 'Municipio C', 'activo', 'reciclables', 0),
(9, 3200.00, 'Av. Gral. Flores', 'Domingo Aramburú', 'Municipio C', 'activo', 'comun', 0),
(10, 1500.00, 'Bvar. Artigas', 'Av. Joaquín Suárez', 'Municipio C', 'activo', 'electronicos', 0);

-- Coordenadas reales geolocalizadas en Montevideo para Leaflet
INSERT INTO contenedorcomunitario (idCon, latitud, longitud) VALUES
(1, -34.9056200, -56.1865200),
(2, -34.9038100, -56.1942100),
(3, -34.9071000, -56.1915000),
(4, -34.9189000, -56.1558000),
(5, -34.9163000, -56.1524000),
(6, -34.9135000, -56.1481000),
(7, -34.9064000, -56.1552000),
(8, -34.8874000, -56.1610000),
(9, -34.8812000, -56.1770000),
(10, -34.8690000, -56.1850000);

-- -----------------------------------------------------------------------------
-- 9. COMPOSICIÓN DE RUTAS (Secuencia de contenedores)
-- -----------------------------------------------------------------------------
INSERT INTO compone (idComposicion, idRuta, idCon, orden) VALUES
-- Ruta 1: Centro y Cordón (Municipio B)
(1, 1, 1, 1),
(2, 1, 2, 2),
(3, 1, 3, 3),
-- Ruta 2: Pocitos y Punta Carretas (Municipio CH)
(4, 2, 4, 1),
(5, 2, 5, 2),
(6, 2, 6, 3),
(7, 2, 7, 4),
-- Ruta 3: La Blanqueada y Goes (Municipio C)
(8, 3, 8, 1),
(9, 3, 9, 2),
(10, 3, 10, 3);

-- -----------------------------------------------------------------------------
-- 10. INCIDENCIAS DE CONTENEDORES
-- -----------------------------------------------------------------------------
INSERT INTO incidencia (idInci, fchaInci, idCon, tipoInci, descInci, cedHashInci, prioridad, estado) VALUES
(1, NOW() - INTERVAL 3 HOUR, 1, 'desbordado', 'Capacidad colmada de bolsas y cajas en la acera comercial.', SHA2('41234567', 256), 'alta', 'en curso'),
(2, NOW() - INTERVAL 6 HOUR, 5, 'roto', 'El pedal de apertura se soltó y la tapa no se puede abrir.', SHA2('39876543', 256), 'media', 'en curso'),
(3, NOW() - INTERVAL 1 DAY, 4, 'basura alrededor', 'Restos de poda acumulados en el lateral del contenedor.', SHA2('48765432', 256), 'baja', 'resuelto'),
(4, NOW() - INTERVAL 2 DAY, 9, 'incendiado', 'Se detectaron cenizas y estructura plástica dañada por fuego.', SHA2('51239874', 256), 'alta', 'resuelto'),
(5, NOW() - INTERVAL 12 HOUR, 2, 'desbordado', 'Desborde de residuos luego de fin de semana.', SHA2('42551122', 256), 'alta', 'en curso');

-- -----------------------------------------------------------------------------
-- 11. OPERACIONES (Asignación Cuadrilla a Vehículo)
-- -----------------------------------------------------------------------------
INSERT INTO opera (idOperacion, idVehi, idCuadrilla, fchOperacion) VALUES
(1, 1, 1, CURDATE()),
(2, 2, 2, CURDATE());

-- -----------------------------------------------------------------------------
-- 12. RECORRIDOS (Ejecución de Rutas y Llegadas a Centros de Acopio)
-- -----------------------------------------------------------------------------
INSERT INTO recorrido (idRecorrido, idVehi, idRuta, fechaRec) VALUES
(1, 1, 1, CURDATE()),
(2, 2, 2, CURDATE()),
(3, 4, 3, CURDATE() - INTERVAL 1 DAY),
(4, 1, 1, CURDATE() - INTERVAL 1 DAY);

-- -----------------------------------------------------------------------------
-- 13. RESOLUCIÓN Y ATENCIÓN DE INCIDENCIAS
-- -----------------------------------------------------------------------------
INSERT INTO resuelve (idResolucion, idInci, idUsu, fchIntento, descEstado, estIntento) VALUES
(1, 3, 2, NOW() - INTERVAL 18 HOUR, 'Cuadrilla de limpieza despachada, vereda despejada.', 'cerrado'),
(2, 4, 1, NOW() - INTERVAL 36 HOUR, 'Contenedor reemplazado por unidad de repuesto.', 'cerrado');

INSERT INTO atiende (idAtencion, idInci, idVehi, fchAtencion) VALUES
(1, 3, 4, NOW() - INTERVAL 18 HOUR),
(2, 4, 1, NOW() - INTERVAL 36 HOUR);

-- -----------------------------------------------------------------------------
-- 14. ENVÍOS DESDE CENTROS A DISPOSICIÓN FINAL (Vertedero)
-- -----------------------------------------------------------------------------
INSERT INTO enviares (idEnvio, idCentro, idVertedero, fchVertido) VALUES
(1, 1, 1, CURDATE() - INTERVAL 1 DAY),
(2, 2, 1, CURDATE() - INTERVAL 2 DAY);
