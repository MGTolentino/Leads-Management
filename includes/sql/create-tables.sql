-- Tabla de metadatos para extender leads
CREATE TABLE IF NOT EXISTS `{prefix}ltb_leads_metadata` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `lead_id` bigint(20) NOT NULL,
  `evento_id` bigint(20) DEFAULT NULL,
  `prioridad` varchar(50) DEFAULT NULL,
  `valor_potencial` varchar(50) DEFAULT NULL,
  `probabilidad` varchar(50) DEFAULT NULL,
  `responsable_id` bigint(20) DEFAULT NULL,
  `ultima_interaccion` datetime DEFAULT NULL,
  `proxima_accion_fecha` datetime DEFAULT NULL,
  `fuente` varchar(100) DEFAULT NULL,
  `campana` varchar(100) DEFAULT NULL,
  `ubicacion` varchar(100) DEFAULT NULL,
  `industria` varchar(100) DEFAULT NULL,
  `estado_propuesta` varchar(50) DEFAULT NULL,
  `rango_cotizacion` varchar(50) DEFAULT NULL,
  `temporada` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_id` (`lead_id`),
  KEY `evento_id` (`evento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de etiquetas
CREATE TABLE IF NOT EXISTS `{prefix}ltb_leads_tags` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de relación entre leads y etiquetas
CREATE TABLE IF NOT EXISTS `{prefix}ltb_leads_tag_relationships` (
  `lead_id` bigint(20) NOT NULL,
  `tag_id` bigint(20) NOT NULL,
  PRIMARY KEY (`lead_id`,`tag_id`),
  KEY `tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;