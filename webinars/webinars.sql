-- Adminer 4.8.1 MySQL 10.11.11-MariaDB-0+deb12u1 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `webinars`;
CREATE TABLE `webinars` (
  `webinar_id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `categoria` varchar(100) NOT NULL,
  `fecha` datetime NOT NULL,
  `duracion` varchar(50) DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`webinar_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `webinars` (`webinar_id`, `titulo`, `descripcion`, `precio`, `categoria`, `fecha`, `duracion`, `imagen`, `activo`) VALUES
(1,	'Clima Cálido',	'Curso sobre comportamiento del concreto en zonas de clima cálido.',	1500.00,	'Certificaciones IMCYC',	'2025-07-15 10:00:00',	'3h',	'webinars/clima_calido.jpg',	1),
(2,	'Clima Frío',	'Curso sobre medidas para colocación de concreto en clima frío.',	1500.00,	'Certificaciones IMCYC',	'2025-07-20 10:00:00',	'3h',	'webinars/clima_frio.jpg',	1),
(3,	'Durabilidad del Concreto',	'Factores y soluciones para prolongar la vida útil del concreto.',	1500.00,	'Certificaciones IMCYC',	'2025-07-25 10:00:00',	'4h',	'webinars/durabilidad.jpg',	1),
(4,	'Tecnología de los Agregados para Concreto',	'Curso técnico sobre tipos y propiedades de agregados.',	1500.00,	'Certificaciones IMCYC',	'2025-07-30 10:00:00',	'3.5h',	'webinars/agregados.jpg',	1),
(5,	'Pisos Industriales de Concreto',	'Diseño y ejecución de pisos industriales.',	1500.00,	'Certificaciones IMCYC',	'2025-08-02 10:00:00',	'5h',	'webinars/pisos_industriales.jpg',	1),
(6,	'Fundamentos del Concreto',	'Bases esenciales del concreto, materiales y preparación.',	1500.00,	'Certificaciones IMCYC',	'2025-08-05 10:00:00',	'3h',	'webinars/fundamentos_concreto.jpg',	1),
(7,	'Reforzamiento Técnico',	'Técnicas de refuerzo estructural en concreto.',	1500.00,	'Certificaciones IMCYC',	'2025-08-07 10:00:00',	'3h',	'webinars/reforzamiento.jpg',	1),
(8,	'Estrategia Comercial',	'Aplicación comercial de los servicios técnicos en la construcción.',	1500.00,	'Certificaciones IMCYC',	'2025-08-10 10:00:00',	'2.5h',	'webinars/estrategia.jpg',	1),
(9,	'Manual de Habilitado del Acero de Refuerzo',	'Curso práctico para habilitar acero de refuerzo.',	1500.00,	'Certificaciones IMCYC',	'2025-08-12 10:00:00',	'3h',	'webinars/habilitado.jpg',	1),
(10,	'Fundamentos del Cemento',	'Conoce las propiedades básicas del cemento en construcción.',	1500.00,	'Certificaciones IMCYC',	'2025-08-15 10:00:00',	'2h',	'webinars/fundamentos_cemento.jpg',	1);

-- 2025-06-26 19:02:24
