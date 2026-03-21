<?php
/**
 * Manejo Centralizado de Nonces para Seguridad
 */

if (!defined('ABSPATH')) {
    exit;
}

class LTB_Nonce_Manager {
    
    private static $instance = null;
    private $nonce_actions = array();
    private $nonce_lifetime = DAY_IN_SECONDS;
    
    /**
     * Acciones de nonce predefinidas
     */
    const ACTION_LEAD_EDIT = 'ltb_lead_edit_nonce';
    const ACTION_LEAD_ADD = 'ltb_lead_add_nonce';
    const ACTION_LEAD_DELETE = 'ltb_lead_delete_nonce';
    const ACTION_EVENT_EDIT = 'ltb_event_edit_nonce';
    const ACTION_FOLLOWUP = 'ltb_followup_nonce';
    const ACTION_FILTER = 'ltb_leads_filter_nonce';
    const ACTION_AJAX = 'ltb_leads_nonce';
    const ACTION_METADATA = 'ltb_lead_metadata_nonce';
    const ACTION_IMPORT = 'ltb_import_nonce';
    const ACTION_EXPORT = 'ltb_export_nonce';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_nonce_actions();
        $this->init_hooks();
    }
    
    /**
     * Inicializar acciones de nonce
     */
    private function init_nonce_actions() {
        $this->nonce_actions = array(
            'lead_edit' => self::ACTION_LEAD_EDIT,
            'lead_add' => self::ACTION_LEAD_ADD,
            'lead_delete' => self::ACTION_LEAD_DELETE,
            'event_edit' => self::ACTION_EVENT_EDIT,
            'followup' => self::ACTION_FOLLOWUP,
            'filter' => self::ACTION_FILTER,
            'ajax' => self::ACTION_AJAX,
            'metadata' => self::ACTION_METADATA,
            'import' => self::ACTION_IMPORT,
            'export' => self::ACTION_EXPORT
        );
    }
    
    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Personalizar duración de nonce
        add_filter('nonce_life', array($this, 'custom_nonce_lifetime'));
        
        // Agregar nonces a formularios automáticamente
        add_action('ltb_form_nonce_field', array($this, 'render_nonce_field'), 10, 1);
        
        // Verificación automática en AJAX
        add_action('wp_ajax_nopriv_ltb_verify_nonce', array($this, 'ajax_verify_nonce'));
        add_action('wp_ajax_ltb_verify_nonce', array($this, 'ajax_verify_nonce'));
    }
    
    /**
     * Crear nonce
     */
    public function create($action_key) {
        $action = $this->get_action($action_key);
        return wp_create_nonce($action);
    }
    
    /**
     * Crear campo nonce
     */
    public function create_field($action_key, $name = '_wpnonce', $referer = true) {
        $action = $this->get_action($action_key);
        return wp_nonce_field($action, $name, $referer, false);
    }
    
    /**
     * Verificar nonce
     */
    public function verify($nonce, $action_key) {
        $action = $this->get_action($action_key);
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Verificar nonce de request
     */
    public function verify_request($action_key, $nonce_name = '_wpnonce') {
        $action = $this->get_action($action_key);
        
        // Verificar en POST
        if (isset($_POST[$nonce_name])) {
            return wp_verify_nonce($_POST[$nonce_name], $action);
        }
        
        // Verificar en GET
        if (isset($_GET[$nonce_name])) {
            return wp_verify_nonce($_GET[$nonce_name], $action);
        }
        
        // Verificar en header para AJAX
        $nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? $_SERVER['HTTP_X_WP_NONCE'] : '';
        if ($nonce) {
            return wp_verify_nonce($nonce, $action);
        }
        
        return false;
    }
    
    /**
     * Verificar AJAX referer
     */
    public function verify_ajax($action_key, $nonce_name = 'nonce', $die = true) {
        $action = $this->get_action($action_key);
        return check_ajax_referer($action, $nonce_name, $die);
    }
    
    /**
     * Crear URL con nonce
     */
    public function create_url($url, $action_key, $name = '_wpnonce') {
        $action = $this->get_action($action_key);
        return wp_nonce_url($url, $action, $name);
    }
    
    /**
     * Obtener acción por clave
     */
    private function get_action($action_key) {
        if (isset($this->nonce_actions[$action_key])) {
            return $this->nonce_actions[$action_key];
        }
        
        // Si no existe, usar la clave directamente
        return $action_key;
    }
    
    /**
     * Registrar nueva acción de nonce
     */
    public function register_action($key, $action) {
        $this->nonce_actions[$key] = $action;
    }
    
    /**
     * Renderizar campo nonce
     */
    public function render_nonce_field($action_key) {
        echo $this->create_field($action_key);
    }
    
    /**
     * Verificación AJAX de nonce
     */
    public function ajax_verify_nonce() {
        $action = sanitize_text_field($_POST['action_key'] ?? '');
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        
        if (empty($action) || empty($nonce)) {
            wp_send_json_error('Datos insuficientes');
            return;
        }
        
        if ($this->verify($nonce, $action)) {
            wp_send_json_success('Nonce válido');
        } else {
            wp_send_json_error('Nonce inválido');
        }
    }
    
    /**
     * Personalizar duración del nonce
     */
    public function custom_nonce_lifetime($lifetime) {
        // Aplicar duración personalizada solo para nuestros nonces
        if ($this->is_our_nonce()) {
            return $this->nonce_lifetime;
        }
        return $lifetime;
    }
    
    /**
     * Verificar si es nuestro nonce
     */
    private function is_our_nonce() {
        // Verificar si la acción actual es una de las nuestras
        foreach ($this->nonce_actions as $action) {
            if (doing_action($action)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Establecer duración personalizada
     */
    public function set_lifetime($seconds) {
        $this->nonce_lifetime = $seconds;
    }
    
    /**
     * Obtener todos los nonces para JavaScript
     */
    public function get_all_nonces() {
        $nonces = array();
        
        foreach ($this->nonce_actions as $key => $action) {
            $nonces[$key] = $this->create($key);
        }
        
        return $nonces;
    }
    
    /**
     * Localizar nonces para JavaScript
     */
    public function localize_nonces($handle = 'ltb-main') {
        wp_localize_script($handle, 'ltbNonces', $this->get_all_nonces());
    }
    
    /**
     * Validación con mensaje de error personalizado
     */
    public function validate_or_die($action_key, $message = null) {
        if (!$this->verify_request($action_key)) {
            $message = $message ?: 'Error de seguridad: La sesión ha expirado. Por favor, recarga la página.';
            wp_die($message, 'Error de Seguridad', array('response' => 403));
        }
        return true;
    }
}