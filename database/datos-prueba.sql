USE sigeru;

-- Cuenta para la demostración: admin@sigeru.local / admin123
INSERT INTO usuario (priNom, telUsu, email, hash, rol, estUsu, idCentro) VALUES
    ('Administrador SiGeRU', '099000001', 'admin@sigeru.local',
     '$2y$12$B0n37IC9bDBgFdiXe/87geIavyGmy6Ck1ZWEiLY0HNY0d8XD95bCm',
     'administrador', 'activo', NULL);

INSERT INTO contenedor (capacidad, calle, esquina, zona, estCon, tipoCon, repuesto) VALUES
    (1100, '18 de Julio', 'Ejido', 'Municipio B', 'activo', 'comun', FALSE),
    (1100, 'Colonia', 'Eduardo Acevedo', 'Municipio B', 'activo', 'comun', FALSE),
    (240, 'Benito Blanco', 'Pagola', 'Municipio CH', 'activo', 'vidrio', FALSE);

INSERT INTO camion (tipoCamion, matricula, marca, modelo, capacidad, estCamion, repuesto) VALUES
    ('comunitario', 'STP1234', 'Volkswagen', 'Constellation', 12000, 'disponible', FALSE),
    ('limpieza', 'STP5678', 'Mercedes-Benz', 'Atego', 9000, 'en uso', FALSE);

INSERT INTO incidencia
    (idCon, tipoInci, descInci, nomReporte, cedHashInci, telReporte, prioridad)
VALUES
    (2, 'desbordado', 'El contenedor supera su capacidad.', 'María Pérez',
     SHA2('45678901', 256), '099123456', 'media');