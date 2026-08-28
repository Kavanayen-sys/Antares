CREATE DATABASE IF NOT EXISTS sigeru
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sigeru;

CREATE TABLE centro (
    idCentro INT AUTO_INCREMENT,
    tipoCentro VARCHAR(30) NOT NULL,
    capCentro INT NOT NULL,

    PRIMARY KEY (idCentro),

    CONSTRAINT chk_centro_tipo
        CHECK (tipoCentro IN (
            'comun',
            'aceite',
            'electronicos',
            'reciclables'
        )),

    CONSTRAINT chk_centro_capacidad
        CHECK (capCentro > 0)
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

    CONSTRAINT uq_maquinaria_serie
        UNIQUE (numSerie),

    CONSTRAINT fk_maquinaria_centro
        FOREIGN KEY (idCentro)
        REFERENCES centro(idCentro),

    CONSTRAINT chk_maquinaria_capacidad
        CHECK (capMaq >= 0)
) ENGINE=InnoDB;

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
        REFERENCES centro(idCentro),

    CONSTRAINT chk_usuario_rol
        CHECK (rol IN (
            'operario',
            'municipal',
            'cuadrilla',
            'administrador'
        )),

    CONSTRAINT chk_usuario_estado
        CHECK (estUsu IN (
            'activo',
            'inactivo'
        ))
) ENGINE=InnoDB;

CREATE TABLE cuadrilla (
    idCuadrilla INT AUTO_INCREMENT,
    idChofer INT NOT NULL,
    idPeon INT NOT NULL,

    PRIMARY KEY (idCuadrilla),

    CONSTRAINT fk_cuadrilla_chofer
        FOREIGN KEY (idChofer)
        REFERENCES usuario(idUsu),

    CONSTRAINT fk_cuadrilla_peon
        FOREIGN KEY (idPeon)
        REFERENCES usuario(idUsu),

    CONSTRAINT uq_cuadrilla_chofer
        UNIQUE (idChofer),

    CONSTRAINT uq_cuadrilla_peon
        UNIQUE (idPeon),

    CONSTRAINT chk_cuadrilla_integrantes
        CHECK (idChofer <> idPeon)
) ENGINE=InnoDB;

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
        CHECK (tipoVehi IN (
            'comunitario',
            'intradomiciliario',
            'limpieza',
            'centroAcopio'
        )),

    CONSTRAINT chk_vehiculo_estado
        CHECK (estVehi IN (
            'disponible',
            'en uso',
            'roto'
        )),

    CONSTRAINT chk_vehiculo_capacidad
        CHECK (capVehi > 0)
) ENGINE=InnoDB;

CREATE TABLE ruta (
    idRuta INT AUTO_INCREMENT,
    frecuencia VARCHAR(30) NOT NULL,
    idCentro INT NOT NULL,

    PRIMARY KEY (idRuta),

    CONSTRAINT fk_ruta_centro
        FOREIGN KEY (idCentro)
        REFERENCES centro(idCentro)
) ENGINE=InnoDB;

CREATE TABLE recorrido (
    idRecorrido INT AUTO_INCREMENT,
    idVehi INT NOT NULL,
    idRuta INT NOT NULL,
    fechaRec DATE NOT NULL,

    PRIMARY KEY (idRecorrido),

    CONSTRAINT fk_recorrido_vehiculo
        FOREIGN KEY (idVehi)
        REFERENCES vehiculo(idVehi),

    CONSTRAINT fk_recorrido_ruta
        FOREIGN KEY (idRuta)
        REFERENCES ruta(idRuta)
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

    CONSTRAINT chk_contenedor_capacidad
        CHECK (capacidad > 0),

    CONSTRAINT chk_contenedor_zona
        CHECK (zona IN (
            'Municipio A',
            'Municipio B',
            'Municipio C',
            'Municipio CH',
            'Municipio D',
            'Municipio E',
            'Municipio F',
            'Municipio G'
        )),

    CONSTRAINT chk_contenedor_estado
        CHECK (estCon IN (
            'activo',
            'inactivo'
        )),

    CONSTRAINT chk_contenedor_tipo
        CHECK (tipoCon IN (
            'comun',
            'aceite',
            'electronicos',
            'reciclables'
        ))
) ENGINE=InnoDB;

CREATE TABLE contenedordomiciliario (
    idCon INT NOT NULL,
    numPuerta VARCHAR(30) NOT NULL,

    PRIMARY KEY (idCon),

    CONSTRAINT fk_cd_contenedor
        FOREIGN KEY (idCon)
        REFERENCES contenedor(idCon)
) ENGINE=InnoDB;

CREATE TABLE contenedorcomunitario (
    idCon INT NOT NULL,
    latitud DECIMAL(10,7) NOT NULL,
    longitud DECIMAL(10,7) NOT NULL,

    PRIMARY KEY (idCon),

    CONSTRAINT fk_cc_contenedor
        FOREIGN KEY (idCon)
        REFERENCES contenedor(idCon),

    CONSTRAINT chk_cc_latitud
        CHECK (latitud BETWEEN -90 AND 90),

    CONSTRAINT chk_cc_longitud
        CHECK (longitud BETWEEN -180 AND 180)
) ENGINE=InnoDB;

