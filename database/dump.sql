-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: sigeru
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `atiende`
--

DROP TABLE IF EXISTS `atiende`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `atiende` (
  `idIncidencia` int(11) NOT NULL,
  `idCamion` int(11) NOT NULL,
  PRIMARY KEY (`idIncidencia`,`idCamion`),
  KEY `fk_atiende_camion` (`idCamion`),
  CONSTRAINT `fk_atiende_camion` FOREIGN KEY (`idCamion`) REFERENCES `camion` (`idCamion`),
  CONSTRAINT `fk_atiende_incidencia` FOREIGN KEY (`idIncidencia`) REFERENCES `incidencia` (`idIncidencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `atiende`
--

LOCK TABLES `atiende` WRITE;
/*!40000 ALTER TABLE `atiende` DISABLE KEYS */;
/*!40000 ALTER TABLE `atiende` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `camion`
--

DROP TABLE IF EXISTS `camion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `camion` (
  `idCamion` int(11) NOT NULL AUTO_INCREMENT,
  `tipoCamion` varchar(30) NOT NULL,
  `matricula` varchar(10) NOT NULL,
  `marca` varchar(50) NOT NULL,
  `modelo` varchar(50) NOT NULL,
  `capacidad` decimal(10,2) NOT NULL,
  `estCamion` varchar(20) NOT NULL,
  `repuesto` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`idCamion`),
  UNIQUE KEY `uq_camion_matricula` (`matricula`),
  CONSTRAINT `chk_camion_tipo` CHECK (`tipoCamion` in ('comunitario','intradomiciliario','limpieza','centroAcopio')),
  CONSTRAINT `chk_camion_estado` CHECK (`estCamion` in ('disponible','en uso','roto')),
  CONSTRAINT `chk_camion_capacidad` CHECK (`capacidad` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `camion`
--

LOCK TABLES `camion` WRITE;
/*!40000 ALTER TABLE `camion` DISABLE KEYS */;
INSERT INTO `camion` VALUES (1,'comunitario','STP1234','Volkswagen','Constellation',12000.00,'disponible',0),(2,'limpieza','STP5678','Mercedes-Benz','Atego',9000.00,'en uso',0);
/*!40000 ALTER TABLE `camion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `centro`
--

DROP TABLE IF EXISTS `centro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `centro` (
  `idCentro` int(11) NOT NULL AUTO_INCREMENT,
  `tipoCentro` varchar(30) NOT NULL,
  `especialidadCentro` varchar(100) NOT NULL,
  `capCentro` int(11) NOT NULL,
  PRIMARY KEY (`idCentro`),
  CONSTRAINT `chk_centro_tipo` CHECK (`tipoCentro` in ('comun','aceite','electronicos','reciclables')),
  CONSTRAINT `chk_centro_capacidad` CHECK (`capCentro` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `centro`
--

LOCK TABLES `centro` WRITE;
/*!40000 ALTER TABLE `centro` DISABLE KEYS */;
/*!40000 ALTER TABLE `centro` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compone`
--

DROP TABLE IF EXISTS `compone`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compone` (
  `idRuta` int(11) NOT NULL,
  `idCon` int(11) NOT NULL,
  `orden` int(11) NOT NULL,
  PRIMARY KEY (`idRuta`,`idCon`),
  UNIQUE KEY `uq_compone_orden` (`idRuta`,`orden`),
  KEY `fk_compone_contenedor` (`idCon`),
  CONSTRAINT `fk_compone_contenedor` FOREIGN KEY (`idCon`) REFERENCES `contenedor` (`idCon`),
  CONSTRAINT `fk_compone_ruta` FOREIGN KEY (`idRuta`) REFERENCES `ruta` (`idRuta`),
  CONSTRAINT `chk_compone_orden` CHECK (`orden` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compone`
--

LOCK TABLES `compone` WRITE;
/*!40000 ALTER TABLE `compone` DISABLE KEYS */;
/*!40000 ALTER TABLE `compone` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contenedor`
--

DROP TABLE IF EXISTS `contenedor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contenedor` (
  `idCon` int(11) NOT NULL AUTO_INCREMENT,
  `capacidad` decimal(10,2) NOT NULL,
  `calle` varchar(100) NOT NULL,
  `esquina` varchar(100) NOT NULL,
  `zona` varchar(30) NOT NULL,
  `estCon` varchar(20) NOT NULL,
  `tipoCon` varchar(30) NOT NULL,
  `repuesto` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`idCon`),
  CONSTRAINT `chk_contenedor_capacidad` CHECK (`capacidad` > 0),
  CONSTRAINT `chk_contenedor_zona` CHECK (`zona` in ('Municipio A','Municipio B','Municipio C','Municipio CH','Municipio D','Municipio E','Municipio F','Municipio G')),
  CONSTRAINT `chk_contenedor_estado` CHECK (`estCon` in ('activo','inactivo')),
  CONSTRAINT `chk_contenedor_tipo` CHECK (`tipoCon` in ('comun','aceite','electronicos','reciclables'))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contenedor`
--

LOCK TABLES `contenedor` WRITE;
/*!40000 ALTER TABLE `contenedor` DISABLE KEYS */;
INSERT INTO `contenedor` VALUES (1,1100.00,'18 de Julio','Ejido','Municipio B','activo','comun',0),(2,1100.00,'Colonia','Eduardo Acevedo','Municipio B','activo','comun',0),(3,240.00,'Benito Blanco','Pagola','Municipio CH','activo','reciclables',0);
/*!40000 ALTER TABLE `contenedor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contenedorcomunitario`
--

DROP TABLE IF EXISTS `contenedorcomunitario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contenedorcomunitario` (
  `idCon` int(11) NOT NULL,
  `latitud` decimal(10,7) NOT NULL,
  `longitud` decimal(10,7) NOT NULL,
  PRIMARY KEY (`idCon`),
  CONSTRAINT `fk_cc_contenedor` FOREIGN KEY (`idCon`) REFERENCES `contenedor` (`idCon`),
  CONSTRAINT `chk_cc_latitud` CHECK (`latitud` between -90 and 90),
  CONSTRAINT `chk_cc_longitud` CHECK (`longitud` between -180 and 180)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contenedorcomunitario`
--

LOCK TABLES `contenedorcomunitario` WRITE;
/*!40000 ALTER TABLE `contenedorcomunitario` DISABLE KEYS */;
/*!40000 ALTER TABLE `contenedorcomunitario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contenedordomiciliario`
--

DROP TABLE IF EXISTS `contenedordomiciliario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contenedordomiciliario` (
  `idCon` int(11) NOT NULL,
  `numPuerta` varchar(30) NOT NULL,
  PRIMARY KEY (`idCon`),
  CONSTRAINT `fk_cd_contenedor` FOREIGN KEY (`idCon`) REFERENCES `contenedor` (`idCon`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contenedordomiciliario`
--

LOCK TABLES `contenedordomiciliario` WRITE;
/*!40000 ALTER TABLE `contenedordomiciliario` DISABLE KEYS */;
/*!40000 ALTER TABLE `contenedordomiciliario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cuadrilla`
--

DROP TABLE IF EXISTS `cuadrilla`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cuadrilla` (
  `idCuadrilla` int(11) NOT NULL AUTO_INCREMENT,
  `horarioCuadrilla` int(11) NOT NULL,
  `idChofer` int(11) NOT NULL,
  `idPeon` int(11) NOT NULL,
  PRIMARY KEY (`idCuadrilla`),
  UNIQUE KEY `uq_cuadrilla_chofer` (`idChofer`),
  UNIQUE KEY `uq_cuadrilla_peon` (`idPeon`),
  CONSTRAINT `fk_cuadrilla_chofer` FOREIGN KEY (`idChofer`) REFERENCES `usuario` (`idUsu`),
  CONSTRAINT `fk_cuadrilla_peon` FOREIGN KEY (`idPeon`) REFERENCES `usuario` (`idUsu`),
  CONSTRAINT `chk_cuadrilla_horario` CHECK (`horarioCuadrilla` between 0 and 23),
  CONSTRAINT `chk_cuadrilla_integrantes` CHECK (`idChofer` <> `idPeon`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuadrilla`
--

LOCK TABLES `cuadrilla` WRITE;
/*!40000 ALTER TABLE `cuadrilla` DISABLE KEYS */;
/*!40000 ALTER TABLE `cuadrilla` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enviares`
--

DROP TABLE IF EXISTS `enviares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enviares` (
  `idCentro` int(11) NOT NULL,
  `idVertedero` int(11) NOT NULL,
  `fchVertido` date NOT NULL,
  PRIMARY KEY (`idCentro`,`idVertedero`,`fchVertido`),
  KEY `fk_enviares_vertedero` (`idVertedero`),
  CONSTRAINT `fk_enviares_centro` FOREIGN KEY (`idCentro`) REFERENCES `centro` (`idCentro`),
  CONSTRAINT `fk_enviares_vertedero` FOREIGN KEY (`idVertedero`) REFERENCES `vertedero` (`idVertedero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enviares`
--

LOCK TABLES `enviares` WRITE;
/*!40000 ALTER TABLE `enviares` DISABLE KEYS */;
/*!40000 ALTER TABLE `enviares` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidencia`
--

DROP TABLE IF EXISTS `incidencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidencia` (
  `idIncidencia` int(11) NOT NULL AUTO_INCREMENT,
  `fchInci` datetime NOT NULL DEFAULT current_timestamp(),
  `idCon` int(11) NOT NULL,
  `tipoInci` varchar(30) NOT NULL,
  `descInci` varchar(200) NOT NULL,
  `nomReporte` varchar(100) NOT NULL,
  `cedHashInci` char(64) NOT NULL,
  `telReporte` varchar(20) NOT NULL,
  `prioridad` varchar(20) NOT NULL,
  PRIMARY KEY (`idIncidencia`),
  KEY `fk_incidencia_contenedor` (`idCon`),
  CONSTRAINT `fk_incidencia_contenedor` FOREIGN KEY (`idCon`) REFERENCES `contenedor` (`idCon`),
  CONSTRAINT `chk_incidencia_tipo` CHECK (`tipoInci` in ('roto','incendiado','desbordado','basura alrededor')),
  CONSTRAINT `chk_incidencia_prioridad` CHECK (`prioridad` in ('alta','media','baja')),
  CONSTRAINT `chk_incidencia_descripcion` CHECK (char_length(`descInci`) <= 200)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidencia`
--

LOCK TABLES `incidencia` WRITE;
/*!40000 ALTER TABLE `incidencia` DISABLE KEYS */;
INSERT INTO `incidencia` VALUES (1,'2026-07-25 20:35:04',2,'desbordado','El contenedor supera su capacidad.','María Pérez','6a5ef7bdbf8db33f0192c3a106dbe58fd9651f5d7bf8b094897eebd2b5514194','099123456','media'),(2,'2026-07-25 20:40:35',1,'roto','Esta lleno de caca','Juan','8bd88f10268038dd86a3e225a040d6da90fde747607a0eac97859657fb789e41','099333444','media');
/*!40000 ALTER TABLE `incidencia` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maquinaria`
--

DROP TABLE IF EXISTS `maquinaria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `maquinaria` (
  `idMaq` int(11) NOT NULL AUTO_INCREMENT,
  `idCentro` int(11) NOT NULL,
  `propositoMaq` varchar(200) NOT NULL,
  `capMaq` decimal(10,2) NOT NULL,
  `marcaMaq` varchar(50) NOT NULL,
  `modeloMaq` varchar(50) NOT NULL,
  `numSerie` varchar(50) NOT NULL,
  PRIMARY KEY (`idMaq`),
  UNIQUE KEY `uq_maquinaria_serie` (`numSerie`),
  KEY `fk_maquinaria_centro` (`idCentro`),
  CONSTRAINT `fk_maquinaria_centro` FOREIGN KEY (`idCentro`) REFERENCES `centro` (`idCentro`),
  CONSTRAINT `chk_maquinaria_capacidad` CHECK (`capMaq` >= 0),
  CONSTRAINT `chk_maquinaria_proposito` CHECK (char_length(`propositoMaq`) <= 200)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maquinaria`
--

LOCK TABLES `maquinaria` WRITE;
/*!40000 ALTER TABLE `maquinaria` DISABLE KEYS */;
/*!40000 ALTER TABLE `maquinaria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opera`
--

DROP TABLE IF EXISTS `opera`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `opera` (
  `idCamion` int(11) NOT NULL,
  `idCuadrilla` int(11) NOT NULL,
  `fchOperacion` date NOT NULL,
  PRIMARY KEY (`idCamion`,`idCuadrilla`,`fchOperacion`),
  KEY `fk_opera_cuadrilla` (`idCuadrilla`),
  CONSTRAINT `fk_opera_camion` FOREIGN KEY (`idCamion`) REFERENCES `camion` (`idCamion`),
  CONSTRAINT `fk_opera_cuadrilla` FOREIGN KEY (`idCuadrilla`) REFERENCES `cuadrilla` (`idCuadrilla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opera`
--

LOCK TABLES `opera` WRITE;
/*!40000 ALTER TABLE `opera` DISABLE KEYS */;
/*!40000 ALTER TABLE `opera` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recorrido`
--

DROP TABLE IF EXISTS `recorrido`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recorrido` (
  `idCamion` int(11) NOT NULL,
  `idRuta` int(11) NOT NULL,
  `fechaRec` date NOT NULL,
  `volumenRecolectado` decimal(10,2) NOT NULL,
  PRIMARY KEY (`idCamion`,`idRuta`,`fechaRec`),
  KEY `fk_recorrido_ruta` (`idRuta`),
  CONSTRAINT `fk_recorrido_camion` FOREIGN KEY (`idCamion`) REFERENCES `camion` (`idCamion`),
  CONSTRAINT `fk_recorrido_ruta` FOREIGN KEY (`idRuta`) REFERENCES `ruta` (`idRuta`),
  CONSTRAINT `chk_recorrido_volumen` CHECK (`volumenRecolectado` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recorrido`
--

LOCK TABLES `recorrido` WRITE;
/*!40000 ALTER TABLE `recorrido` DISABLE KEYS */;
/*!40000 ALTER TABLE `recorrido` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resolucion`
--

DROP TABLE IF EXISTS `resolucion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resolucion` (
  `idIncidencia` int(11) NOT NULL,
  `numIntento` int(11) NOT NULL,
  `fchIntento` datetime NOT NULL,
  `descEstado` varchar(200) NOT NULL,
  `estIntento` varchar(20) NOT NULL,
  `idUsu` int(11) NOT NULL,
  `idCuadrilla` int(11) NOT NULL,
  `idCamion` int(11) NOT NULL,
  `fchSolucion` datetime DEFAULT NULL,
  PRIMARY KEY (`idIncidencia`,`numIntento`),
  KEY `fk_resolucion_usuario` (`idUsu`),
  KEY `fk_resolucion_cuadrilla` (`idCuadrilla`),
  KEY `fk_resolucion_camion` (`idCamion`),
  CONSTRAINT `fk_resolucion_camion` FOREIGN KEY (`idCamion`) REFERENCES `camion` (`idCamion`),
  CONSTRAINT `fk_resolucion_cuadrilla` FOREIGN KEY (`idCuadrilla`) REFERENCES `cuadrilla` (`idCuadrilla`),
  CONSTRAINT `fk_resolucion_incidencia` FOREIGN KEY (`idIncidencia`) REFERENCES `incidencia` (`idIncidencia`),
  CONSTRAINT `fk_resolucion_usuario` FOREIGN KEY (`idUsu`) REFERENCES `usuario` (`idUsu`),
  CONSTRAINT `chk_resolucion_intento` CHECK (`numIntento` > 0),
  CONSTRAINT `chk_resolucion_estado` CHECK (`estIntento` in ('abierto','en proceso','cerrado')),
  CONSTRAINT `chk_resolucion_descripcion` CHECK (char_length(`descEstado`) <= 200),
  CONSTRAINT `chk_resolucion_fecha` CHECK (`fchSolucion` is null or `fchSolucion` >= `fchIntento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resolucion`
--

LOCK TABLES `resolucion` WRITE;
/*!40000 ALTER TABLE `resolucion` DISABLE KEYS */;
/*!40000 ALTER TABLE `resolucion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ruta`
--

DROP TABLE IF EXISTS `ruta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ruta` (
  `idRuta` int(11) NOT NULL AUTO_INCREMENT,
  `frecuencia` varchar(30) NOT NULL,
  `idCentro` int(11) NOT NULL,
  PRIMARY KEY (`idRuta`),
  KEY `fk_ruta_centro` (`idCentro`),
  CONSTRAINT `fk_ruta_centro` FOREIGN KEY (`idCentro`) REFERENCES `centro` (`idCentro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ruta`
--

LOCK TABLES `ruta` WRITE;
/*!40000 ALTER TABLE `ruta` DISABLE KEYS */;
/*!40000 ALTER TABLE `ruta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario` (
  `idUsu` int(11) NOT NULL AUTO_INCREMENT,
  `priNom` varchar(50) NOT NULL,
  `telUsu` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `hash` varchar(255) NOT NULL,
  `rol` varchar(30) NOT NULL,
  `estUsu` varchar(20) NOT NULL,
  `idCentro` int(11) DEFAULT NULL,
  PRIMARY KEY (`idUsu`),
  UNIQUE KEY `uq_usuario_email` (`email`),
  KEY `fk_usuario_centro` (`idCentro`),
  CONSTRAINT `fk_usuario_centro` FOREIGN KEY (`idCentro`) REFERENCES `centro` (`idCentro`),
  CONSTRAINT `chk_usuario_rol` CHECK (`rol` in ('operario','municipal','cuadrilla','administrador')),
  CONSTRAINT `chk_usuario_estado` CHECK (`estUsu` in ('activo','inactivo'))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (1,'Administrador SiGeRU','099000001','admin@sigeru.local','$2y$12$B0n37IC9bDBgFdiXe/87geIavyGmy6Ck1ZWEiLY0HNY0d8XD95bCm','administrador','activo',NULL);
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vertedero`
--

DROP TABLE IF EXISTS `vertedero`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vertedero` (
  `idVertedero` int(11) NOT NULL AUTO_INCREMENT,
  `ubicacionVertedero` varchar(150) NOT NULL,
  PRIMARY KEY (`idVertedero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vertedero`
--

LOCK TABLES `vertedero` WRITE;
/*!40000 ALTER TABLE `vertedero` DISABLE KEYS */;
/*!40000 ALTER TABLE `vertedero` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-27  0:24:32
