<?php
/**
 * AJAX Handler Optimizado con Repository Pattern
 */

if (!defined('ABSPATH')) {
    exit;
}

class LTB_Leads_Ajax_Optimized {
    
    private $repository;
    private $query_handler;
    
    public function __construct() {
        // Cargar Repository si existe
        if (file_exists(LTB_LEADS_PLUGIN_DIR . 'includes/repositories/class-leads-repository.php')) {
            require_once LTB_LEADS_PLUGIN_DIR . 'includes/repositories/class-leads-repository.php';
            $this->repository = new LTB_Leads_Repository();
        }
        
        // Mantener compatibilidad con query handler original
        $this->query_handler = new LTB_Leads_Query();
        
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Reemplazar el handler de get_leads_by_status con versión optimizada
        remove_action('wp_ajax_get_leads_by_status', array($this->query_handler, 'get_leads_by_status'));
        add_action('wp_ajax_get_leads_by_status', array($this, 'get_leads_by_status_optimized'));
        
        // Agregar hook para limpiar cache
        add_action('wp_ajax_clear_leads_cache', array($this, 'clear_cache'));
    }
    
    /**
     * Obtener leads por estado - Versión optimizada con Repository
     */
    public function get_leads_by_status_optimized() {
        // Verificar nonce
        if (!check_ajax_referer('ltb_leads_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
        }
        
        // Si no hay repository, usar método original
        if (!$this->repository) {
            $original_ajax = new LTB_Leads_Ajax();
            return $original_ajax->get_leads_by_status();
        }
        
        // Obtener filtros
        $filters = array(
            'search' => sanitize_text_field($_POST['search'] ?? ''),
            'status' => sanitize_text_field($_POST['status'] ?? ''),
            'event_type' => sanitize_text_field($_POST['event_type'] ?? ''),
            'date_from' => sanitize_text_field($_POST['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_POST['date_to'] ?? '')
        );
        
        // Remover filtros vacíos
        $filters = array_filter($filters);
        
        try {
            // Usar repository con cache
            $leads = $this->repository->findAll($filters);
            
            // Agrupar por estado para pipeline
            $grouped = $this->group_by_status($leads);
            
            wp_send_json_success(array(
                'leads' => $grouped,
                'total' => count($leads),
                'cached' => true
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Error al obtener leads: ' . $e->getMessage());
        }
    }
    
    /**
     * Agrupar leads por estado
     */
    private function group_by_status($leads) {
        $grouped = array();
        $status_options = LTB_Leads_Status_Utils::get_active_status_options();
        
        // Inicializar grupos
        foreach ($status_options as $status => $label) {
            $grouped[$status] = array();
        }
        $grouped['sin-evento'] = array();
        
        // Agrupar leads
        foreach ($leads as $lead) {
            if (!empty($lead->evento_status)) {
                $status = LTB_Leads_Status_Utils::get_status_category($lead->evento_status);
                if (isset($grouped[$status])) {
                    $grouped[$status][] = $lead;
                }
            } else {
                $grouped['sin-evento'][] = $lead;
            }
        }
        
        return $grouped;
    }
    
    /**
     * Limpiar cache
     */
    public function clear_cache() {
        if (!check_ajax_referer('ltb_leads_nonce', 'nonce', false)) {
            wp_send_json_error('Security check failed');
        }
        
        if ($this->repository) {
            $this->repository->clearCache();
            wp_send_json_success('Cache limpiado');
        } else {
            wp_send_json_error('Repository no disponible');
        }
    }
}