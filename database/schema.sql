-- =============================================================================
-- SiGeRU - Sistema de Gestión de Residuos Urbanos
-- Base de Datos: schema.sql (Estructura limpia sin datos)
-- =============================================================================

CREATE DATABASE IF NOT EXISTS sigeru
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sigeru;

-- Deshabilitar chequeo de claves foráneas para recreación limpia
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS repara;
DROP TABLE IF EXISTS estaciona;
DROP TABLE IF EXISTS mantenimiento;
DROP TABLE IF EXISTS garaje;
DROP TABLE IF EXISTS enviares;
DROP TABLE IF EXISTS opera;
DROP TABLE IF EXISTS compone;
DROP TABLE IF EXISTS atiende;
DROP TABLE IF EXISTS resuelve;
DROP TABLE IF EXISTS incidencia;
DROP TABLE IF EXISTS contenedorcomunitario;
DROP TABLE IF EXISTS contenedordomiciliario;
DROP TABLE IF EXISTS contenedor;
DROP TABLE IF EXISTS recorrido;
DROP TABLE IF EXISTS ruta;
DROP TABLE IF EXISTS vehiculo;
DROP TABLE IF EXISTS cuadrilla;
DROP TABLE IF EXISTS usuario;
DROP TABLE IF EXISTS maquinaria;
DROP TABLE IF EXISTS vertedero;
DROP TABLE IF EXISTS centro;

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- 1. TABLA: centro (Plantas de tratamiento y centros de acopio)
-- -----------------------------------------------------------------------------
CREATE TABLE centro (
    idCentro INT AUTO_INCREMENT,
    ubiCentro VARCHAR(150) NOT NULL,
    tipoCentro VARCHAR(30) NOT NULL,
    capCentro INT NOT NULL,

    PRIMARY KEY (idCentro),

    CONSTRAINT chk_centro_tipo
        CHECK (tipoCentro IN ('comun', 'aceite', 'electronicos', 'reciclables')),

    CONSTRAINT chk_centro_capacidad
        CHECK (capCentro > 0)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 2. TABLA: vertedero (Vertederos y disposiciones finales)
-- -----------------------------------------------------------------------------
CREATE TABLE vertedero (
    idVertedero INT AUTO_INCREMENT,
    ubicacionVertedero VARCHAR(150) NOT NULL,

    PRIMARY KEY (idVertedero)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 3. TABLA: maquinaria (Maquinaria en plantas y centros)
-- -----------------------------------------------------------------------------
CREATE TABLE maquinaria (
    idMaq INT AUTO_INCREMENT,
    idCentro INT NOT NULL,
    propositoMaq VARCHAR(200) NOT NULL,
    capMaq DECIMAL(10,2) NOT NULL,
    marcaMaq VARCHAR(50) NOT NULL,
    modeloMaq VARCHAR(50) NOT NULL,
    numSerie VARCHAR(50) NOT NULL,

    PRIMARY KEY (idMaq),

    CONSTRAINT uq_maquinaria_serie
        UNIQUE (numSerie),

    CONSTRAINT fk_maquinaria_centro
        FOREIGN KEY (idCentro)
        REFERENCES centro(idCentro)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT chk_maquinaria_capacidad
        CHECK (capMaq >= 0)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 4. TABLA: usuario (Cuentas del personal y ciudadanos)
-- -----------------------------------------------------------------------------
CREATE TABLE usuario (
    idUsu INT AUTO_INCREMENT,
    priNom VARCHAR(50) NOT NULL,
    telUsu VARCHAR(20),
    fchNac DATE,
    email VARCHAR(100) NOT NULL,
    passwordHash VARCHAR(255) NOT NULL,
    rol VARCHAR(30) NOT NULL,
    estUsu VARCHAR(20) NOT NULL,
    idCentro INT,

    PRIMARY KEY (idUsu),

    CONSTRAINT uq_usuario_email
        UNIQUE (email),

    CONSTRAINT fk_usuario_centro
        FOREIGN KEY (idCentro)
        REFERENCES centro(idCentro)
        ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT chk_usuario_rol
        CHECK (rol IN ('operario', 'municipal', 'cuadrilla', 'administrador', 'vecino')),

    CONSTRAINT chk_usuario_estado
        CHECK (estUsu IN ('activo', 'inactivo'))
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 5. TABLA: cuadrilla (Equipos de recolección: chofer + peón)
-- -----------------------------------------------------------------------------
CREATE TABLE cuadrilla (
    idCuadrilla INT AUTO_INCREMENT,
    idChofer INT NOT NULL,
    idPeon INT NOT NULL,

    PRIMARY KEY (idCuadrilla),

    CONSTRAINT fk_cuadrilla_chofer
        FOREIGN KEY (idChofer)
        REFERENCES usuario(idUsu)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_cuadrilla_peon
        FOREIGN KEY (idPeon)
        REFERENCES usuario(idUsu)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT uq_cuadrilla_chofer
        UNIQUE (idChofer),

    CONSTRAINT uq_cuadrilla_peon
        UNIQUE (idPeon)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 6. TABLA: vehiculo (Flota de camiones recolectores y utilitarios)
-- -----------------------------------------------------------------------------
CREATE TABLE vehiculo (
    idVehi INT AUTO_INCREMENT,
    tipoVehi VARCHAR(30) NOT NULL,
    matriculaVehi VARCHAR(10) NOT NULL,
    marcaVehi VARCHAR(50) NOT NULL,
    modeloVehi VARCHAR(50) NOT NULL,
    capVehi DECIMAL(10,2) NOT NULL,
    estVehi VARCHAR(20) NOT NULL,

    PRIMARY KEY (idVehi),

    CONSTRAINT uq_vehiculo_matricula
        UNIQUE (matriculaVehi),

    CONSTRAINT chk_vehiculo_tipo
        CHECK (tipoVehi IN ('comunitario', 'intradomiciliario', 'limpieza', 'centroAcopio')),

    CONSTRAINT chk_vehiculo_estado
        CHECK (estVehi IN ('disponible', 'en uso', 'roto')),

    CONSTRAINT chk_vehiculo_capacidad
        CHECK (capVehi > 0)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 7. TABLA: ruta (Rutas de recolección asignadas)
-- -----------------------------------------------------------------------------
CREATE TABLE ruta (
    idRuta INT AUTO_INCREMENT,
    frecuencia VARCHAR(100) NOT NULL,
    idCentro INT NOT NULL,

    PRIMARY KEY (idRuta),

    CONSTRAINT fk_ruta_centro
        FOREIGN KEY (idCentro)
        REFERENCES centro(idCentro)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 8. TABLA: recorrido (Ejecución de una ruta en una fecha específica)
-- -----------------------------------------------------------------------------
CREATE TABLE recorrido (
    idRecorrido INT AUTO_INCREMENT,
    idVehi INT NOT NULL,
    idRuta INT NOT NULL,
    fechaRec DATE NOT NULL,

    PRIMARY KEY (idRecorrido),

    CONSTRAINT fk_recorrido_vehiculo
        FOREIGN KEY (idVehi)
        REFERENCES vehiculo(idVehi)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_recorrido_ruta
        FOREIGN KEY (idRuta)
        REFERENCES ruta(idRuta)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 9. TABLA: contenedor (Contenedores de residuos)
-- -----------------------------------------------------------------------------
CREATE TABLE contenedor (
    idCon INT AUTO_INCREMENT,
    capacidad DECIMAL(10,2) NOT NULL,
    calle VARCHAR(100) NOT NULL,
    esquina VARCHAR(100) NOT NULL,
    zona VARCHAR(30) NOT NULL,
    estCon VARCHAR(20) NOT NULL,
    tipoCon VARCHAR(30) NOT NULL,
    repuesto BOOLEAN NOT NULL DEFAULT FALSE,

    PRIMARY KEY (idCon),

    CONSTRAINT chk_contenedor_capacidad
        CHECK (capacidad > 0),

    CONSTRAINT chk_contenedor_zona
        CHECK (zona IN (
            'Municipio A', 'Municipio B', 'Municipio C', 'Municipio CH',
            'Municipio D', 'Municipio E', 'Municipio F', 'Municipio G'
        )),

    CONSTRAINT chk_contenedor_estado
        CHECK (estCon IN ('activo', 'inactivo')),

    CONSTRAINT chk_contenedor_tipo
        CHECK (tipoCon IN ('comun', 'aceite', 'electronicos', 'reciclables'))
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 10. TABLA: contenedordomiciliario (Especialización residencial)
-- -----------------------------------------------------------------------------
CREATE TABLE contenedordomiciliario (
    idCon INT NOT NULL,
    numPuerta VARCHAR(30) NOT NULL,

    PRIMARY KEY (idCon),

    CONSTRAINT fk_cd_contenedor
        FOREIGN KEY (idCon)
        REFERENCES contenedor(idCon)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 11. TABLA: contenedorcomunitario (Especialización pública con coordenadas)
-- -----------------------------------------------------------------------------
CREATE TABLE contenedorcomunitario (
    idCon INT NOT NULL,
    latitud DECIMAL(10,7) NOT NULL,
    longitud DECIMAL(10,7) NOT NULL,

    PRIMARY KEY (idCon),

    CONSTRAINT fk_cc_contenedor
        FOREIGN KEY (idCon)
        REFERENCES contenedor(idCon)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT chk_cc_latitud
        CHECK (latitud BETWEEN -90 AND 90),

    CONSTRAINT chk_cc_longitud
        CHECK (longitud BETWEEN -180 AND 180)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 12. TABLA: incidencia (Reportes de fallas o problemas en contenedores)
-- -----------------------------------------------------------------------------
CREATE TABLE incidencia (
    idInci INT AUTO_INCREMENT,
    fchaInci DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    idCon INT NOT NULL,
    tipoInci VARCHAR(30) NOT NULL,
    descInci VARCHAR(200) NOT NULL,
    cedHashInci CHAR(64) NOT NULL,
    prioridad VARCHAR(20) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'en curso',

    PRIMARY KEY (idInci),

    CONSTRAINT fk_incidencia_contenedor
        FOREIGN KEY (idCon)
        REFERENCES contenedor(idCon)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT chk_incidencia_tipo
        CHECK (tipoInci IN ('roto', 'incendiado', 'desbordado', 'basura alrededor')),

    CONSTRAINT chk_incidencia_prioridad
        CHECK (prioridad IN ('alta', 'media', 'baja')),

    CONSTRAINT chk_incidencia_estado
        CHECK (estado IN ('resuelto', 'en curso', 'descartado')),

    CONSTRAINT chk_incidencia_descripcion
        CHECK (CHAR_LENGTH(descInci) <= 200)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 13. TABLA: resuelve (Historial de resolución por funcionarios)
-- -----------------------------------------------------------------------------
CREATE TABLE resuelve (
    idResolucion INT AUTO_INCREMENT,
    idInci INT NOT NULL,
    idUsu INT NOT NULL,
    fchIntento DATETIME NOT NULL,
    descEstado VARCHAR(200) NOT NULL,
    estIntento VARCHAR(20) NOT NULL,

    PRIMARY KEY (idResolucion),

    CONSTRAINT fk_resuelve_incidencia
        FOREIGN KEY (idInci)
        REFERENCES incidencia(idInci)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_resuelve_usuario
        FOREIGN KEY (idUsu)
        REFERENCES usuario(idUsu)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT chk_resuelve_estado
        CHECK (estIntento IN ('abierto', 'en proceso', 'cerrado')),

    CONSTRAINT chk_resuelve_descripcion
        CHECK (CHAR_LENGTH(descEstado) <= 200)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 14. TABLA: atiende (Atención de incidencias por vehículos de recolección)
-- -----------------------------------------------------------------------------
CREATE TABLE atiende (
    idAtencion INT AUTO_INCREMENT,
    idInci INT NOT NULL,
    idVehi INT NOT NULL,
    fchAtencion DATETIME NOT NULL,

    PRIMARY KEY (idAtencion),

    CONSTRAINT fk_atiende_incidencia
        FOREIGN KEY (idInci)
        REFERENCES incidencia(idInci)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_atiende_vehiculo
        FOREIGN KEY (idVehi)
        REFERENCES vehiculo(idVehi)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 15. TABLA: compone (Secuencia ordenada de contenedores en una ruta)
-- -----------------------------------------------------------------------------
CREATE TABLE compone (
    idComposicion INT AUTO_INCREMENT,
    idRuta INT NOT NULL,
    idCon INT NOT NULL,
    orden INT NOT NULL,

    PRIMARY KEY (idComposicion),

    CONSTRAINT fk_compone_ruta
        FOREIGN KEY (idRuta)
        REFERENCES ruta(idRuta)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_compone_contenedor
        FOREIGN KEY (idCon)
        REFERENCES contenedor(idCon)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT uq_compone_orden
        UNIQUE (idRuta, orden),

    CONSTRAINT uq_compone_contenedor
        UNIQUE (idCon),

    CONSTRAINT chk_compone_orden
        CHECK (orden > 0)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 16. TABLA: opera (Asignación diaria de cuadrilla a un vehículo)
-- -----------------------------------------------------------------------------
CREATE TABLE opera (
    idOperacion INT AUTO_INCREMENT,
    idVehi INT NOT NULL,
    idCuadrilla INT NOT NULL,
    fchOperacion DATE NOT NULL,

    PRIMARY KEY (idOperacion),

    CONSTRAINT fk_opera_vehiculo
        FOREIGN KEY (idVehi)
        REFERENCES vehiculo(idVehi)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_opera_cuadrilla
        FOREIGN KEY (idCuadrilla)
        REFERENCES cuadrilla(idCuadrilla)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 17. TABLA: enviares (Transporte de residuos desde centro a vertedero)
-- -----------------------------------------------------------------------------
CREATE TABLE enviares (
    idEnvio INT AUTO_INCREMENT,
    idCentro INT NOT NULL,
    idVertedero INT NOT NULL,
    fchVertido DATE NOT NULL,

    PRIMARY KEY (idEnvio),

    CONSTRAINT fk_enviares_centro
        FOREIGN KEY (idCentro)
        REFERENCES centro(idCentro)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_enviares_vertedero
        FOREIGN KEY (idVertedero)
        REFERENCES vertedero(idVertedero)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 18. TABLA: garaje (Estacionamiento de flota municipal)
-- -----------------------------------------------------------------------------
CREATE TABLE garaje (
    idGaraje INT AUTO_INCREMENT,
    ubiGaraje VARCHAR(150) NOT NULL,

    PRIMARY KEY (idGaraje)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 19. TABLA: mantenimiento (Talleres de reparación de vehículos)
-- -----------------------------------------------------------------------------
CREATE TABLE mantenimiento (
    idMant INT AUTO_INCREMENT,
    ubiMant VARCHAR(150) NOT NULL,

    PRIMARY KEY (idMant)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 20. TABLA: estaciona (Registro de estacionamiento en garaje)
-- -----------------------------------------------------------------------------
CREATE TABLE estaciona (
    idEstacion INT AUTO_INCREMENT,
    idGaraje INT NOT NULL,
    idVehi INT NOT NULL,
    horaEstacionamiento DATETIME NOT NULL,

    PRIMARY KEY (idEstacion),

    CONSTRAINT fk_estaciona_garaje
        FOREIGN KEY (idGaraje)
        REFERENCES garaje(idGaraje)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_estaciona_vehiculo
        FOREIGN KEY (idVehi)
        REFERENCES vehiculo(idVehi)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- 21. TABLA: repara (Registro de intervenciones mecánicas)
-- -----------------------------------------------------------------------------
CREATE TABLE repara (
    idReparacion INT AUTO_INCREMENT,
    idMant INT NOT NULL,
    idVehi INT NOT NULL,
    horaIngreso DATETIME NOT NULL,
    horaSalida DATETIME,
    estadoReparacion VARCHAR(30) NOT NULL,

    PRIMARY KEY (idReparacion),

    CONSTRAINT fk_repara_mantenimiento
        FOREIGN KEY (idMant)
        REFERENCES mantenimiento(idMant)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_repara_vehiculo
        FOREIGN KEY (idVehi)
        REFERENCES vehiculo(idVehi)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT chk_repara_fechas
        CHECK (horaSalida IS NULL OR horaSalida >= horaIngreso)
) ENGINE=InnoDB;
