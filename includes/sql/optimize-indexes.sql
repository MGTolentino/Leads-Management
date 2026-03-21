-- Optimization script for Leads Management database
-- Run these queries to improve performance
-- Version: 2.2.0

-- Add indexes for leads table
ALTER TABLE `wp_jet_cct_leads` 
ADD INDEX IF NOT EXISTS `idx_email` (`email`),
ADD INDEX IF NOT EXISTS `idx_telefono` (`telefono`),
ADD INDEX IF NOT EXISTS `idx_ejecutivo` (`ejecutivo`),
ADD INDEX IF NOT EXISTS `idx_estatus` (`estatus`),
ADD INDEX IF NOT EXISTS `idx_created` (`cct_created`),
ADD INDEX IF NOT EXISTS `idx_nombre_apellido` (`nombre`, `apellido`);

-- Add composite index for common queries
ALTER TABLE `wp_jet_cct_leads`
ADD INDEX IF NOT EXISTS `idx_status_created` (`estatus`, `cct_created`);

-- Add indexes for eventos table
ALTER TABLE `wp_jet_cct_eventos`
ADD INDEX IF NOT EXISTS `idx_lead_id` (`lead_id`),
ADD INDEX IF NOT EXISTS `idx_fecha_evento` (`fecha_de_evento`),
ADD INDEX IF NOT EXISTS `idx_tipo_evento` (`tipo_de_evento`),
ADD INDEX IF NOT EXISTS `idx_evento_status` (`evento_status`),
ADD INDEX IF NOT EXISTS `idx_salon` (`salon`);

-- Add composite indexes for common join queries
ALTER TABLE `wp_jet_cct_eventos`
ADD INDEX IF NOT EXISTS `idx_lead_date` (`lead_id`, `fecha_de_evento`),
ADD INDEX IF NOT EXISTS `idx_status_type` (`evento_status`, `tipo_de_evento`),
ADD INDEX IF NOT EXISTS `idx_date_status` (`fecha_de_evento`, `evento_status`);

-- Add index for date range queries
ALTER TABLE `wp_jet_cct_eventos`
ADD INDEX IF NOT EXISTS `idx_date_range` (`fecha_de_evento`, `lead_id`, `evento_status`);

-- Optimize tables after adding indexes
OPTIMIZE TABLE `wp_jet_cct_leads`;
OPTIMIZE TABLE `wp_jet_cct_eventos`;

-- Update table statistics
ANALYZE TABLE `wp_jet_cct_leads`;
ANALYZE TABLE `wp_jet_cct_eventos`;