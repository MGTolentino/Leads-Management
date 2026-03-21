<?php
/**
 * Plugin Name: Leads Management
 * Description: Sistema de gestión de leads y eventos - Version Optimizada
 * Version: 2.2.0
 * Author: Miguel Tolentino
 * Text Domain: ltb-leads
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('LTB_LEADS_VERSION', '2.2.0');
define('LTB_LEADS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LTB_LEADS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class - Optimized version
 */
class LTB_Leads_Management_V2 {
    
    private static $instance = null;
    private $error_handler = null;
    private $repository = null;
    private $cache = null;
    private $nonce_manager = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_core_services();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        $core_files = [
            // Interfaces
            'interfaces/interface-leads-repository.php',
            
            // Core services
            'class-error-handler.php',
            'class-leads-cache.php',
            'class-query-builder.php',
            'class-input-validator.php',
            'class-nonce-manager.php',
            
            // Repositories
            'repositories/class-leads-repository.php',
            
            // Refactored query class
            'class-leads-query-refactored.php',
            
            // Original classes (kept for compatibility)
            'class-leads-filters.php',
            'class-leads-ajax.php',
            'class-leads-router.php',
            'class-leads-followup-form.php',
            'class-leads-event-followup.php',
            'class-leads-add.php',
            'class-leads-event-router.php',
            'class-leads-status-utils.php',
            'class-leads-metadata.php',
            'class-leads-activator.php',
            'normalize-event-types.php'
        ];
        
