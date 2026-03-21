<?php
/**
 * Refactored Leads Query Class with improved methods
 * 
 * @package LTB_Leads_Management
 * @since 2.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once LTB_LEADS_PLUGIN_DIR . 'includes/repositories/class-leads-repository.php';
require_once LTB_LEADS_PLUGIN_DIR . 'includes/class-input-validator.php';
require_once LTB_LEADS_PLUGIN_DIR . 'includes/class-leads-cache.php';

class LTB_Leads_Query_Refactored {
    
    private $wpdb;
    private $leads_table;
    private $eventos_table;
    private $repository;
    private $cache;
    private $validator;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->leads_table = $wpdb->prefix . 'jet_cct_leads';
        $this->eventos_table = $wpdb->prefix . 'jet_cct_eventos';
        $this->repository = new LTB_Leads_Repository();
        $this->cache = new LTB_Leads_Cache();
        $this->validator = new LTB_Input_Validator();
    }
    
    /**
     * Get leads with filters - Main refactored method
     * Split from 400+ lines to smaller focused methods
     * 
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function get_leads($filters = [], $limit = 100, $offset = 0) {
        // Validate and sanitize filters
        $validated_filters = $this->validateFilters($filters);
        
        // Check cache first
        $cache_key = $this->cache->make_key('leads', $validated_filters);
        $cached_result = $this->cache->get($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        // Build and execute query
        $query = $this->buildLeadsQuery($validated_filters, $limit, $offset);
        $results = $this->wpdb->get_results($query);
        
        // Process results
        $processed_results = $this->processLeadResults($results);
        
        // Get total count for pagination
        $total = $this->getTotalLeadsCount($validated_filters);
        
        // Prepare final response
        $response = [
            'leads' => $processed_results,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'pages' => ceil($total / $limit)
        ];
        
        // Cache the result
        $this->cache->set($cache_key, $response, 300);
        
        return $response;
    }
    
    /**
     * Validate and sanitize filter parameters
     * 
     * @param array $filters
     * @return array
     */
    private function validateFilters($filters) {
        $validated = [];
        
        // Date range validation
        if (!empty($filters['fecha_inicio']) && !empty($filters['fecha_fin'])) {
            $date_range = LTB_Input_Validator::validateDateRange(
                $filters['fecha_inicio'],
                $filters['fecha_fin']
            );
            
            if ($date_range) {
                $validated['fecha_inicio'] = $date_range['start'];
                $validated['fecha_fin'] = $date_range['end'];
            }
        }
        
        // Status validation
        if (!empty($filters['status'])) {
            $allowed_statuses = $this->getAllowedStatuses();
            
            if (is_array($filters['status'])) {
                $validated_statuses = array_filter($filters['status'], function($status) use ($allowed_statuses) {
                    return in_array($status, $allowed_statuses);
                });
                
                if (!empty($validated_statuses)) {
                    $validated['status'] = $validated_statuses;
                }
            } else {
                $status = LTB_Input_Validator::validateStatus($filters['status'], $allowed_statuses);
                if ($status) {
                    $validated['status'] = [$status];
                }
            }
        }
        
        // Search term validation
        if (!empty($filters['search'])) {
            $search = LTB_Input_Validator::validateText($filters['search'], 100);
            if ($search) {
                $validated['search'] = $search;
            }
        }
        
        // Event type validation
        if (!empty($filters['event_type'])) {
            $validated['event_type'] = LTB_Input_Validator::validateText($filters['event_type'], 50);
        }
        
        // Ejecutivo validation
        if (!empty($filters['ejecutivo'])) {
            $validated['ejecutivo'] = LTB_Input_Validator::validateText($filters['ejecutivo'], 100);
        }
        
        // Salon validation
        if (!empty($filters['salon'])) {
            $validated['salon'] = LTB_Input_Validator::validateText($filters['salon'], 100);
        }
        
        return $validated;
    }
    
    /**
     * Build the main leads query
     * 
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return string
     */
    private function buildLeadsQuery($filters, $limit, $offset) {
        $select = $this->buildSelectClause();
        $from = $this->buildFromClause();
        $where = $this->buildWhereClause($filters);
        $order = $this->buildOrderClause($filters);
        $pagination = $this->buildPaginationClause($limit, $offset);
        
        $query = "$select $from $where $order $pagination";
        
        return $query;
    }
    
    /**
     * Build SELECT clause
     * 
     * @return string
     */
    private function buildSelectClause() {
        return "SELECT DISTINCT 
                l._ID as lead_id,
                l.nombre,
                l.apellido,
                l.email,
                l.telefono,
                l.ejecutivo,
                l.estatus as lead_status,
                l.cct_created as created_date,
                e._ID as evento_id,
                e.fecha_de_evento,
                e.tipo_de_evento,
                e.evento_status,
                e.salon,
                e.numero_de_invitados,
                e.notas as evento_notas";
    }
    
    /**
     * Build FROM clause with JOINs
     * 
     * @return string
     */
    private function buildFromClause() {
        return "FROM {$this->leads_table} l
                LEFT JOIN {$this->eventos_table} e ON e.lead_id = l._ID";
    }
    
    /**
     * Build WHERE clause based on filters
     * 
     * @param array $filters
     * @return string
     */
    private function buildWhereClause($filters) {
        $conditions = [];
        
        // Date range filter
        if (!empty($filters['fecha_inicio']) && !empty($filters['fecha_fin'])) {
            $conditions[] = $this->wpdb->prepare(
                "e.fecha_de_evento BETWEEN %s AND %s",
                $filters['fecha_inicio'] . ' 00:00:00',
                $filters['fecha_fin'] . ' 23:59:59'
            );
        }
        
        // Status filter
        if (!empty($filters['status'])) {
            $placeholders = array_fill(0, count($filters['status']), '%s');
            $conditions[] = $this->wpdb->prepare(
                "e.evento_status IN (" . implode(',', $placeholders) . ")",
                $filters['status']
            );
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $search_term = '%' . $this->wpdb->esc_like($filters['search']) . '%';
            $conditions[] = $this->wpdb->prepare(
                "(l.nombre LIKE %s OR l.apellido LIKE %s OR l.email LIKE %s OR l.telefono LIKE %s)",
                $search_term, $search_term, $search_term, $search_term
            );
        }
        
        // Event type filter
        if (!empty($filters['event_type'])) {
            $conditions[] = $this->wpdb->prepare(
                "e.tipo_de_evento = %s",
                $filters['event_type']
            );
        }
        
        // Ejecutivo filter
        if (!empty($filters['ejecutivo'])) {
            $conditions[] = $this->wpdb->prepare(
                "l.ejecutivo = %s",
                $filters['ejecutivo']
            );
        }
        
        // Salon filter
        if (!empty($filters['salon'])) {
            $conditions[] = $this->wpdb->prepare(
                "e.salon = %s",
                $filters['salon']
            );
        }
        
        if (empty($conditions)) {
            return "WHERE 1=1";
        }
        
        return "WHERE " . implode(' AND ', $conditions);
    }
    
    /**
     * Build ORDER BY clause
     * 
     * @param array $filters
     * @return string
     */
    private function buildOrderClause($filters) {
        $order_by = "e.fecha_de_evento DESC, l.cct_created DESC";
        
        if (!empty($filters['order_by'])) {
            $allowed_columns = [
                'fecha_evento' => 'e.fecha_de_evento',
                'nombre' => 'l.nombre',
                'apellido' => 'l.apellido',
                'created' => 'l.cct_created',
                'status' => 'e.evento_status'
            ];
            
            if (isset($allowed_columns[$filters['order_by']])) {
                $column = $allowed_columns[$filters['order_by']];
                $direction = (!empty($filters['order']) && strtoupper($filters['order']) === 'ASC') ? 'ASC' : 'DESC';
                $order_by = "$column $direction";
            }
        }
        
        return "ORDER BY $order_by";
    }
    
    /**
     * Build pagination clause
     * 
     * @param int $limit
     * @param int $offset
     * @return string
     */
    private function buildPaginationClause($limit, $offset) {
        $limit = intval($limit);
        $offset = intval($offset);
        
        if ($limit <= 0) {
            $limit = 100;
        }
        
        if ($offset < 0) {
            $offset = 0;
        }
        
        return "LIMIT $limit OFFSET $offset";
    }
    
    /**
     * Process lead results
     * 
     * @param array $results
     * @return array
     */
    private function processLeadResults($results) {
        if (empty($results)) {
            return [];
        }
        
        $processed = [];
        
        foreach ($results as $row) {
            $lead = $this->formatLeadData($row);
            $processed[] = $lead;
        }
        
        return $processed;
    }
    
    /**
     * Format single lead data
     * 
     * @param object $row
     * @return array
     */
    private function formatLeadData($row) {
        return [
            'id' => intval($row->lead_id),
            'nombre' => $row->nombre,
            'apellido' => $row->apellido,
            'email' => $row->email,
            'telefono' => $row->telefono,
            'ejecutivo' => $row->ejecutivo,
            'status' => $row->evento_status ?: 'nuevo',
            'created_date' => $row->created_date,
            'evento' => $row->evento_id ? [
                'id' => intval($row->evento_id),
                'fecha' => $row->fecha_de_evento,
                'tipo' => $row->tipo_de_evento,
                'salon' => $row->salon,
                'invitados' => intval($row->numero_de_invitados),
                'notas' => $row->evento_notas
            ] : null
        ];
    }
    
    /**
     * Get total leads count with filters
     * 
     * @param array $filters
     * @return int
     */
    private function getTotalLeadsCount($filters) {
        $cache_key = $this->cache->make_key('leads_count', $filters);
        $cached_count = $this->cache->get($cache_key);
        
        if ($cached_count !== false) {
            return $cached_count;
        }
        
        $from = $this->buildFromClause();
        $where = $this->buildWhereClause($filters);
        
        $query = "SELECT COUNT(DISTINCT l._ID) $from $where";
        $count = intval($this->wpdb->get_var($query));
        
        $this->cache->set($cache_key, $count, 60);
        
        return $count;
    }
    
    /**
     * Get allowed status values
     * 
     * @return array
     */
    private function getAllowedStatuses() {
        return [
            'nuevo',
            'contactado',
            'visitado',
            'cotizado',
            'contratado',
            'perdido'
        ];
    }
    
    /**
     * Get pipeline data - Refactored version
     * 
     * @param array $filters
     * @return array
     */
    public function get_pipeline_data($filters = []) {
        $validated_filters = $this->validateFilters($filters);
        
        // Get leads grouped by status
        $pipeline = [
            'nuevo' => [],
            'contactado' => [],
            'visitado' => [],
            'cotizado' => [],
            'contratado' => [],
            'perdido' => []
        ];
        
        // Get all leads
        $result = $this->get_leads($validated_filters, 1000, 0);
        $leads = $result['leads'];
        
        // Group by status
        foreach ($leads as $lead) {
            $status = $lead['status'];
            if (isset($pipeline[$status])) {
                $pipeline[$status][] = $lead;
            }
        }
        
        // Calculate statistics
        $stats = $this->calculatePipelineStats($pipeline);
        
        return [
            'pipeline' => $pipeline,
            'stats' => $stats,
            'total' => $result['total']
        ];
    }
    
    /**
     * Calculate pipeline statistics
     * 
     * @param array $pipeline
     * @return array
     */
    private function calculatePipelineStats($pipeline) {
        $total = 0;
        $stats = [];
        
        foreach ($pipeline as $status => $leads) {
            $count = count($leads);
            $total += $count;
            $stats[$status] = $count;
        }
        
        $stats['total'] = $total;
        
        // Calculate conversion rates
        if ($stats['nuevo'] > 0) {
            $stats['conversion_rate'] = round(($stats['contratado'] / $stats['nuevo']) * 100, 2);
        } else {
            $stats['conversion_rate'] = 0;
        }
        
        return $stats;
    }
    
    /**
     * Search leads by term
     * 
     * @param string $term
     * @param int $limit
     * @return array
     */
    public function search_leads($term, $limit = 20) {
        $term = LTB_Input_Validator::validateText($term, 100);
        
        if (!$term) {
            return [];
        }
        
        $filters = ['search' => $term];
        $result = $this->get_leads($filters, $limit, 0);
        
        return $result['leads'];
    }
    
    /**
     * Get recent leads
     * 
     * @param int $days
     * @param int $limit
     * @return array
     */
    public function get_recent_leads($days = 7, $limit = 50) {
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        $end_date = date('Y-m-d');
        
        $filters = [
            'fecha_inicio' => $start_date,
            'fecha_fin' => $end_date
        ];
        
        $result = $this->get_leads($filters, $limit, 0);
        
        return $result['leads'];
    }
    
    /**
     * Get leads by status
     * 
     * @param string $status
     * @param int $limit
     * @return array
     */
    public function get_leads_by_status($status, $limit = 100) {
        $filters = ['status' => $status];
        $result = $this->get_leads($filters, $limit, 0);
        
        return $result['leads'];
    }
    
    /**
     * Export leads to CSV
     * 
     * @param array $filters
     * @return string File path
     */
    public function export_leads_csv($filters = []) {
        $result = $this->get_leads($filters, 10000, 0);
        $leads = $result['leads'];
        
        // Create CSV content
        $csv_content = $this->generateCSVContent($leads);
        
        // Save to temporary file
        $upload_dir = wp_upload_dir();
        $file_name = 'leads_export_' . date('Y-m-d_H-i-s') . '.csv';
        $file_path = $upload_dir['basedir'] . '/exports/' . $file_name;
        
        // Create directory if it doesn't exist
        if (!file_exists(dirname($file_path))) {
            wp_mkdir_p(dirname($file_path));
        }
        
        // Write CSV file
        file_put_contents($file_path, $csv_content);
        
        return [
            'path' => $file_path,
            'url' => $upload_dir['baseurl'] . '/exports/' . $file_name
        ];
    }
    
    /**
     * Generate CSV content from leads
     * 
     * @param array $leads
     * @return string
     */
    private function generateCSVContent($leads) {
        $csv = '';
        
        // Headers
        $headers = [
            'ID',
            'Nombre',
            'Apellido',
            'Email',
            'Teléfono',
            'Ejecutivo',
            'Estado',
            'Fecha Creación',
            'Tipo Evento',
            'Fecha Evento',
            'Salón',
            'Invitados'
        ];
        
        $csv .= implode(',', $headers) . "\n";
        
        // Data rows
        foreach ($leads as $lead) {
            $row = [
                $lead['id'],
                $lead['nombre'],
                $lead['apellido'],
                $lead['email'],
                $lead['telefono'],
                $lead['ejecutivo'],
                $lead['status'],
                $lead['created_date'],
                $lead['evento']['tipo'] ?? '',
                $lead['evento']['fecha'] ?? '',
                $lead['evento']['salon'] ?? '',
                $lead['evento']['invitados'] ?? ''
            ];
            
            // Escape values
            $row = array_map(function($value) {
                return '"' . str_replace('"', '""', $value) . '"';
            }, $row);
            
            $csv .= implode(',', $row) . "\n";
        }
        
        return $csv;
    }
}