-- MySQL dump 10.13  Distrib 8.0.46, for Win64 (x86_64)
--
-- Host: localhost    Database: sistema
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `citas`
--

DROP TABLE IF EXISTS `citas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `citas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `paciente_id` int NOT NULL,
  `doctor_id` int NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `modalidad` enum('presencial','virtual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'presencial',
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('pendiente','confirmada','atendida','cancelada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `turno_activo` tinyint GENERATED ALWAYS AS ((case when (`estado` = _utf8mb4'cancelada') then NULL else 1 end)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_doctor_fecha_hora_activa` (`doctor_id`,`fecha`,`hora`,`turno_activo`),
  KEY `idx_citas_paciente` (`paciente_id`),
  KEY `idx_citas_doctor` (`doctor_id`),
  KEY `idx_citas_fecha` (`fecha`),
  KEY `idx_citas_estado` (`estado`),
  CONSTRAINT `fk_cita_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_cita_paciente` FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `citas`
--

LOCK TABLES `citas` WRITE;
/*!40000 ALTER TABLE `citas` DISABLE KEYS */;
INSERT INTO `citas` (`id`, `paciente_id`, `doctor_id`, `fecha`, `hora`, `modalidad`, `motivo`, `estado`, `observaciones`, `fecha_registro`) VALUES (1,1,1,'2026-10-05','08:00:00','presencial','Dolor en el pecho','atendida',NULL,'2026-09-30 14:19:33'),(2,2,2,'2026-10-05','13:00:00','presencial','Consulta médica general','confirmada',NULL,'2026-09-30 14:19:33'),(3,2,2,'2026-10-09','18:00:00','presencial','','cancelada',NULL,'2026-09-30 14:29:54'),(4,2,1,'2026-10-02','10:00:00','presencial','dolor de columna','confirmada',NULL,'2026-09-30 20:20:24'),(5,2,2,'2026-10-01','13:00:00','presencial','','pendiente',NULL,'2026-09-30 20:35:49'),(6,2,4,'2026-10-03','12:00:00','presencial','consulta médica','pendiente',NULL,'2026-10-01 02:26:25'),(7,2,1,'2026-10-03','08:00:00','presencial','consulta','pendiente',NULL,'2026-10-01 02:27:49'),(8,2,3,'2026-10-01','08:00:00','virtual','dolor de muela','pendiente',NULL,'2026-10-01 03:40:29'),(9,2,3,'2026-10-02','08:00:00','virtual','dolor lumbar','pendiente',NULL,'2026-10-01 04:06:30'),(10,2,1,'2026-10-16','10:00:00','virtual','','pendiente',NULL,'2026-10-01 04:46:47'),(11,2,1,'2026-10-03','08:30:00','presencial','dolor de pecho','pendiente',NULL,'2026-10-02 14:35:48');
/*!40000 ALTER TABLE `citas` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-11 10:10:04
