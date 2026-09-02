CREATE DATABASE IF NOT EXISTS sigeru
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_0900_ai_ci;

USE sigeru;

CREATE TABLE centro (
    idCentro INT AUTO_INCREMENT,
    tipoCentro VARCHAR(30) NOT NULL,
    especialidadCentro VARCHAR(100) NOT NULL,
    capCentro INT NOT NULL,
    PRIMARY KEY (idCentro),
    CONSTRAINT chk_centro_tipo CHECK (tipoCentro IN ('comun','aceite','electronicos','vidrio','plastico')),
    CONSTRAINT chk_centro_capacidad CHECK (capCentro > 0)
) ENGINE=InnoDB;

CREATE TABLE vertedero (
    idVertedero INT AUTO_INCREMENT,
    ubicacionVertedero VARCHAR(150) NOT NULL,
    PRIMARY KEY (idVertedero)
) ENGINE=InnoDB;

CREATE TABLE maquinaria (
    idMaq INT AUTO_INCREMENT,
    idCentro INT NOT NULL,
    propositoMaq VARCHAR(200) NOT NULL,
    capMaq DECIMAL(10,2) NOT NULL,
    marcaMaq VARCHAR(50) NOT NULL,
    modeloMaq VARCHAR(50) NOT NULL,
    numSerie VARCHAR(50) NOT NULL,
    PRIMARY KEY (idMaq),
    CONSTRAINT uq_maquinaria_serie UNIQUE (numSerie),
    CONSTRAINT fk_maquinaria_centro FOREIGN KEY (idCentro) REFERENCES centro(idCentro),
    CONSTRAINT chk_maquinaria_capacidad CHECK (capMaq >= 0),
    CONSTRAINT chk_maquinaria_proposito CHECK (CHAR_LENGTH(propositoMaq) <= 200)
) ENGINE=InnoDB;

CREATE TABLE usuario (
    idUsu INT AUTO_INCREMENT,
    priNom VARCHAR(50) NOT NULL,
    telUsu VARCHAR(20),
    email VARCHAR(100) NOT NULL,
    hash VARCHAR(255) NOT NULL,
    rol VARCHAR(30) NOT NULL,
    estUsu VARCHAR(20) NOT NULL,
    idCentro INT,
    PRIMARY KEY (idUsu),
    CONSTRAINT uq_usuario_email UNIQUE (email),
    CONSTRAINT fk_usuario_centro FOREIGN KEY (idCentro) REFERENCES centro(idCentro),
    CONSTRAINT chk_usuario_rol CHECK (rol IN ('operario','municipal','cuadrilla','administrador','vecino')),
    CONSTRAINT chk_usuario_estado CHECK (estUsu IN ('activo','inactivo'))
) ENGINE=InnoDB;

CREATE TABLE cuadrilla (
    idCuadrilla INT AUTO_INCREMENT,
    horarioCuadrilla INT NOT NULL,
    idChofer INT NOT NULL,
    idPeon INT NOT NULL,
    PRIMARY KEY (idCuadrilla),
    CONSTRAINT fk_cuadrilla_chofer FOREIGN KEY (idChofer) REFERENCES usuario(idUsu),
    CONSTRAINT fk_cuadrilla_peon FOREIGN KEY (idPeon) REFERENCES usuario(idUsu),
    CONSTRAINT uq_cuadrilla_chofer UNIQUE (idChofer),
    CONSTRAINT uq_cuadrilla_peon UNIQUE (idPeon),
    CONSTRAINT chk_cuadrilla_horario CHECK (horarioCuadrilla BETWEEN 0 AND 23),
    CONSTRAINT chk_cuadrilla_integrantes CHECK (idChofer <> idPeon)
) ENGINE=InnoDB;

