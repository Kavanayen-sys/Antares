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
-- Current Database: `sigeru`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `sigeru` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `sigeru`;

--
-- Table structure for table `atiende`
--

DROP TABLE IF EXISTS `atiende`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `atiende` (
  `idAtencion` int(11) NOT NULL AUTO_INCREMENT,
  `idInci` int(11) NOT NULL,
  `idVehi` int(11) NOT NULL,
  `fchAtencion` datetime NOT NULL,
  PRIMARY KEY (`idAtencion`),
  KEY `fk_atiende_incidencia` (`idInci`),
  KEY `fk_atiende_vehiculo` (`idVehi`),
  CONSTRAINT `fk_atiende_incidencia` FOREIGN KEY (`idInci`) REFERENCES `incidencia` (`idInci`),
  CONSTRAINT `fk_atiende_vehiculo` FOREIGN KEY (`idVehi`) REFERENCES `vehiculo` (`idVehi`)
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
-- Table structure for table `centro`
--

DROP TABLE IF EXISTS `centro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `centro` (
  `idCentro` int(11) NOT NULL AUTO_INCREMENT,
  `ubiCentro` varchar(150) NOT NULL,
  `tipoCentro` varchar(30) NOT NULL,
  `capCentro` int(11) NOT NULL,
  PRIMARY KEY (`idCentro`),
  CONSTRAINT `chk_centro_tipo` CHECK (`tipoCentro` in ('comun','aceite','electronicos','reciclables')),
  CONSTRAINT `chk_centro_capacidad` CHECK (`capCentro` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `centro`
--

LOCK TABLES `centro` WRITE;
/*!40000 ALTER TABLE `centro` DISABLE KEYS */;
INSERT INTO `centro` VALUES (1,'Camino Corrales 2800','comun',50000),(2,'Av. 8 de Octubre 3400','reciclables',30000),(3,'Bulevar Artigas 4100','electronicos',15000),(4,'Ruta 8 km 17','aceite',10000);
/*!40000 ALTER TABLE `centro` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compone`
--

DROP TABLE IF EXISTS `compone`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compone` (
  `idComposicion` int(11) NOT NULL AUTO_INCREMENT,
  `idRuta` int(11) NOT NULL,
  `idCon` int(11) NOT NULL,
  `orden` int(11) NOT NULL,
  PRIMARY KEY (`idComposicion`),
  UNIQUE KEY `uq_compone_orden` (`idRuta`,`orden`),
  KEY `fk_compone_contenedor` (`idCon`),
  CONSTRAINT `fk_compone_contenedor` FOREIGN KEY (`idCon`) REFERENCES `contenedor` (`idCon`),
  CONSTRAINT `fk_compone_ruta` FOREIGN KEY (`idRuta`) REFERENCES `ruta` (`idRuta`),
  CONSTRAINT `chk_compone_orden` CHECK (`orden` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compone`
--

LOCK TABLES `compone` WRITE;
/*!40000 ALTER TABLE `compone` DISABLE KEYS */;
INSERT INTO `compone` VALUES (1,1,7,1),(2,1,8,2),(3,1,9,3);
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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contenedor`
--

LOCK TABLES `contenedor` WRITE;
/*!40000 ALTER TABLE `contenedor` DISABLE KEYS */;
INSERT INTO `contenedor` VALUES (7,1100.00,'18 de Julio','Ejido','Municipio B','activo','comun',0),(8,1100.00,'Colonia','Eduardo Acevedo','Municipio B','activo','comun',0),(9,240.00,'Benito Blanco','Pagola','Municipio CH','activo','aceite',0);
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
  `idChofer` int(11) NOT NULL,
  `idPeon` int(11) NOT NULL,
  PRIMARY KEY (`idCuadrilla`),
  UNIQUE KEY `uq_cuadrilla_chofer` (`idChofer`),
  UNIQUE KEY `uq_cuadrilla_peon` (`idPeon`),
  CONSTRAINT `fk_cuadrilla_chofer` FOREIGN KEY (`idChofer`) REFERENCES `usuario` (`idUsu`),
  CONSTRAINT `fk_cuadrilla_peon` FOREIGN KEY (`idPeon`) REFERENCES `usuario` (`idUsu`),
  CONSTRAINT `chk_cuadrilla_integrantes` CHECK (`idChofer` <> `idPeon`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cuadrilla`
--

LOCK TABLES `cuadrilla` WRITE;
/*!40000 ALTER TABLE `cuadrilla` DISABLE KEYS */;
INSERT INTO `cuadrilla` VALUES (1,5,6);
/*!40000 ALTER TABLE `cuadrilla` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enviares`
--

DROP TABLE IF EXISTS `enviares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enviares` (
  `idEnvio` int(11) NOT NULL AUTO_INCREMENT,
  `idCentro` int(11) NOT NULL,
  `idVertedero` int(11) NOT NULL,
  `fchVertido` date NOT NULL,
  PRIMARY KEY (`idEnvio`),
  KEY `fk_enviares_centro` (`idCentro`),
  KEY `fk_enviares_vertederero` (`idVertedero`),
  CONSTRAINT `fk_enviares_centro` FOREIGN KEY (`idCentro`) REFERENCES `centro` (`idCentro`),
  CONSTRAINT `fk_enviares_vertederero` FOREIGN KEY (`idVertedero`) REFERENCES `vertedero` (`idVertedero`)
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
-- Table structure for table `estaciona`
--

DROP TABLE IF EXISTS `estaciona`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estaciona` (
  `idEstacion` int(11) NOT NULL AUTO_INCREMENT,
  `idGaraje` int(11) NOT NULL,
  `idVehi` int(11) NOT NULL,
  `horaEstacionamiento` datetime NOT NULL,
  PRIMARY KEY (`idEstacion`),
  KEY `fk_estaciona_garaje` (`idGaraje`),
  KEY `fk_estaciona_vehiculo` (`idVehi`),
  CONSTRAINT `fk_estaciona_garaje` FOREIGN KEY (`idGaraje`) REFERENCES `garaje` (`idGaraje`),
  CONSTRAINT `fk_estaciona_vehiculo` FOREIGN KEY (`idVehi`) REFERENCES `vehiculo` (`idVehi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estaciona`
--

LOCK TABLES `estaciona` WRITE;
/*!40000 ALTER TABLE `estaciona` DISABLE KEYS */;
/*!40000 ALTER TABLE `estaciona` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `garaje`
--

DROP TABLE IF EXISTS `garaje`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `garaje` (
  `idGaraje` int(11) NOT NULL AUTO_INCREMENT,
  `ubiGaraje` varchar(150) NOT NULL,
  PRIMARY KEY (`idGaraje`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `garaje`
--

LOCK TABLES `garaje` WRITE;
/*!40000 ALTER TABLE `garaje` DISABLE KEYS */;
/*!40000 ALTER TABLE `garaje` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidencia`
--

DROP TABLE IF EXISTS `incidencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidencia` (
  `idInci` int(11) NOT NULL AUTO_INCREMENT,
  `fchaInci` datetime NOT NULL DEFAULT current_timestamp(),
  `idCon` int(11) NOT NULL,
  `tipoInci` varchar(30) NOT NULL,
  `descInci` varchar(200) NOT NULL,
  `cedHashInci` char(64) NOT NULL,
  `prioridad` varchar(20) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'en curso',
  PRIMARY KEY (`idInci`),
  KEY `fk_incidencia_contenedor` (`idCon`),
  CONSTRAINT `fk_incidencia_contenedor` FOREIGN KEY (`idCon`) REFERENCES `contenedor` (`idCon`),
  CONSTRAINT `chk_incidencia_tipo` CHECK (`tipoInci` in ('roto','incendiado','desbordado','basura alrededor')),
  CONSTRAINT `chk_incidencia_prioridad` CHECK (`prioridad` in ('alta','media','baja')),
  CONSTRAINT `chk_incidencia_descripcion` CHECK (char_length(`descInci`) <= 200)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidencia`
--

LOCK TABLES `incidencia` WRITE;
/*!40000 ALTER TABLE `incidencia` DISABLE KEYS */;
INSERT INTO `incidencia` VALUES (2,'2026-08-27 16:06:55',7,'desbordado','El contenedor supera su capacidad.','6a5ef7bdbf8db33f0192c3a106dbe58fd9651f5d7bf8b094897eebd2b5514194','media','en curso'),(3,'2026-08-27 16:40:29',7,'desbordado','ksdkskdkd','ea556a48e81850da2dbcc300a81df66cab076f7d291f972466eaf66700050fd0','media','en curso'),(4,'2026-08-31 15:41:53',7,'desbordado','JAJIASDIHAI','3504f750d9fb0e9da5e7531e557d1844428eeab22be93fc9cf754a275cdd07d7','media','en curso'),(5,'2026-08-31 15:46:10',8,'roto','drop database;','2528fca32f7790b3c5f3f57552e4fe0d24e0d1bec91436def7d33ce104fd429b','media','en curso'),(6,'2026-08-31 16:19:57',7,'incendiado','3iui393','3504f750d9fb0e9da5e7531e557d1844428eeab22be93fc9cf754a275cdd07d7','alta','en curso'),(7,'2026-08-31 16:20:40',9,'desbordado','kssks','ea556a48e81850da2dbcc300a81df66cab076f7d291f972466eaf66700050fd0','media','en curso'),(8,'2026-08-31 16:44:15',7,'roto','dfd','ea556a48e81850da2dbcc300a81df66cab076f7d291f972466eaf66700050fd0','media','resuelto'),(9,'2026-08-31 17:37:53',8,'desbordado','ffjhjb','ea556a48e81850da2dbcc300a81df66cab076f7d291f972466eaf66700050fd0','media','en curso');
/*!40000 ALTER TABLE `incidencia` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mantenimiento`
--

DROP TABLE IF EXISTS `mantenimiento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mantenimiento` (
  `idMant` int(11) NOT NULL AUTO_INCREMENT,
  `ubiMant` varchar(150) NOT NULL,
  PRIMARY KEY (`idMant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mantenimiento`
--

LOCK TABLES `mantenimiento` WRITE;
/*!40000 ALTER TABLE `mantenimiento` DISABLE KEYS */;
/*!40000 ALTER TABLE `mantenimiento` ENABLE KEYS */;
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
  CONSTRAINT `chk_maquinaria_capacidad` CHECK (`capMaq` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maquinaria`
--

LOCK TABLES `maquinaria` WRITE;
/*!40000 ALTER TABLE `maquinaria` DISABLE KEYS */;
INSERT INTO `maquinaria` VALUES (1,1,'Prensa compactadora hidráulica',1200.50,'Caterpillar','CP-500','CAT-500-2024'),(2,1,'Cinta transportadora y separadora',850.00,'Komatsu','TR-300','KOM-300-2023'),(3,1,'Trituradora de residuos sólidos',2500.00,'Volvo','SH-800','VOL-800-2025');
/*!40000 ALTER TABLE `maquinaria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opera`
--

DROP TABLE IF EXISTS `opera`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `opera` (
  `idOperacion` int(11) NOT NULL AUTO_INCREMENT,
  `idVehi` int(11) NOT NULL,
  `idCuadrilla` int(11) NOT NULL,
  `fchOperacion` date NOT NULL,
  PRIMARY KEY (`idOperacion`),
  KEY `fk_opera_vehiculo` (`idVehi`),
  KEY `fk_opera_cuadrilla` (`idCuadrilla`),
  CONSTRAINT `fk_opera_cuadrilla` FOREIGN KEY (`idCuadrilla`) REFERENCES `cuadrilla` (`idCuadrilla`),
  CONSTRAINT `fk_opera_vehiculo` FOREIGN KEY (`idVehi`) REFERENCES `vehiculo` (`idVehi`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opera`
--

LOCK TABLES `opera` WRITE;
/*!40000 ALTER TABLE `opera` DISABLE KEYS */;
INSERT INTO `opera` VALUES (1,1,1,'2026-08-31');
/*!40000 ALTER TABLE `opera` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recorrido`
--

DROP TABLE IF EXISTS `recorrido`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recorrido` (
  `idRecorrido` int(11) NOT NULL AUTO_INCREMENT,
  `idVehi` int(11) NOT NULL,
  `idRuta` int(11) NOT NULL,
  `fechaRec` date NOT NULL,
  PRIMARY KEY (`idRecorrido`),
  KEY `fk_recorrido_vehiculo` (`idVehi`),
  KEY `fk_recorrido_ruta` (`idRuta`),
  CONSTRAINT `fk_recorrido_ruta` FOREIGN KEY (`idRuta`) REFERENCES `ruta` (`idRuta`),
  CONSTRAINT `fk_recorrido_vehiculo` FOREIGN KEY (`idVehi`) REFERENCES `vehiculo` (`idVehi`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recorrido`
--

LOCK TABLES `recorrido` WRITE;
/*!40000 ALTER TABLE `recorrido` DISABLE KEYS */;
INSERT INTO `recorrido` VALUES (1,1,1,'2026-08-31');
/*!40000 ALTER TABLE `recorrido` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repara`
--

DROP TABLE IF EXISTS `repara`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repara` (
  `idReparacion` int(11) NOT NULL AUTO_INCREMENT,
  `idMant` int(11) NOT NULL,
  `idVehi` int(11) NOT NULL,
  `horaIngreso` datetime NOT NULL,
  `horaSalida` datetime DEFAULT NULL,
  `estadoReparacion` varchar(30) NOT NULL,
  PRIMARY KEY (`idReparacion`),
  KEY `fk_repara_mantenimiento` (`idMant`),
  KEY `fk_repara_vehiculo` (`idVehi`),
  CONSTRAINT `fk_repara_mantenimiento` FOREIGN KEY (`idMant`) REFERENCES `mantenimiento` (`idMant`),
  CONSTRAINT `fk_repara_vehiculo` FOREIGN KEY (`idVehi`) REFERENCES `vehiculo` (`idVehi`),
  CONSTRAINT `chk_repara_fechas` CHECK (`horaSalida` is null or `horaSalida` >= `horaIngreso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repara`
--

LOCK TABLES `repara` WRITE;
/*!40000 ALTER TABLE `repara` DISABLE KEYS */;
/*!40000 ALTER TABLE `repara` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resuelve`
--

DROP TABLE IF EXISTS `resuelve`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resuelve` (
  `idResolucion` int(11) NOT NULL AUTO_INCREMENT,
  `idInci` int(11) NOT NULL,
  `idUsu` int(11) NOT NULL,
  `fchIntento` datetime NOT NULL,
  `descEstado` varchar(200) NOT NULL,
  `estIntento` varchar(20) NOT NULL,
  PRIMARY KEY (`idResolucion`),
  KEY `fk_resuelve_incidencia` (`idInci`),
  KEY `fk_resuelve_usuario` (`idUsu`),
  CONSTRAINT `fk_resuelve_incidencia` FOREIGN KEY (`idInci`) REFERENCES `incidencia` (`idInci`),
  CONSTRAINT `fk_resuelve_usuario` FOREIGN KEY (`idUsu`) REFERENCES `usuario` (`idUsu`),
  CONSTRAINT `chk_resuelve_estado` CHECK (`estIntento` in ('abierto','en proceso','cerrado')),
  CONSTRAINT `chk_resuelve_descripcion` CHECK (char_length(`descEstado`) <= 200)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resuelve`
--

LOCK TABLES `resuelve` WRITE;
/*!40000 ALTER TABLE `resuelve` DISABLE KEYS */;
/*!40000 ALTER TABLE `resuelve` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ruta`
--

LOCK TABLES `ruta` WRITE;
/*!40000 ALTER TABLE `ruta` DISABLE KEYS */;
INSERT INTO `ruta` VALUES (1,'Diaria - Municipio B',1),(2,'Nocturna - Municipio CH',1);
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
  `passwordHash` varchar(255) NOT NULL,
  `rol` varchar(30) NOT NULL,
  `estUsu` varchar(20) NOT NULL,
  PRIMARY KEY (`idUsu`),
  UNIQUE KEY `uq_usuario_email` (`email`),
  CONSTRAINT `chk_usuario_rol` CHECK (`rol` in ('operario','municipal','cuadrilla','administrador')),
  CONSTRAINT `chk_usuario_estado` CHECK (`estUsu` in ('activo','inactivo'))
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (1,'Administrador SiGeRU','099000001','admin@sigeru.local','$2y$10$fPgqe4UAFA6lmZiqhTHySeNdTBHh512m0w44sGZdGBKvtuY8vGVeS','administrador','activo'),(3,'Jorge','093974462','jorge@gmail.com','$2y$10$M/UssDv52wDKk/JNb00heeIhVYFgEyCHIZbdUmNZwDQNPndMyThZK','cuadrilla','activo'),(4,'Funcionario Municipal','099000002','municipal@sigeru.local','$2y$10$fPgqe4UAFA6lmZiqhTHySeNdTBHh512m0w44sGZdGBKvtuY8vGVeS','municipal','activo'),(5,'Jorge Chofer','099000003','chofer@sigeru.local','$2y$10$fPgqe4UAFA6lmZiqhTHySeNdTBHh512m0w44sGZdGBKvtuY8vGVeS','cuadrilla','activo'),(6,'Carlos Peon','099000004','peon@sigeru.local','$2y$10$fPgqe4UAFA6lmZiqhTHySeNdTBHh512m0w44sGZdGBKvtuY8vGVeS','cuadrilla','activo'),(7,'Roberto Operario','099000005','operario@sigeru.local','$2y$10$fPgqe4UAFA6lmZiqhTHySeNdTBHh512m0w44sGZdGBKvtuY8vGVeS','operario','activo');
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehiculo`
--

DROP TABLE IF EXISTS `vehiculo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehiculo` (
  `idVehi` int(11) NOT NULL AUTO_INCREMENT,
  `tipoVehi` varchar(30) NOT NULL,
  `matriculaVehi` varchar(10) NOT NULL,
  `marcaVehi` varchar(50) NOT NULL,
  `modeloVehi` varchar(50) NOT NULL,
  `capVehi` decimal(10,2) NOT NULL,
  `estVehi` varchar(20) NOT NULL,
  PRIMARY KEY (`idVehi`),
  UNIQUE KEY `uq_vehiculo_matricula` (`matriculaVehi`),
  CONSTRAINT `chk_vehiculo_tipo` CHECK (`tipoVehi` in ('comunitario','intradomiciliario','limpieza','centroAcopio')),
  CONSTRAINT `chk_vehiculo_estado` CHECK (`estVehi` in ('disponible','en uso','roto')),
  CONSTRAINT `chk_vehiculo_capacidad` CHECK (`capVehi` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehiculo`
--

LOCK TABLES `vehiculo` WRITE;
/*!40000 ALTER TABLE `vehiculo` DISABLE KEYS */;
INSERT INTO `vehiculo` VALUES (1,'comunitario','STP1234','Volkswagen','Constellation',12000.00,'disponible'),(2,'limpieza','STP5678','Mercedes-Benz','Atego',9000.00,'en uso'),(3,'intradomiciliario','STP9012','Iveco','Tector',7500.00,'disponible');
/*!40000 ALTER TABLE `vehiculo` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vertedero`
--

LOCK TABLES `vertedero` WRITE;
/*!40000 ALTER TABLE `vertedero` DISABLE KEYS */;
INSERT INTO `vertedero` VALUES (1,'Camino Felipe Cardoso km 4.5, Montevideo'),(2,'Ruta 1 y Camino Tomkinson, Montevideo');
/*!40000 ALTER TABLE `vertedero` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'sigeru'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-11 16:39:33
