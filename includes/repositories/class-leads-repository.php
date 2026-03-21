<?php
/**
 * Repository Implementation for Leads
 * Maneja todo el acceso a datos con optimizaciones
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once LTB_LEADS_PLUGIN_DIR . 'includes/interfaces/interface-leads-repository.php';

class LTB_Leads_Repository implements ILeadsRepository {
    
    private $wpdb;
    private $leads_table;
    private $events_table;
    private $cache_group = 'ltb_leads';
    private $cache_ttl = 300; // 5 minutos
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->leads_table = $wpdb->prefix . 'jet_cct_leads';
        $this->events_table = $wpdb->prefix . 'jet_cct_eventos';
    }
    
    /**
     * Buscar lead por ID con cache
     */
    public function find($id) {
        $cache_key = 'lead_' . $id;
        $cached = wp_cache_get($cache_key, $this->cache_group);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $query = $this->wpdb->prepare("
            SELECT l.*, e.*,
                   CONCAT(l.lead_nombre, ' ', l.lead_apellido) as nombre_completo
            FROM {$this->leads_table} l
            LEFT JOIN {$this->events_table} e ON l._ID = e.lead_id
            WHERE l._ID = %d
            AND l.lead_status = 'publish'
        ", $id);
        
        $result = $this->wpdb->get_row($query);
        
        if ($result) {
            wp_cache_set($cache_key, $result, $this->cache_group, $this->cache_ttl);
        }
        
        return $result;
    }
    
    /**
     * Obtener todos los leads con filtros optimizados
     */
    public function findAll($filters = array()) {
        $cache_key = 'leads_all_' . md5(serialize($filters));
        $cached = wp_cache_get($cache_key, $this->cache_group);
        
        if (false !== $cached) {
            return $cached;
        }
        
        // Construir query base con índices optimizados
        $query = "
            SELECT SQL_CALC_FOUND_ROWS 
                   l._ID as lead_id,
                   l.lead_nombre,
                   l.lead_apellido,
                   CONCAT(l.lead_nombre, ' ', l.lead_apellido) as nombre_completo,
                   l.lead_celular,
                   l.lead_e_mail,
                   l.lead_razon_social,
                   l.cct_created as lead_created,
                   e._ID as evento_id,
                   e.fecha_de_evento,
                   e.tipo_de_evento,
                   e.evento_status,
                   e.evento_asistentes,
                   e.evento_servicio_de_interes
            FROM {$this->leads_table} l
            LEFT JOIN {$this->events_table} e ON l._ID = e.lead_id
            WHERE l.lead_status = 'publish'
        ";
        
        $where_clauses = array();
        $values = array();
        
        // Aplicar filtros
        if (!empty($filters['search'])) {
            $search = '%' . $this->wpdb->esc_like($filters['search']) . '%';
            $where_clauses[] = "(l.lead_nombre LIKE %s OR l.lead_apellido LIKE %s OR l.lead_e_mail LIKE %s OR l.lead_celular LIKE %s)";
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }
        
        if (!empty($filters['status'])) {
            $where_clauses[] = "e.evento_status = %s";
            $values[] = $filters['status'];
        }
        
        if (!empty($filters['event_type'])) {
            $where_clauses[] = "e.tipo_de_evento = %s";
            $values[] = $filters['event_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "e.fecha_de_evento >= %s";
            $values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "e.fecha_de_evento <= %s";
            $values[] = $filters['date_to'];
        }
        
        if (!empty($where_clauses)) {
            $query .= " AND " . implode(" AND ", $where_clauses);
        }
        
        // Ordenamiento optimizado
        $order_by = !empty($filters['order_by']) ? $filters['order_by'] : 'l.cct_created';
        $order = !empty($filters['order']) ? $filters['order'] : 'DESC';
        $query .= " ORDER BY {$order_by} {$order}";
        
        // Paginación
        if (!empty($filters['limit'])) {
            $offset = !empty($filters['offset']) ? intval($filters['offset']) : 0;
            $query .= $this->wpdb->prepare(" LIMIT %d OFFSET %d", $filters['limit'], $offset);
        }
        
        // Ejecutar query
        if (!empty($values)) {
            $query = $this->wpdb->prepare($query, $values);
        }
        
        $results = $this->wpdb->get_results($query);
        
        // Guardar en cache
        wp_cache_set($cache_key, $results, $this->cache_group, $this->cache_ttl);
        
        return $results;
    }
    
    /**
     * Obtener leads por estado
     */
    public function findByStatus($status) {
        return $this->findAll(array('status' => $status));
    }
    
    /**
     * Crear nuevo lead
     */
    public function create($data) {
        // Limpiar cache
        wp_cache_delete('leads_count', $this->cache_group);
        wp_cache_flush_group($this->cache_group);
        
        $lead_data = array(
            'lead_nombre' => sanitize_text_field($data['lead_nombre']),
            'lead_apellido' => sanitize_text_field($data['lead_apellido']),
            'lead_celular' => sanitize_text_field($data['lead_celular']),
            'lead_e_mail' => sanitize_email($data['lead_e_mail']),
            'lead_razon_social' => sanitize_text_field($data['lead_razon_social'] ?? ''),
            'lead_status' => 'publish',
            'cct_status' => 'publish',
            'cct_created' => current_time('mysql'),
            'cct_modified' => current_time('mysql')
        );
        
        $result = $this->wpdb->insert($this->leads_table, $lead_data);
        
        if ($result === false) {
            return false;
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Actualizar lead
     */
    public function update($id, $data) {
        // Limpiar cache
        wp_cache_delete('lead_' . $id, $this->cache_group);
        wp_cache_flush_group($this->cache_group);
        
        $update_data = array();
        
        if (isset($data['lead_nombre'])) {
            $update_data['lead_nombre'] = sanitize_text_field($data['lead_nombre']);
        }
        if (isset($data['lead_apellido'])) {
            $update_data['lead_apellido'] = sanitize_text_field($data['lead_apellido']);
        }
        if (isset($data['lead_celular'])) {
            $update_data['lead_celular'] = sanitize_text_field($data['lead_celular']);
        }
        if (isset($data['lead_e_mail'])) {
            $update_data['lead_e_mail'] = sanitize_email($data['lead_e_mail']);
        }
        
        $update_data['cct_modified'] = current_time('mysql');
        
        return $this->wpdb->update(
            $this->leads_table,
            $update_data,
            array('_ID' => $id),
            null,
            array('%d')
        );
    }
    
    /**
     * Eliminar lead (soft delete)
     */
    public function delete($id) {
        // Limpiar cache
        wp_cache_delete('lead_' . $id, $this->cache_group);
        wp_cache_flush_group($this->cache_group);
        
        return $this->wpdb->update(
            $this->leads_table,
            array('lead_status' => 'trash'),
            array('_ID' => $id),
            array('%s'),
            array('%d')
        );
    }
    
    /**
     * Contar leads con filtros
     */
    public function count($filters = array()) {
        $cache_key = 'leads_count_' . md5(serialize($filters));
        $cached = wp_cache_get($cache_key, $this->cache_group);
        
        if (false !== $cached) {
            return $cached;
        }
        
        $query = "SELECT COUNT(DISTINCT l._ID) 
                  FROM {$this->leads_table} l
                  LEFT JOIN {$this->events_table} e ON l._ID = e.lead_id
                  WHERE l.lead_status = 'publish'";
        
        if (!empty($filters['status'])) {
            $query .= $this->wpdb->prepare(" AND e.evento_status = %s", $filters['status']);
        }
        
        $count = $this->wpdb->get_var($query);
        
        wp_cache_set($cache_key, $count, $this->cache_group, $this->cache_ttl);
        
        return intval($count);
    }
    
    /**
     * Obtener leads por rango de fecha
     */
    public function getByDateRange($start_date, $end_date) {
        return $this->findAll(array(
            'date_from' => $start_date,
            'date_to' => $end_date
        ));
    }
    
    /**
     * Obtener leads recientes
     */
    public function getRecentLeads($limit = 10) {
        return $this->findAll(array(
            'limit' => $limit,
            'order_by' => 'l.cct_created',
            'order' => 'DESC'
        ));
    }
    
    /**
     * Limpiar todo el cache
     */
    public function clearCache() {
        wp_cache_flush_group($this->cache_group);
    }
}