CREATE TABLE camion (
    idCamion INT AUTO_INCREMENT,
    tipoCamion VARCHAR(30) NOT NULL,
    matricula VARCHAR(10) NOT NULL,
    marca VARCHAR(50) NOT NULL,
    modelo VARCHAR(50) NOT NULL,
    capacidad DECIMAL(10,2) NOT NULL,
    estCamion VARCHAR(20) NOT NULL,
    repuesto BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (idCamion),
    CONSTRAINT uq_camion_matricula UNIQUE (matricula),
    CONSTRAINT chk_camion_tipo CHECK (tipoCamion IN ('comunitario','intradomiciliario','limpieza','centroAcopio')),
    CONSTRAINT chk_camion_estado CHECK (estCamion IN ('disponible','en uso','roto')),
    CONSTRAINT chk_camion_capacidad CHECK (capacidad > 0)
) ENGINE=InnoDB;

CREATE TABLE ruta (
    idRuta INT AUTO_INCREMENT,
    frecuencia VARCHAR(30) NOT NULL,
    idCentro INT NOT NULL,
    PRIMARY KEY (idRuta),
    CONSTRAINT fk_ruta_centro FOREIGN KEY (idCentro) REFERENCES centro(idCentro)
) ENGINE=InnoDB;

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
    CONSTRAINT chk_contenedor_capacidad CHECK (capacidad > 0),
    CONSTRAINT chk_contenedor_zona CHECK (zona IN ('Municipio A','Municipio B','Municipio C','Municipio CH','Municipio D','Municipio E','Municipio F','Municipio G')),
    CONSTRAINT chk_contenedor_estado CHECK (estCon IN ('activo','inactivo')),
    CONSTRAINT chk_contenedor_tipo CHECK (tipoCon IN ('comun','aceite','electronicos','vidrio','plastico'))
) ENGINE=InnoDB;

CREATE TABLE contenedordomiciliario (
    idCon INT NOT NULL,
    numPuerta VARCHAR(30) NOT NULL,
    PRIMARY KEY (idCon),
    CONSTRAINT fk_cd_contenedor FOREIGN KEY (idCon) REFERENCES contenedor(idCon)
) ENGINE=InnoDB;

CREATE TABLE contenedorcomunitario (
    idCon INT NOT NULL,
    latitud DECIMAL(10,7) NOT NULL,
    longitud DECIMAL(10,7) NOT NULL,
    PRIMARY KEY (idCon),
    CONSTRAINT fk_cc_contenedor FOREIGN KEY (idCon) REFERENCES contenedor(idCon),
    CONSTRAINT chk_cc_latitud CHECK (latitud BETWEEN -90 AND 90),
    CONSTRAINT chk_cc_longitud CHECK (longitud BETWEEN -180 AND 180)
) ENGINE=InnoDB;

CREATE TABLE incidencia (
    idIncidencia INT AUTO_INCREMENT,
    fchInci DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    idCon INT NOT NULL,
    tipoInci VARCHAR(30) NOT NULL,
    descInci VARCHAR(200) NOT NULL,
    nomReporte VARCHAR(100) NOT NULL,
    cedHashInci CHAR(64) NOT NULL,
    telReporte VARCHAR(20) NOT NULL,
    prioridad VARCHAR(20) NOT NULL,
    PRIMARY KEY (idIncidencia),
    CONSTRAINT fk_incidencia_contenedor FOREIGN KEY (idCon) REFERENCES contenedor(idCon),
    CONSTRAINT chk_incidencia_tipo CHECK (tipoInci IN ('roto','incendiado','desbordado','basura alrededor')),
    CONSTRAINT chk_incidencia_prioridad CHECK (prioridad IN ('alta','media','baja')),
    CONSTRAINT chk_incidencia_descripcion CHECK (CHAR_LENGTH(descInci) <= 200)
) ENGINE=InnoDB;

