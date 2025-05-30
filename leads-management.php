<?php
/**
 * Plugin Name: Leads Management
 * Description: Sistema de gestión de leads y eventos
 * Version: 2.0.0
 * Author: Miguel Tolentino
 * Text Domain: ltb-leads
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('LTB_LEADS_VERSION', '2.0.0');
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
            'class-leads-add.php',
            'class-leads-event-router.php',
			'class-leads-status-utils.php',
			'class-leads-metadata.php',
			'class-leads-activator.php'
        );

        foreach ($files as $file) {
            $filepath = LTB_LEADS_PLUGIN_DIR . 'includes/' . $file;
            if (file_exists($filepath)) {
                require_once $filepath;
            } else {
                error_log('ARCHIVO NO ENCONTRADO: ' . $filepath);
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

        wp_enqueue_style(
            'ltb-leads-admin',
            LTB_LEADS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            LTB_LEADS_VERSION
        );
        
        wp_enqueue_style(
            'ltb-leads-permissions',
            LTB_LEADS_PLUGIN_URL . 'assets/css/permissions.css',
            array(),
            LTB_LEADS_VERSION
        );

        wp_enqueue_style(
            'ltb-leads-responsive',
            LTB_LEADS_PLUGIN_URL . 'assets/css/responsive.css',
            array(),
            LTB_LEADS_VERSION
        );
        
        // Nuevos estilos para filtros mejorados
        wp_enqueue_style(
            'ltb-leads-filters',
            LTB_LEADS_PLUGIN_URL . 'assets/css/filters.css',
            array(),
            LTB_LEADS_VERSION
        );
        
        // Estilos mejorados para filtros
        wp_enqueue_style(
            'ltb-enhanced-filters',
            LTB_LEADS_PLUGIN_URL . 'assets/css/enhanced-filters.css',
            array('ltb-leads-filters'),
            LTB_LEADS_VERSION
        );
        
        // Select2 para selección múltiple
        wp_enqueue_style(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            array(),
            '4.1.0-rc.0'
        );
        
        wp_enqueue_script(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            array('jquery'),
            '4.1.0-rc.0',
            true
        );

        // jQuery UI para datepicker y autocomplete
        wp_enqueue_style(
            'jquery-ui-style',
            'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css',
            array(),
            '1.13.2'
        );

        wp_enqueue_script('jquery-ui-autocomplete');
        wp_enqueue_script('jquery-ui-datepicker');
        
        wp_enqueue_script(
            'ltb-leads-filters',
            LTB_LEADS_PLUGIN_URL . 'assets/js/filters.js',
            array('jquery', 'jquery-ui-datepicker', 'select2'),
            LTB_LEADS_VERSION,
            true
        );
        
        // Nuevo script para gestión de filtros avanzados
        wp_enqueue_script(
            'ltb-advanced-filters',
            LTB_LEADS_PLUGIN_URL . 'assets/js/advanced-filters.js',
            array('jquery', 'select2'),
            LTB_LEADS_VERSION,
            true
        );
        
        wp_enqueue_script(
            'ltb-lead-add',
            LTB_LEADS_PLUGIN_URL . 'assets/js/lead-add.js',
            array('jquery', 'jquery-ui-datepicker'),
            LTB_LEADS_VERSION,
            true
        );

        wp_localize_script('ltb-lead-add', 'ltbLeadAdd', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ltb_lead_add_nonce')
        ));
        
        wp_enqueue_style(
            'ltb-lead-add-css',
            LTB_LEADS_PLUGIN_URL . 'assets/css/lead-add.css',
            array(),
            LTB_LEADS_VERSION
        );

        if ($this->is_leads_listing_page()) {

            wp_register_script(
                'ltb-leads-pipeline',
                plugin_dir_url(__FILE__) . 'assets/js/pipeline-view.js',
                array('jquery', 'jquery-ui-datepicker'),
                LTB_LEADS_VERSION,
                true
            );

            wp_enqueue_script('ltb-leads-pipeline');
            
            wp_localize_script('ltb-leads-pipeline', 'ltb_leads', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ltb_leads_nonce'),
                'site_url' => site_url()
            ));
			
			$status_options = LTB_Leads_Status_Utils::get_status_options();
wp_localize_script('ltb-leads-pipeline', 'leadManagementConfig', array(
    'statusOptions' => $status_options
));
            
            wp_enqueue_style(
                'ltb-pipeline-view',
                LTB_LEADS_PLUGIN_URL . 'assets/css/pipeline-view.css',
                array('ltb-leads-admin'),
                LTB_LEADS_VERSION
            );
        }

        if ($this->is_lead_details_page()) {
            wp_enqueue_style(
                'ltb-leads-edit',
                LTB_LEADS_PLUGIN_URL . 'assets/css/lead-edit.css',
                array(),
                LTB_LEADS_VERSION
            );
            
            // Cargar estilos para metadatos
            wp_enqueue_style(
                'ltb-leads-metadata',
                LTB_LEADS_PLUGIN_URL . 'assets/css/lead-metadata.css',
                array(),
                LTB_LEADS_VERSION
            );

            wp_enqueue_script(
                'ltb-leads-edit',
                LTB_LEADS_PLUGIN_URL . 'assets/js/lead-edit.js',
                array('jquery'),
                LTB_LEADS_VERSION,
                true
            );
			
$status_options = LTB_Leads_Status_Utils::get_status_options();
wp_localize_script('ltb-lead-edit', 'leadManagementConfig', array(
    'statusOptions' => $status_options
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

        if ($this->is_leads_listing_page() && $this->filters_handler) {
            echo $this->filters_handler->render_filters();
        }

        if ($this->is_leads_listing_page()) {

            $template_path = LTB_LEADS_PLUGIN_DIR . 'templates/table-view.php';
            if (file_exists($template_path)) {
                include $template_path;
            }
            
            $pipeline_template_path = LTB_LEADS_PLUGIN_DIR . 'templates/pipeline-view.php';
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
    $user = wp_get_current_user();
    $allowed_roles = array('administrator', 'ejecutivo_de_ventas');
    
    $can_manage = array_intersect($allowed_roles, $user->roles);
    
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