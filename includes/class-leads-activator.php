<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para manejar la activación y actualización del plugin
 */
class LTB_Leads_Activator {
    
    /**
     * Método de activación
     */
    public static function activate() {
        // Crear tablas de base de datos
        self::create_tables();
        
        // Registrar versión
        update_option('ltb_leads_db_version', LTB_LEADS_VERSION);
        
        // Activar enrutador
        if (class_exists('LTB_Leads_Router')) {
            LTB_Leads_Router::activate();
        }
        
        // Limpiar caché
        flush_rewrite_rules();
        wp_cache_flush();
    }
    
    /**
     * Método de desactivación
     */
    public static function deactivate() {
        flush_rewrite_rules();
        wp_cache_flush();
    }
    
    /**
     * Método de actualización
     */
    public static function update() {
        $current_version = get_option('ltb_leads_db_version', '0.0.0');
        
        if (version_compare($current_version, LTB_LEADS_VERSION, '<')) {
            // Actualizar tablas
            self::create_tables();
            
            // Ejecutar migraciones específicas por versión
            self::run_migrations($current_version);
            
            // Actualizar versión
            update_option('ltb_leads_db_version', LTB_LEADS_VERSION);
        }
    }
    
    /**
     * Crear tablas necesarias
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Archivo SQL con la definición de las tablas
        $sql_file = LTB_LEADS_PLUGIN_DIR . 'includes/sql/create-tables.sql';
        
        if (file_exists($sql_file)) {
            $sql = file_get_contents($sql_file);
            
            // Reemplazar placeholder de prefijo
            $sql = str_replace('{prefix}', $wpdb->prefix, $sql);
            
            // Dividir las sentencias SQL
            $statements = explode(';', $sql);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $wpdb->query($statement);
                }
            }
        }
    }
    
    /**
     * Ejecutar migraciones específicas por versión
     */
    private static function run_migrations($from_version) {
        // Ejemplo de migración específica por versión
        if (version_compare($from_version, '1.5.0', '<')) {
            // Migración para versiones anteriores a 1.5.0
        }
        
        if (version_compare($from_version, '2.0.0', '<')) {
            // Migración para versiones anteriores a 2.0.0
            // Por ejemplo, migrar datos existentes a las nuevas tablas de metadatos
            self::migrate_existing_data_to_metadata();
        }
        
        if (version_compare($from_version, '2.1.0', '<')) {
            // Crear nueva tabla de seguimientos por evento
            self::create_event_followups_table();
        }
    }
    
    /**
     * Migrar datos existentes a las nuevas tablas de metadatos
     */
    private static function migrate_existing_data_to_metadata() {
        global $wpdb;
        
        // Comprobar si hay leads sin metadatos
        $leads_table = $wpdb->prefix . 'jet_cct_leads';
        $metadata_table = $wpdb->prefix . 'leads_metadata';
        
        $leads = $wpdb->get_results("
            SELECT l._ID as lead_id
            FROM {$leads_table} l
            LEFT JOIN {$metadata_table} m ON l._ID = m.lead_id
            WHERE m.id IS NULL
        ");
        
        if (empty($leads)) {
            return;
        }
        
        // Crear registro de metadatos para cada lead
        foreach ($leads as $lead) {
            $wpdb->insert(
                $metadata_table,
                array(
                    'lead_id' => $lead->lead_id,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                )
            );
        }
    }
    
    /**
     * Crear tabla específica de seguimientos por evento
     */
    private static function create_event_followups_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'jet_cct_event_followups';
        $charset_collate = $wpdb->get_charset_collate();
        
        // Verificar si la tabla ya existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        
        if (!$table_exists) {
            $sql = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
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
            ) {$charset_collate};";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
}