CREATE TABLE resolucion (
    idIncidencia INT NOT NULL,
    numIntento INT NOT NULL,
    fchIntento DATETIME NOT NULL,
    descEstado VARCHAR(200) NOT NULL,
    estIntento VARCHAR(20) NOT NULL,
    idUsu INT NOT NULL,
    idCuadrilla INT NOT NULL,
    idCamion INT NOT NULL,
    fchSolucion DATETIME,
    PRIMARY KEY (idIncidencia, numIntento),
    CONSTRAINT fk_resolucion_incidencia FOREIGN KEY (idIncidencia) REFERENCES incidencia(idIncidencia),
    CONSTRAINT fk_resolucion_usuario FOREIGN KEY (idUsu) REFERENCES usuario(idUsu),
    CONSTRAINT fk_resolucion_cuadrilla FOREIGN KEY (idCuadrilla) REFERENCES cuadrilla(idCuadrilla),
    CONSTRAINT fk_resolucion_camion FOREIGN KEY (idCamion) REFERENCES camion(idCamion),
    CONSTRAINT chk_resolucion_intento CHECK (numIntento > 0),
    CONSTRAINT chk_resolucion_estado CHECK (estIntento IN ('abierto','en proceso','cerrado')),
    CONSTRAINT chk_resolucion_descripcion CHECK (CHAR_LENGTH(descEstado) <= 200),
    CONSTRAINT chk_resolucion_fecha CHECK (fchSolucion IS NULL OR fchSolucion >= fchIntento)
) ENGINE=InnoDB;

CREATE TABLE atiende (
    idIncidencia INT NOT NULL,
    idCamion INT NOT NULL,
    PRIMARY KEY (idIncidencia, idCamion),
    CONSTRAINT fk_atiende_incidencia FOREIGN KEY (idIncidencia) REFERENCES incidencia(idIncidencia),
    CONSTRAINT fk_atiende_camion FOREIGN KEY (idCamion) REFERENCES camion(idCamion)
) ENGINE=InnoDB;

CREATE TABLE compone (
    idRuta INT NOT NULL,
    idCon INT NOT NULL,
    orden INT NOT NULL,
    PRIMARY KEY (idRuta, idCon),
    CONSTRAINT uq_compone_orden UNIQUE (idRuta, orden),
    CONSTRAINT fk_compone_ruta FOREIGN KEY (idRuta) REFERENCES ruta(idRuta),
    CONSTRAINT fk_compone_contenedor FOREIGN KEY (idCon) REFERENCES contenedor(idCon),
    CONSTRAINT chk_compone_orden CHECK (orden > 0)
) ENGINE=InnoDB;

CREATE TABLE enviares (
    idCentro INT NOT NULL,
    idVertedero INT NOT NULL,
    fchVertido DATE NOT NULL,
    PRIMARY KEY (idCentro, idVertedero, fchVertido),
    CONSTRAINT fk_enviares_centro FOREIGN KEY (idCentro) REFERENCES centro(idCentro),
    CONSTRAINT fk_enviares_vertedero FOREIGN KEY (idVertedero) REFERENCES vertedero(idVertedero)
) ENGINE=InnoDB;

CREATE TABLE opera (
    idCamion INT NOT NULL,
    idCuadrilla INT NOT NULL,
    fchOperacion DATE NOT NULL,
    PRIMARY KEY (idCamion, idCuadrilla, fchOperacion),
    CONSTRAINT fk_opera_camion FOREIGN KEY (idCamion) REFERENCES camion(idCamion),
    CONSTRAINT fk_opera_cuadrilla FOREIGN KEY (idCuadrilla) REFERENCES cuadrilla(idCuadrilla)
) ENGINE=InnoDB;

CREATE TABLE recorrido (
    idCamion INT NOT NULL,
    idRuta INT NOT NULL,
    fechaRec DATE NOT NULL,
    volumenRecolectado DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (idCamion, idRuta, fechaRec),
    CONSTRAINT fk_recorrido_camion FOREIGN KEY (idCamion) REFERENCES camion(idCamion),
    CONSTRAINT fk_recorrido_ruta FOREIGN KEY (idRuta) REFERENCES ruta(idRuta),
    CONSTRAINT chk_recorrido_volumen CHECK (volumenRecolectado >= 0)
) ENGINE=InnoDB;