        foreach ($core_files as $file) {
            $filepath = LTB_LEADS_PLUGIN_DIR . 'includes/' . $file;
            if (file_exists($filepath)) {
                require_once $filepath;
            }
        }
    }
    
    /**
     * Initialize core services
     */
    private function init_core_services() {
        // Initialize error handler first
        $this->error_handler = LTB_Error_Handler::getInstance();
        $this->error_handler->registerHandlers();
        
        // Initialize cache service
        $this->cache = new LTB_Leads_Cache();
        
        // Initialize repository
        $this->repository = new LTB_Leads_Repository();
        
        // Initialize nonce manager
        $this->nonce_manager = new LTB_Nonce_Manager();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_shortcode('ltb_leads_table', [$this, 'render_leads_table']);
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        }
    }
    
    /**
     * Plugin initialization
     */
    public function init() {
        // Initialize components
        new LTB_Leads_Filters();
        new LTB_Leads_Ajax();
        new LTB_Leads_Followup_Form();
        new LTB_Leads_Add();
        
        // Initialize routers
        LTB_Leads_Router::get_instance();
        if (class_exists('LTB_Leads_Event_Router')) {
            LTB_Leads_Event_Router::get_instance();
        }
        
        // Initialize metadata system
        if (class_exists('LTB_Leads_Metadata')) {
            new LTB_Leads_Metadata();
        }
        
        // Load translations
        load_plugin_textdomain('ltb-leads', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        if (!$this->is_leads_page()) {
            return;
        }
        
        // Enqueue modern unified CSS system
        wp_enqueue_style(
            'ltb-leads-unified',
            LTB_LEADS_PLUGIN_URL . 'assets/css/leads-unified.css',
            [],
            LTB_LEADS_VERSION
        );
        
        // jQuery and dependencies
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-autocomplete');
        wp_enqueue_script('jquery-ui-datepicker');
        
        // Third-party libraries from CDN (consider bundling in production)
        wp_enqueue_script(
            'moment-js',
            'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js',
            [],
            '2.29.4',
            true
        );
        
        wp_enqueue_style(
            'daterangepicker-css',
            'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css',
            [],
            '3.1.0'
        );
        
        wp_enqueue_script(
            'daterangepicker-js',
            'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js',
            ['jquery', 'moment-js'],
            '3.1.0',
            true
        );
        
        // Main unified pipeline script
        wp_enqueue_script(
            'ltb-pipeline-unified',
            LTB_LEADS_PLUGIN_URL . 'assets/js/pipeline-unified.js',
            ['jquery', 'moment-js', 'daterangepicker-js'],
            LTB_LEADS_VERSION,
            true
        );
        
        // Localize script with necessary data
        wp_localize_script('ltb-pipeline-unified', 'ltb_leads', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => LTB_Nonce_Manager::create(LTB_Nonce_Manager::AJAX_NONCE),
            'site_url' => site_url(),
            'strings' => [
                'loading' => __('Cargando...', 'ltb-leads'),
                'error' => __('Error al cargar los datos', 'ltb-leads'),
                'no_results' => __('No se encontraron resultados', 'ltb-leads'),
                'confirm_delete' => __('¿Está seguro de eliminar este lead?', 'ltb-leads')
            ],
            'config' => [
                'cache_ttl' => 300,
                'debounce_time' => 300,
                'page_size' => 50
            ]
        ]);
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts() {
        // Use unified CSS for admin too
        wp_enqueue_style(
            'ltb-leads-unified',
            LTB_LEADS_PLUGIN_URL . 'assets/css/leads-unified.css',
            [],
            LTB_LEADS_VERSION
        );
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Leads Management', 'ltb-leads'),
            __('Leads', 'ltb-leads'),
            'manage_options',
            'ltb-leads',
            [$this, 'render_admin_page'],
            'dashicons-groups',
            30
        );
        
        add_submenu_page(
            'ltb-leads',
            __('Configuración', 'ltb-leads'),
            __('Configuración', 'ltb-leads'),
            'manage_options',
            'ltb-leads-settings',
            [$this, 'render_settings_page']
        );
        
        add_submenu_page(
            'ltb-leads',
            __('Optimización BD', 'ltb-leads'),
            __('Optimización BD', 'ltb-leads'),
            'manage_options',
            'ltb-leads-optimize',
            [$this, 'render_optimize_page']
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        include LTB_LEADS_PLUGIN_DIR . 'templates/admin-page.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        include LTB_LEADS_PLUGIN_DIR . 'templates/settings-page.php';
    }
    
    /**
     * Render database optimization page
     */
    public function render_optimize_page() {
        include LTB_LEADS_PLUGIN_DIR . 'templates/optimize-page.php';
    }
    
    /**
     * Check if current page is a leads page
     */
    private function is_leads_page() {
        $current_url = $_SERVER['REQUEST_URI'];
        return (
            strpos($current_url, '/leads/') !== false ||
            strpos($current_url, '/lead-details/') !== false ||
            strpos($current_url, '/event-details/') !== false
        );
    }
    
    /**
     * Render leads table shortcode
     */
    public function render_leads_table($atts) {
        if (!$this->is_leads_page()) {
            return '';
        }
        
        if (!function_exists('ltb_user_can_manage_leads') || !ltb_user_can_manage_leads()) {
            return '<p>' . __('No tienes permisos para ver esta información.', 'ltb-leads') . '</p>';
        }
        
        ob_start();
        
        // Load the unified pipeline template
        $template_path = LTB_LEADS_PLUGIN_DIR . 'templates/pipeline-view-unified.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
        
        return ob_get_clean();
    }
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Version check
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            wp_die(__('Este plugin requiere WordPress 5.0 o superior', 'ltb-leads'));
        }
        
        // Update version
        update_option('ltb_leads_version', LTB_LEADS_VERSION);
        
        // Run database optimization
        self::optimize_database();
        
        // Activate components
        if (class_exists('LTB_Leads_Router')) {
            LTB_Leads_Router::activate();
        }
        
        if (class_exists('LTB_Leads_Activator')) {
            LTB_Leads_Activator::activate();
        }
        
        // Clear cache
        wp_cache_flush();
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('ltb_leads_daily_cleanup');
        
        // Clear cache
        wp_cache_flush();
        flush_rewrite_rules();
    }
    
    /**
     * Run database optimization
     */
    public static function optimize_database() {
        global $wpdb;
        
        // Read optimization SQL file
        $sql_file = LTB_LEADS_PLUGIN_DIR . 'includes/sql/optimize-indexes.sql';
        
        if (file_exists($sql_file)) {
            $sql_content = file_get_contents($sql_file);
            $queries = explode(';', $sql_content);
            
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query) && !str_starts_with($query, '--')) {
                    // Replace table prefixes
                    $query = str_replace('wp_', $wpdb->prefix, $query);
                    $wpdb->query($query);
                }
            }
        }
    }
    
    /**
     * Get repository instance
     */
    public function get_repository() {
        return $this->repository;
    }
    
    /**
     * Get cache instance
     */
    public function get_cache() {
        return $this->cache;
    }
    
    /**
     * Get error handler instance
     */
    public function get_error_handler() {
        return $this->error_handler;
    }
}

// Compatibility function
function ltb_user_can_manage_leads() {
    if (!is_user_logged_in()) {
        return false;
    }
    
    $user = wp_get_current_user();
    $allowed_roles = ['administrator', 'ejecutivo_de_ventas'];
    
    $can_manage = array_intersect($allowed_roles, $user->roles);
    $can_manage = !empty($can_manage) || current_user_can('manage_options');
    
    return apply_filters('ltb_user_can_manage_leads', $can_manage, $user);
}

// Body class filter
function ltb_add_body_class($classes) {
    if (function_exists('ltb_user_can_manage_leads') && ltb_user_can_manage_leads()) {
        $classes[] = 'can-manage-leads';
    }
    return $classes;
}
add_filter('body_class', 'ltb_add_body_class');

// Register activation/deactivation hooks
register_activation_hook(__FILE__, ['LTB_Leads_Management_V2', 'activate']);
register_deactivation_hook(__FILE__, ['LTB_Leads_Management_V2', 'deactivate']);

// Initialize plugin
function ltb_leads_init_v2() {
    return LTB_Leads_Management_V2::get_instance();
}
add_action('plugins_loaded', 'ltb_leads_init_v2');

// Global access function
function ltb_leads() {
    return LTB_Leads_Management_V2::get_instance();
}