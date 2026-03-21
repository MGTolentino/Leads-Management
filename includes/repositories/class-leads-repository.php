<?php
/**
 * Concrete implementation of Leads Repository
 * 
 * @package LTB_Leads_Management
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once LTB_LEADS_PLUGIN_DIR . 'includes/interfaces/interface-leads-repository.php';
require_once LTB_LEADS_PLUGIN_DIR . 'includes/class-leads-cache.php';

class LTB_Leads_Repository implements LTB_Leads_Repository_Interface {
    
    /**
     * @var wpdb
     */
    private $db;
    
    /**
     * @var LTB_Leads_Cache
     */
    private $cache;
    
    /**
     * @var string
     */
    private $leads_table;
    
    /**
     * @var string
     */
    private $eventos_table;
    
    /**
     * @var LTB_Query_Builder
     */
    private $query_builder;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->cache = new LTB_Leads_Cache();
        $this->leads_table = $wpdb->prefix . 'jet_cct_leads';
        $this->eventos_table = $wpdb->prefix . 'jet_cct_eventos';
        $this->query_builder = new LTB_Query_Builder($this->leads_table, $this->eventos_table);
    }
    
    /**
     * Find a lead by ID
     * 
     * @param int $id Lead ID
     * @return object|null
     */
    public function find($id) {
        $cache_key = 'lead_' . $id;
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $query = $this->db->prepare(
            "SELECT l.*, e.* 
             FROM {$this->leads_table} l
             LEFT JOIN {$this->eventos_table} e ON e.lead_id = l._ID
             WHERE l._ID = %d
             LIMIT 1",
            $id
        );
        
        $result = $this->db->get_row($query);
        
        if ($result) {
            $this->cache->set($cache_key, $result, 3600);
        }
        
        return $result;
    }
    
    /**
     * Find leads with filters
     * 
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findWithFilters(array $filters, $limit = 100, $offset = 0) {
        $cache_key = 'leads_' . md5(serialize($filters) . $limit . $offset);
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $query = $this->query_builder
            ->select(['l.*', 'e.*'])
            ->from($this->leads_table, 'l')
            ->leftJoin($this->eventos_table, 'e', 'e.lead_id = l._ID');
        
        // Apply filters
        $this->applyFilters($query, $filters);
        
        // Apply pagination
        $query->limit($limit)->offset($offset);
        
        $sql = $query->build();
        $results = $this->db->get_results($sql);
        
        if ($results) {
            $this->cache->set($cache_key, $results, 600); // Cache for 10 minutes
        }
        
        return $results ?: [];
    }
    
    /**
     * Save a new lead
     * 
     * @param array $data
     * @return int|false
     */
    public function save(array $data) {
        $sanitized_data = $this->sanitizeLeadData($data);
        
        if (empty($sanitized_data)) {
            return false;
        }
        
        $result = $this->db->insert($this->leads_table, $sanitized_data);
        
        if ($result === false) {
            return false;
        }
        
        $lead_id = $this->db->insert_id;
        
        // Clear related caches
        $this->cache->delete_group('leads');
        
        return $lead_id;
    }
    
    /**
     * Update a lead
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, array $data) {
        $sanitized_data = $this->sanitizeLeadData($data);
        
        if (empty($sanitized_data)) {
            return false;
        }
        
        $result = $this->db->update(
            $this->leads_table,
            $sanitized_data,
            ['_ID' => $id],
            null,
            ['%d']
        );
        
        if ($result !== false) {
            // Clear specific cache
            $this->cache->delete('lead_' . $id);
            $this->cache->delete_group('leads');
        }
        
        return $result !== false;
    }
    
    /**
     * Delete a lead
     * 
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        // First delete related events
        $this->db->delete($this->eventos_table, ['lead_id' => $id], ['%d']);
        
        // Then delete the lead
        $result = $this->db->delete($this->leads_table, ['_ID' => $id], ['%d']);
        
        if ($result !== false) {
            // Clear caches
            $this->cache->delete('lead_' . $id);
            $this->cache->delete_group('leads');
        }
        
        return $result !== false;
    }
    
    /**
     * Count leads with filters
     * 
     * @param array $filters
     * @return int
     */
    public function count(array $filters = []) {
        $cache_key = 'leads_count_' . md5(serialize($filters));
        $cached = $this->cache->get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $query = $this->query_builder
            ->select(['COUNT(DISTINCT l._ID) as total'])
            ->from($this->leads_table, 'l')
            ->leftJoin($this->eventos_table, 'e', 'e.lead_id = l._ID');
        
        $this->applyFilters($query, $filters);
        
        $sql = $query->build();
        $count = $this->db->get_var($sql);
        
        $this->cache->set($cache_key, intval($count), 300); // Cache for 5 minutes
        
        return intval($count);
    }
    
    /**
     * Apply filters to query builder
     * 
     * @param LTB_Query_Builder $query
     * @param array $filters
     */
    private function applyFilters($query, array $filters) {
        // Date range filter
        if (!empty($filters['fecha_inicio']) && !empty($filters['fecha_fin'])) {
            $query->where('e.fecha_de_evento', '>=', $filters['fecha_inicio'])
                  ->where('e.fecha_de_evento', '<=', $filters['fecha_fin']);
        }
        
        // Status filter
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('e.evento_status', $filters['status']);
            } else {
                $query->where('e.evento_status', '=', $filters['status']);
            }
        }
        
        // Event type filter
        if (!empty($filters['tipo_evento'])) {
            $query->where('e.tipo_de_evento', '=', $filters['tipo_evento']);
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $search = '%' . $this->db->esc_like($filters['search']) . '%';
            $query->whereGroup(function($q) use ($search) {
                $q->whereLike('l.nombre', $search)
                  ->orWhereLike('l.apellido', $search)
                  ->orWhereLike('l.email', $search)
                  ->orWhereLike('l.telefono', $search);
            });
        }
        
        // Ejecutivo filter
        if (!empty($filters['ejecutivo'])) {
            $query->where('l.ejecutivo', '=', $filters['ejecutivo']);
        }
        
        // Salon filter
        if (!empty($filters['salon'])) {
            $query->where('e.salon', '=', $filters['salon']);
        }
    }
    
    /**
     * Sanitize lead data before database operations
     * 
     * @param array $data
     * @return array
     */
    private function sanitizeLeadData(array $data) {
        $sanitized = [];
        
        $fields = [
            'nombre' => 'sanitize_text_field',
            'apellido' => 'sanitize_text_field',
            'email' => 'sanitize_email',
            'telefono' => 'sanitize_text_field',
            'ejecutivo' => 'sanitize_text_field',
            'estatus' => 'sanitize_text_field',
            'notas' => 'sanitize_textarea_field'
        ];
        
        foreach ($fields as $field => $sanitizer) {
            if (isset($data[$field])) {
                $sanitized[$field] = call_user_func($sanitizer, $data[$field]);
            }
        }
        
        return $sanitized;
    }
}