CREATE TABLE incidencia (
    idInci INT AUTO_INCREMENT,
    fchaInci DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    idCon INT NOT NULL,
    tipoInci VARCHAR(30) NOT NULL,
    descInci VARCHAR(200) NOT NULL,
    cedHashInci CHAR(64) NOT NULL,
    prioridad VARCHAR(20) NOT NULL,

    PRIMARY KEY (idInci),

    CONSTRAINT fk_incidencia_contenedor
        FOREIGN KEY (idCon)
        REFERENCES contenedor(idCon),

    CONSTRAINT chk_incidencia_tipo
        CHECK (tipoInci IN (
            'roto',
            'incendiado',
            'desbordado',
            'basura alrededor'
        )),

    CONSTRAINT chk_incidencia_prioridad
        CHECK (prioridad IN (
            'alta',
            'media',
            'baja'
        )),

    CONSTRAINT chk_incidencia_descripcion
        CHECK (CHAR_LENGTH(descInci) <= 200)
) ENGINE=InnoDB;


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
        REFERENCES incidencia(idInci),

    CONSTRAINT fk_resuelve_usuario
        FOREIGN KEY (idUsu)
        REFERENCES usuario(idUsu),

    CONSTRAINT chk_resuelve_estado
        CHECK (estIntento IN (
            'abierto',
            'en proceso',
            'cerrado'
        )),

    CONSTRAINT chk_resuelve_descripcion
        CHECK (CHAR_LENGTH(descEstado) <= 200)
) ENGINE=InnoDB;

CREATE TABLE atiende (
    idAtencion INT AUTO_INCREMENT,
    idInci INT NOT NULL,
    idVehi INT NOT NULL,
    fchAtencion DATETIME NOT NULL,

    PRIMARY KEY (idAtencion),

    CONSTRAINT fk_atiende_incidencia
        FOREIGN KEY (idInci)
        REFERENCES incidencia(idInci),

    CONSTRAINT fk_atiende_vehiculo
        FOREIGN KEY (idVehi)
        REFERENCES vehiculo(idVehi)
) ENGINE=InnoDB;

CREATE TABLE compone (
    idComposicion INT AUTO_INCREMENT,
    idRuta INT NOT NULL,
    idCon INT NOT NULL,
    orden INT NOT NULL,

    PRIMARY KEY (idComposicion),

    CONSTRAINT fk_compone_ruta
        FOREIGN KEY (idRuta)
        REFERENCES ruta(idRuta),

    CONSTRAINT fk_compone_contenedor
        FOREIGN KEY (idCon)
        REFERENCES contenedor(idCon),

    CONSTRAINT uq_compone_orden
        UNIQUE (idRuta, orden),

    CONSTRAINT chk_compone_orden
        CHECK (orden > 0)
) ENGINE=InnoDB;

CREATE TABLE opera (
    idOperacion INT AUTO_INCREMENT,
    idVehi INT NOT NULL,
    idCuadrilla INT NOT NULL,
    fchOperacion DATE NOT NULL,

    PRIMARY KEY (idOperacion),

    CONSTRAINT fk_opera_vehiculo
        FOREIGN KEY (idVehi)
        REFERENCES vehiculo(idVehi),

    CONSTRAINT fk_opera_cuadrilla
        FOREIGN KEY (idCuadrilla)
        REFERENCES cuadrilla(idCuadrilla)
) ENGINE=InnoDB;

CREATE TABLE enviares (
    idEnvio INT AUTO_INCREMENT,
    idCentro INT NOT NULL,
    idVertedero INT NOT NULL,
    fchVertido DATE NOT NULL,

    PRIMARY KEY (idEnvio),

    CONSTRAINT fk_enviares_centro
        FOREIGN KEY (idCentro)
        REFERENCES centro(idCentro),

    CONSTRAINT fk_enviares_vertederero
        FOREIGN KEY (idVertedero)
        REFERENCES vertedero(idVertedero)
) ENGINE=InnoDB;

CREATE TABLE garaje (
    idGaraje INT AUTO_INCREMENT,
    ubiGaraje VARCHAR(150) NOT NULL,

    PRIMARY KEY (idGaraje)
) ENGINE=InnoDB;

CREATE TABLE mantenimiento (
    idMant INT AUTO_INCREMENT,
    ubiMant VARCHAR(150) NOT NULL,

    PRIMARY KEY (idMant)
) ENGINE=InnoDB;


CREATE TABLE estaciona (
    idEstacion INT AUTO_INCREMENT,
    idGaraje INT NOT NULL,
    idVehi INT NOT NULL,
    horaEstacionamiento DATETIME NOT NULL,

    PRIMARY KEY (idEstacion),

    CONSTRAINT fk_estaciona_garaje
        FOREIGN KEY (idGaraje)
        REFERENCES garaje(idGaraje),

    CONSTRAINT fk_estaciona_vehiculo
        FOREIGN KEY (idVehi)
        REFERENCES vehiculo(idVehi)
) ENGINE=InnoDB;

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
        REFERENCES mantenimiento(idMant),

    CONSTRAINT fk_repara_vehiculo
        FOREIGN KEY (idVehi)
        REFERENCES vehiculo(idVehi),

    CONSTRAINT chk_repara_fechas
        CHECK (
            horaSalida IS NULL
            OR horaSalida >= horaIngreso
        )
) ENGINE=InnoDB;