-- Tabla de metadatos para extender leads
CREATE TABLE IF NOT EXISTS `{prefix}leads_metadata` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `lead_id` bigint(20) NOT NULL,
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
  KEY `lead_id` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de etiquetas
CREATE TABLE IF NOT EXISTS `{prefix}leads_tags` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de relación entre leads y etiquetas
CREATE TABLE IF NOT EXISTS `{prefix}leads_tag_relationships` (
  `lead_id` bigint(20) NOT NULL,
  `tag_id` bigint(20) NOT NULL,
  PRIMARY KEY (`lead_id`,`tag_id`),
  KEY `tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de seguimientos por evento
CREATE TABLE IF NOT EXISTS `{prefix}jet_cct_event_followups` (
  `_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) NOT NULL,
  `lead_id` bigint(20) NOT NULL,
  `cotizacion_id` bigint(20) DEFAULT NULL,
  `seguimiento` longtext,
  `cct_status` varchar(20) DEFAULT 'publish',
  `cct_created` datetime NOT NULL,
  `cct_modified` datetime NOT NULL,
  PRIMARY KEY (`_ID`),
  KEY `event_id` (`event_id`),
  KEY `lead_id` (`lead_id`),
  KEY `cotizacion_id` (`cotizacion_id`),
  KEY `event_lead` (`event_id`,`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;