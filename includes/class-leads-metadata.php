<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para gestionar los metadatos adicionales de leads
 */
class LTB_Leads_Metadata {
    private $wpdb;
    private $metadata_table;
    private $tags_table;
    private $tag_relationships_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->metadata_table = $wpdb->prefix . 'leads_metadata';
        $this->tags_table = $wpdb->prefix . 'leads_tags';
        $this->tag_relationships_table = $wpdb->prefix . 'leads_tag_relationships';
        
        $this->init_hooks();
    }
    
    /**
     * Inicializa los hooks
     */
    private function init_hooks() {
        // Agregar hook para guardar metadatos cuando se guarda un lead
        add_action('ltb_lead_saved', array($this, 'save_lead_metadata'), 10, 2);
        
        // Agregar hook para filtrar consultas de leads
        add_filter('ltb_leads_query_args', array($this, 'filter_leads_query_args'), 10, 1);
        add_filter('ltb_leads_query_where', array($this, 'filter_leads_query_where'), 10, 2);
        add_filter('ltb_leads_query_join', array($this, 'filter_leads_query_join'), 10, 2);
    }
    
    /**
     * Obtiene los metadatos de un lead
     */
    public function get_lead_metadata($lead_id) {
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->metadata_table} WHERE lead_id = %d LIMIT 1",
            $lead_id
        );
        
        $metadata = $this->wpdb->get_row($query);
        
        // Si no existe, retornar objeto vacío
        if (!$metadata) {
            $metadata = new stdClass();
        }
        
        // Obtener etiquetas
        $tags_query = $this->wpdb->prepare(
            "SELECT t.* FROM {$this->tags_table} t
            JOIN {$this->tag_relationships_table} tr ON t.id = tr.tag_id
            WHERE tr.lead_id = %d",
            $lead_id
        );
        
        $tags = $this->wpdb->get_results($tags_query);
        $metadata->tags = $tags;
        
        return $metadata;
    }
    
    /**
     * Guarda los metadatos de un lead
     */
    public function save_lead_metadata($lead_id, $data) {
        // Extraer metadatos del array de datos
        $metadata = array(
            'lead_id' => $lead_id,
            'evento_id' => isset($data['evento_id']) ? $data['evento_id'] : null,
            'prioridad' => isset($data['prioridad']) ? $data['prioridad'] : null,
            'valor_potencial' => isset($data['valor_potencial']) ? $data['valor_potencial'] : null,
            'probabilidad' => isset($data['probabilidad']) ? $data['probabilidad'] : null,
            'responsable_id' => isset($data['responsable_id']) ? $data['responsable_id'] : null,
            'ultima_interaccion' => isset($data['ultima_interaccion']) ? $data['ultima_interaccion'] : null,
            'proxima_accion_fecha' => isset($data['proxima_accion_fecha']) ? $data['proxima_accion_fecha'] : null,
            'fuente' => isset($data['fuente']) ? $data['fuente'] : null,
            'campana' => isset($data['campana']) ? $data['campana'] : null,
            'ubicacion' => isset($data['ubicacion']) ? $data['ubicacion'] : null,
            'industria' => isset($data['industria']) ? $data['industria'] : null,
            'estado_propuesta' => isset($data['estado_propuesta']) ? $data['estado_propuesta'] : null,
            'rango_cotizacion' => isset($data['rango_cotizacion']) ? $data['rango_cotizacion'] : null,
            'temporada' => isset($data['temporada']) ? $data['temporada'] : null,
            'servicios_requeridos' => isset($data['servicios_requeridos']) ? maybe_serialize($data['servicios_requeridos']) : null,
            'venue' => isset($data['venue']) ? $data['venue'] : null,
            'updated_at' => current_time('mysql')
        );
        
        // Verificar si ya existe un registro para este lead
        $existing_id = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->metadata_table} WHERE lead_id = %d",
            $lead_id
        ));
        
        if ($existing_id) {
            // Actualizar registro existente
            $this->wpdb->update(
                $this->metadata_table,
                $metadata,
                array('id' => $existing_id)
            );
        } else {
            // Crear nuevo registro
            $metadata['created_at'] = current_time('mysql');
            $this->wpdb->insert($this->metadata_table, $metadata);
        }
        
        // Guardar etiquetas si existen
        if (isset($data['etiquetas']) && is_array($data['etiquetas'])) {
            $this->save_lead_tags($lead_id, $data['etiquetas']);
        }
    }
    
    /**
     * Guarda las etiquetas de un lead
     */
    private function save_lead_tags($lead_id, $tags) {
        // Eliminar relaciones actuales
        $this->wpdb->delete(
            $this->tag_relationships_table, 
            array('lead_id' => $lead_id)
        );
        
        if (empty($tags)) {
            return;
        }
        
        foreach ($tags as $tag_name) {
            // Sanitizar y crear slug
            $tag_name = trim($tag_name);
            $tag_slug = sanitize_title($tag_name);
            
            if (empty($tag_slug)) {
                continue;
            }
            
            // Verificar si la etiqueta existe
            $tag_id = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$this->tags_table} WHERE slug = %s",
                $tag_slug
            ));
            
            // Si no existe, crearla
            if (!$tag_id) {
                $this->wpdb->insert(
                    $this->tags_table,
                    array(
                        'name' => $tag_name,
                        'slug' => $tag_slug,
                        'created_at' => current_time('mysql')
                    )
                );
                
                $tag_id = $this->wpdb->insert_id;
            }
            
            // Crear relación
            $this->wpdb->insert(
                $this->tag_relationships_table,
                array(
                    'lead_id' => $lead_id,
                    'tag_id' => $tag_id
                )
            );
        }
    }
    
    /**
     * Elimina los metadatos de un lead
     */
    public function delete_lead_metadata($lead_id) {
        // Eliminar metadatos
        $this->wpdb->delete(
            $this->metadata_table, 
            array('lead_id' => $lead_id)
        );
        
        // Eliminar relaciones de etiquetas
        $this->wpdb->delete(
            $this->tag_relationships_table, 
            array('lead_id' => $lead_id)
        );
    }
    
    /**
     * Filtra los argumentos de consulta para leads
     */
    public function filter_leads_query_args($args) {
        // No es necesario modificar los args
        return $args;
    }
    
    /**
     * Filtra la cláusula WHERE de la consulta de leads
     */
    public function filter_leads_query_where($where, $args) {
        $conditions = array();
        $values = array();
        
        // Agregar condiciones para cada filtro de metadatos
        
        // Prioridad
        if (!empty($args['prioridad'])) {
            $conditions[] = "m.prioridad = %s";
            $values[] = $args['prioridad'];
        }
        
        // Valor potencial
        if (!empty($args['valor_potencial'])) {
            $conditions[] = "m.valor_potencial = %s";
            $values[] = $args['valor_potencial'];
        }
        
        // Probabilidad
        if (!empty($args['probabilidad'])) {
            $conditions[] = "m.probabilidad = %s";
            $values[] = $args['probabilidad'];
        }
        
        // Responsable
        if (!empty($args['responsable']) && is_array($args['responsable'])) {
            $placeholders = array();
            foreach ($args['responsable'] as $responsable_id) {
                $placeholders[] = '%d';
                $values[] = $responsable_id;
            }
            if (!empty($placeholders)) {
                $conditions[] = "m.responsable_id IN (" . implode(',', $placeholders) . ")";
            }
        }
        
        // Última interacción
        if (!empty($args['ultima_interaccion'])) {
            $now = current_time('mysql');
            $date = new DateTime($now);
            
            switch ($args['ultima_interaccion']) {
                case 'hoy':
                    $start = $date->format('Y-m-d 00:00:00');
                    $conditions[] = "m.ultima_interaccion >= %s";
                    $values[] = $start;
                    break;
                    
                case 'semana':
                    $date->modify('-7 days');
                    $start = $date->format('Y-m-d 00:00:00');
                    $conditions[] = "m.ultima_interaccion >= %s";
                    $values[] = $start;
                    break;
                    
                case 'mes':
                    $date->modify('-30 days');
                    $start = $date->format('Y-m-d 00:00:00');
                    $conditions[] = "m.ultima_interaccion >= %s";
                    $values[] = $start;
                    break;
                    
                case 'trimestre':
                    $date->modify('-90 days');
                    $start = $date->format('Y-m-d 00:00:00');
                    $conditions[] = "m.ultima_interaccion >= %s";
                    $values[] = $start;
                    break;
                    
                case 'mas_3_meses':
                    $date->modify('-90 days');
                    $start = $date->format('Y-m-d 00:00:00');
                    $conditions[] = "m.ultima_interaccion < %s";
                    $values[] = $start;
                    break;
            }
        }
        
        // Tiempo sin actividad
        if (!empty($args['tiempo_sin_actividad'])) {
            $now = current_time('mysql');
            $date = new DateTime($now);
            
            switch ($args['tiempo_sin_actividad']) {
                case '7d':
                    $date->modify('-7 days');
                    $limit = $date->format('Y-m-d 00:00:00');
                    $conditions[] = "(m.ultima_interaccion IS NULL OR m.ultima_interaccion < %s)";
                    $values[] = $limit;
                    break;
                    
                case '15d':
                    $date->modify('-15 days');
                    $limit = $date->format('Y-m-d 00:00:00');
                    $conditions[] = "(m.ultima_interaccion IS NULL OR m.ultima_interaccion < %s)";
                    $values[] = $limit;
                    break;
                    
                case '30d':
                    $date->modify('-30 days');
                    $limit = $date->format('Y-m-d 00:00:00');
                    $conditions[] = "(m.ultima_interaccion IS NULL OR m.ultima_interaccion < %s)";
                    $values[] = $limit;
                    break;
                    
                case '90d':
                    $date->modify('-90 days');
                    $limit = $date->format('Y-m-d 00:00:00');
                    $conditions[] = "(m.ultima_interaccion IS NULL OR m.ultima_interaccion < %s)";
                    $values[] = $limit;
                    break;
            }
        }
        
        // Próxima acción
        if (!empty($args['proxima_accion'])) {
            $now = current_time('mysql');
            $date = new DateTime($now);
            
            switch ($args['proxima_accion']) {
                case 'hoy':
                    $start = $date->format('Y-m-d 00:00:00');
                    $end = $date->format('Y-m-d 23:59:59');
                    $conditions[] = "m.proxima_accion_fecha BETWEEN %s AND %s";
                    $values[] = $start;
                    $values[] = $end;
                    break;
                    
                case 'manana':
                    $date->modify('+1 day');
                    $start = $date->format('Y-m-d 00:00:00');
                    $end = $date->format('Y-m-d 23:59:59');
                    $conditions[] = "m.proxima_accion_fecha BETWEEN %s AND %s";
                    $values[] = $start;
                    $values[] = $end;
                    break;
                    
                case 'semana':
                    $today = $date->format('Y-m-d 00:00:00');
                    $date->modify('sunday this week');
                    $end = $date->format('Y-m-d 23:59:59');
                    $conditions[] = "m.proxima_accion_fecha BETWEEN %s AND %s";
                    $values[] = $today;
                    $values[] = $end;
                    break;
                    
                case 'mes':
                    $today = $date->format('Y-m-d 00:00:00');
                    $date->modify('last day of this month');
                    $end = $date->format('Y-m-d 23:59:59');
                    $conditions[] = "m.proxima_accion_fecha BETWEEN %s AND %s";
                    $values[] = $today;
                    $values[] = $end;
                    break;
                    
                case 'sin_programar':
                    $conditions[] = "m.proxima_accion_fecha IS NULL";
                    break;
            }
        }
        
        // Fuente
        if (!empty($args['fuente']) && is_array($args['fuente'])) {
            $placeholders = array();
            foreach ($args['fuente'] as $fuente) {
                $placeholders[] = '%s';
                $values[] = $fuente;
            }
            if (!empty($placeholders)) {
                $conditions[] = "m.fuente IN (" . implode(',', $placeholders) . ")";
            }
        }
        
        // Campaña
        if (!empty($args['campana']) && is_array($args['campana'])) {
            $placeholders = array();
            foreach ($args['campana'] as $campana) {
                $placeholders[] = '%s';
                $values[] = $campana;
            }
            if (!empty($placeholders)) {
                $conditions[] = "m.campana IN (" . implode(',', $placeholders) . ")";
            }
        }
        
        // Ubicación
        if (!empty($args['ubicacion']) && is_array($args['ubicacion'])) {
            $placeholders = array();
            foreach ($args['ubicacion'] as $ubicacion) {
                $placeholders[] = '%s';
                $values[] = $ubicacion;
            }
            if (!empty($placeholders)) {
                $conditions[] = "m.ubicacion IN (" . implode(',', $placeholders) . ")";
            }
        }
        
        // Industria
        if (!empty($args['industria']) && is_array($args['industria'])) {
            $placeholders = array();
            foreach ($args['industria'] as $industria) {
                $placeholders[] = '%s';
                $values[] = $industria;
            }
            if (!empty($placeholders)) {
                $conditions[] = "m.industria IN (" . implode(',', $placeholders) . ")";
            }
        }
        
        // Estado de propuesta
        if (!empty($args['estado_propuesta'])) {
            $conditions[] = "m.estado_propuesta = %s";
            $values[] = $args['estado_propuesta'];
        }
        
        // Rango de cotización
        if (!empty($args['rango_cotizacion'])) {
            $conditions[] = "m.rango_cotizacion = %s";
            $values[] = $args['rango_cotizacion'];
        }
        
        // Temporada
        if (!empty($args['temporada'])) {
            $conditions[] = "m.temporada = %s";
            $values[] = $args['temporada'];
        }
        
        // Servicios requeridos
        if (!empty($args['servicios_requeridos']) && is_array($args['servicios_requeridos'])) {
            $servicios_conditions = array();
            foreach ($args['servicios_requeridos'] as $servicio) {
                $servicios_conditions[] = "m.servicios_requeridos LIKE %s";
                $values[] = '%' . $this->wpdb->esc_like($servicio) . '%';
            }
            if (!empty($servicios_conditions)) {
                $conditions[] = "(" . implode(' OR ', $servicios_conditions) . ")";
            }
        }
        
        // Venue
        if (!empty($args['venue']) && is_array($args['venue'])) {
            $placeholders = array();
            foreach ($args['venue'] as $venue) {
                $placeholders[] = '%s';
                $values[] = $venue;
            }
            if (!empty($placeholders)) {
                $conditions[] = "m.venue IN (" . implode(',', $placeholders) . ")";
            }
        }
        
        // Etiquetas
        if (!empty($args['etiquetas']) && is_array($args['etiquetas'])) {
            global $wpdb;
            
            $etiquetas_count = count($args['etiquetas']);
            $tag_subquery = "l._ID IN (
                SELECT tr.lead_id 
                FROM {$wpdb->prefix}leads_tag_relationships tr
                JOIN {$wpdb->prefix}leads_tags t ON tr.tag_id = t.id
                WHERE t.name IN (" . implode(',', array_fill(0, $etiquetas_count, '%s')) . ")
                GROUP BY tr.lead_id
                HAVING COUNT(DISTINCT t.id) = %d
            )";
            
            $conditions[] = $tag_subquery;
            
            foreach ($args['etiquetas'] as $etiqueta) {
                $values[] = $etiqueta;
            }
            $values[] = $etiquetas_count;
        }
        
        // Construir cláusula WHERE
        if (!empty($conditions)) {
            // Preparar la consulta
            $conditions_str = implode(' AND ', $conditions);
            
            // Si hay valores, prepararlos
            if (!empty($values)) {
                global $wpdb;
                $conditions_str = $wpdb->prepare($conditions_str, $values);
            }
            
            // Agregar a la cláusula WHERE existente
            if (!empty($where)) {
                $where .= " AND " . $conditions_str;
            } else {
                $where = $conditions_str;
            }
        }
        
        return $where;
    }
    
    /**
     * Filtra la cláusula JOIN de la consulta de leads
     */
    public function filter_leads_query_join($join, $args) {
        global $wpdb;
        
        // Verificar si hay filtros de metadatos activos
        $metadata_filters_active = false;
        $metadata_fields = array(
            'prioridad', 'valor_potencial', 'probabilidad', 'responsable', 
            'ultima_interaccion', 'tiempo_sin_actividad', 'proxima_accion', 
            'fuente', 'campana', 'ubicacion', 'industria', 'estado_propuesta', 
            'rango_cotizacion', 'temporada', 'servicios_requeridos', 'venue'
        );
        
        foreach ($metadata_fields as $field) {
            if (!empty($args[$field])) {
                $metadata_filters_active = true;
                break;
            }
        }
        
        // Agregar JOIN a tabla de metadatos si hay filtros activos
        if ($metadata_filters_active) {
            $join .= " LEFT JOIN {$wpdb->prefix}ltb_leads_metadata m ON m.lead_id = l._ID";
        }
        
        return $join;
    }
    
    /**
     * Activa las tablas necesarias
     */
    public static function activate() {
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
}