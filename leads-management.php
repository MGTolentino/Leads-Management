<?php
/**
 * Plugin Name: Leads Management
 * Description: Sistema de gestión de leads y eventos
 * Version: 2.1.0
 * Author: Miguel Tolentino
 * Text Domain: ltb-leads
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('LTB_LEADS_VERSION', '2.1.0');
define('LTB_LEADS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LTB_LEADS_PLUGIN_URL', plugin_dir_url(__FILE__));

class LTB_Leads_Management {
    private static $instance = null;
    private $query_handler = null;
    private $filters_handler = null;
    private $ajax_handler = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        
        $files = array(
            'class-leads-query.php',
            'class-leads-filters.php',
            'class-leads-router.php',
            'class-leads-ajax.php',
            'class-leads-followup-form.php',
            'class-leads-event-followup.php',
            'class-leads-add.php',
            'class-leads-event-router.php',
			'class-leads-status-utils.php',
			'class-leads-metadata.php',
			'class-leads-activator.php',
			'normalize-event-types.php'
        );

        foreach ($files as $file) {
            $filepath = LTB_LEADS_PLUGIN_DIR . 'includes/' . $file;
            if (file_exists($filepath)) {
                require_once $filepath;
            }
        }
    }

    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('ltb_leads_table', array($this, 'render_leads_table'));
    }

    public function init() {
        
        $this->query_handler = new LTB_Leads_Query();
        $this->filters_handler = new LTB_Leads_Filters();
        $this->ajax_handler = new LTB_Leads_Ajax(); 
        LTB_Leads_Router::get_instance();
        if (class_exists('LTB_Leads_Event_Router')) {
                LTB_Leads_Event_Router::get_instance();
            }
        new LTB_Leads_Followup_Form();
        new LTB_Leads_Add();
        
        // Inicializar sistema de metadatos si la clase existe
        if (class_exists('LTB_Leads_Metadata')) {
            new LTB_Leads_Metadata();
        } 
        
        load_plugin_textdomain('ltb-leads', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function enqueue_scripts() {

        if (!$this->is_leads_page()) {
            return;
        }

        // Cargar sistema de diseño CSS unificado moderno
        wp_enqueue_style(
            'ltb-leads-unified',
            LTB_LEADS_PLUGIN_URL . 'assets/css/leads-unified.css',
            array(),
            LTB_LEADS_VERSION
        );
        
        // Cargar CSS de compatibilidad para transición
        wp_enqueue_style(
            'ltb-compatibility-bridge',
            LTB_LEADS_PLUGIN_URL . 'assets/css/compatibility-bridge.css',
            array('ltb-leads-unified'),
            LTB_LEADS_VERSION
        );
        
        // Cargar ajustes compactos
        wp_enqueue_style(
            'ltb-compact-adjustments',
            LTB_LEADS_PLUGIN_URL . 'assets/css/compact-adjustments.css',
            array('ltb-leads-unified'),
            LTB_LEADS_VERSION
        );
        
        // Cargar fixes del pipeline
        wp_enqueue_style(
            'ltb-pipeline-fixes',
            LTB_LEADS_PLUGIN_URL . 'assets/css/pipeline-fixes.css',
            array('ltb-leads-unified'),
            LTB_LEADS_VERSION
        );
        
        // Cargar fixes del modal
        wp_enqueue_style(
            'ltb-modal-fixes',
            LTB_LEADS_PLUGIN_URL . 'assets/css/modal-fixes.css',
            array('ltb-leads-unified'),
            LTB_LEADS_VERSION
        );
        
        // Cargar fixes de filtros
        wp_enqueue_style(
            'ltb-filter-fixes',
            LTB_LEADS_PLUGIN_URL . 'assets/css/filter-fixes.css',
            array('ltb-leads-unified'),
            LTB_LEADS_VERSION
        );

        if ($this->is_leads_listing_page()) {
            // Cargar jQuery UI CSS para autocomplete y datepicker
            wp_enqueue_style(
                'jquery-ui-css',
                'https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css',
                array(),
                '1.13.2'
            );
            
            // Cargar jQuery UI para autocomplete y datepicker
            wp_enqueue_script('jquery-ui-autocomplete');
            wp_enqueue_script('jquery-ui-datepicker');
            
            // Cargar Moment.js
            wp_enqueue_script(
                'moment-js',
                'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js',
                array(),
                '2.29.4',
                true
            );
            
            // Cargar DateRangePicker
            wp_enqueue_style(
                'daterangepicker-css',
                'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css',
                array(),
                '3.1.0'
            );
            
            wp_enqueue_script(
                'daterangepicker-js',
                'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js',
                array('jquery', 'moment-js'),
                '3.1.0',
                true
            );
            
            // Cargar lead-add.js para funcionalidad de autocomplete
            wp_enqueue_script(
                'ltb-lead-add',
                LTB_LEADS_PLUGIN_URL . 'assets/js/lead-add.js',
                array('jquery', 'jquery-ui-autocomplete', 'jquery-ui-datepicker'),
                LTB_LEADS_VERSION,
                true
            );
            
            // Cargar el JavaScript original del pipeline que funcionaba
            wp_enqueue_script(
                'ltb-pipeline-simple',
                LTB_LEADS_PLUGIN_URL . 'assets/js/pipeline-simple.js',
                array('jquery', 'jquery-ui-autocomplete', 'moment-js', 'daterangepicker-js', 'ltb-lead-add'),
                LTB_LEADS_VERSION,
                true
            );
            
            // Cargar fix para el modal
            wp_enqueue_script(
                'ltb-modal-fix',
                LTB_LEADS_PLUGIN_URL . 'assets/js/modal-fix.js',
                array('jquery'),
                LTB_LEADS_VERSION,
                true
            );
            
            wp_localize_script('ltb-pipeline-simple', 'ltb_leads', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ltb_leads_nonce'),
                'site_url' => site_url()
            ));
            
            // Localizar script para lead-add (necesario para autocomplete)
            wp_localize_script('ltb-lead-add', 'ltbLeadAdd', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ltb_lead_add_nonce')
            ));
            
            $status_options = LTB_Leads_Status_Utils::get_status_options();
            $event_types = LTB_Leads_Status_Utils::get_event_types();
            wp_localize_script('ltb-pipeline-simple', 'leadManagementConfig', array(
                'statusOptions' => $status_options,
                'eventTypes' => $event_types
            ));
        }

        if ($this->is_lead_details_page()) {
            // El CSS unificado ya contiene todos los estilos necesarios

            wp_enqueue_script(
                'ltb-leads-edit',
                LTB_LEADS_PLUGIN_URL . 'assets/js/lead-edit.js',
                array('jquery', 'jquery-ui-autocomplete', 'jquery-ui-datepicker'),
                LTB_LEADS_VERSION,
                true
            );
			
$status_options = LTB_Leads_Status_Utils::get_status_options();
$event_types = LTB_Leads_Status_Utils::get_event_types();
wp_localize_script('ltb-lead-edit', 'leadManagementConfig', array(
    'statusOptions' => $status_options,
    'eventTypes' => $event_types
));

            wp_localize_script('ltb-leads-edit', 'ltbLeadEdit', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ltb_lead_edit_nonce'),
                'leadId' => $GLOBALS['ltb_lead_data']->lead_id ?? 0
            ));
            
            // Script para manejo de metadatos
            wp_enqueue_script(
                'ltb-leads-metadata',
                LTB_LEADS_PLUGIN_URL . 'assets/js/lead-metadata.js',
                array('jquery', 'jquery-ui-datepicker', 'select2'),
                LTB_LEADS_VERSION,
                true
            );
            
            wp_localize_script('ltb-leads-metadata', 'ltbLeadMetadata', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ltb_lead_metadata_nonce'),
                'leadId' => $GLOBALS['ltb_lead_data']->lead_id ?? 0
            ));
            
            wp_enqueue_script(
                'ltb-followup-form',
                LTB_LEADS_PLUGIN_URL . 'assets/js/followup-form.js',
                array('jquery'),
                LTB_LEADS_VERSION,
                true
            );

            wp_localize_script('ltb-followup-form', 'ltbFollowup', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('followup_nonce'),
                'leadId' => $GLOBALS['ltb_lead_data']->lead_id ?? 0
            ));
        }

        wp_localize_script('ltb-leads-filters', 'ltbLeadsFilters', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ltb_leads_filter_nonce'),
            'strings' => array(
                'loading' => __('Cargando...', 'ltb-leads'),
                'error' => __('Error al cargar los datos', 'ltb-leads'),
                'no_results' => __('No se encontraron resultados', 'ltb-leads')
            )
        ));
    }

    private function is_leads_page() {
        $current_url = $_SERVER['REQUEST_URI'];
        return (
            strpos($current_url, '/leads/') !== false ||
            strpos($current_url, '/lead-details/') !== false ||
            strpos($current_url, '/event-details/') !== false
        );
    }

    private function is_leads_listing_page() {
        $current_url = $_SERVER['REQUEST_URI'];

        return (strpos($current_url, '/leads/') !== false && 
                strpos($current_url, '/lead-details/') === false &&
                strpos($current_url, '/event-details/') === false);
    }

    private function is_lead_details_page() {
        return strpos($_SERVER['REQUEST_URI'], '/lead-details/') !== false;
    }

    private function is_event_details_page() {
        return strpos($_SERVER['REQUEST_URI'], '/event-details/') !== false;
    }

    public function render_leads_table($atts) {
        if (!$this->is_leads_page()) {
            return '';
        }
        
        if (!function_exists('ltb_user_can_manage_leads') || !ltb_user_can_manage_leads()) {
            return '<p>No tienes permisos para ver esta información.</p>';
        }

        ob_start();

        if ($this->is_leads_listing_page()) {
            // Cargar la vista pipeline simple original que funcionaba
            $pipeline_template_path = LTB_LEADS_PLUGIN_DIR . 'templates/pipeline-view-simple.php';
            if (file_exists($pipeline_template_path)) {
                include $pipeline_template_path;
            }
        }

        return ob_get_clean();
    }

    public static function activate() {
        
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            wp_die(__('Este plugin requiere WordPress 5.0 o superior', 'ltb-leads'));
        }

        add_option('ltb_leads_version', LTB_LEADS_VERSION);

        // Activar el enrutador
        $router_file = LTB_LEADS_PLUGIN_DIR . 'includes/class-leads-router.php';
        if (file_exists($router_file)) {
            require_once $router_file;
            if (class_exists('LTB_Leads_Router')) {
                LTB_Leads_Router::activate();
            }
        }
        
        // Activar el sistema de metadatos
        $activator_file = LTB_LEADS_PLUGIN_DIR . 'includes/class-leads-activator.php';
        if (file_exists($activator_file)) {
            require_once $activator_file;
            if (class_exists('LTB_Leads_Activator')) {
                LTB_Leads_Activator::activate();
            }
        }
        
        flush_rewrite_rules();
        
        wp_cache_flush();
    }

    public static function deactivate() {
        flush_rewrite_rules();
        wp_cache_flush();
    }
}

function ltb_user_can_manage_leads() {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return false;
    }
    
    $user = wp_get_current_user();
    $allowed_roles = array('administrator', 'ejecutivo_de_ventas');
    
    // Check by roles
    $can_manage = array_intersect($allowed_roles, $user->roles);
    
    // Also check manage_options capability directly
    $can_manage = !empty($can_manage) || current_user_can('manage_options');
    
    
    return $can_manage;
}

function ltb_add_body_class($classes) {
    if (function_exists('ltb_user_can_manage_leads') && ltb_user_can_manage_leads()) {
        $classes[] = 'can-manage-leads';
    }
    return $classes;
}
add_filter('body_class', 'ltb_add_body_class');

register_activation_hook(__FILE__, array('LTB_Leads_Management', 'activate'));
register_deactivation_hook(__FILE__, array('LTB_Leads_Management', 'deactivate'));

function ltb_leads_init() {
    return LTB_Leads_Management::get_instance();
}

add_action('plugins_loaded', 'ltb_leads_init');