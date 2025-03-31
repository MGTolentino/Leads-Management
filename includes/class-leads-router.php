<?php
if (!defined('ABSPATH')) {
    exit;
}

class LTB_Leads_Router {
    private static $instance = null;
    private $query_handler;

    public static function get_instance() {
        error_log('=== ROUTER GET_INSTANCE llamado ===');
        if (null === self::$instance) {
            error_log('Creando nueva instancia de Router');
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        error_log('=== ROUTER CONSTRUCTOR ===');
        $this->query_handler = new LTB_Leads_Query();
        $this->init_hooks();
    }

    private function init_hooks() {
        error_log('=== ROUTER INIT HOOKS ===');
        
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_filter('template_include', array($this, 'maybe_load_template'));
        
        error_log('Router hooks registrados');
    }

    public function add_rewrite_rules() {
        error_log('=== ROUTER ADD_REWRITE_RULES llamado ===');
        
        add_rewrite_rule(
            '^lead-details/([^/]+)/?$',
            'index.php?lead_details=1&lead_slug=$matches[1]',
            'top'
        );
        
        error_log('Regla agregada para lead-details');
    }

    public function add_query_vars($vars) {
        error_log('=== ROUTER ADD_QUERY_VARS llamado ===');
        $vars[] = 'lead_details';
        $vars[] = 'lead_slug';
        return $vars;
    }

   public function maybe_load_template($template) {
    if (strpos($_SERVER['REQUEST_URI'], '/lead-details/') !== 0) {
        return $template;
    }
    
    // Verificar permisos antes de procesar cualquier cosa
    if (!ltb_user_can_manage_leads()) {
        // El usuario no tiene permisos, redirigir a la página de inicio
        wp_redirect(home_url());
        exit;
    }
    
    $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $parts = explode('/', $path);
    $lead_slug = end($parts);
    
    if (!empty($lead_slug)) {
        preg_match('/-(\d+)$/', $lead_slug, $matches);
        if (!empty($matches[1])) {
            $lead_id = $matches[1];
            $lead_data = $this->query_handler->get_single_lead($lead_id);
            
            if ($lead_data) {
                // Establecer el estado de WordPress
                global $wp_query;
                $wp_query->is_404 = false;
                $wp_query->is_singular = true;
                $wp_query->is_page = true;
                status_header(200);
                
                // Título para Yoast SEO (prioridad alta)
                add_filter('wpseo_title', function($title) use ($lead_data) {
                    return sprintf('Lead: %s %s', $lead_data->lead_nombre, $lead_data->lead_apellido);
                }, 10);
                
                // Título para casos donde Yoast no se active
                add_filter('document_title_parts', function($title_parts) use ($lead_data) {
                    $title_parts['title'] = sprintf('Lead: %s %s', $lead_data->lead_nombre, $lead_data->lead_apellido);
                    return $title_parts;
                }, 10);
                
                // Deshabilitar el título predeterminado para evitar conflictos
                add_filter('pre_get_document_title', '__return_false', 5);
                
                // Para temas que usan wp_title directamente
                add_filter('wp_title', function($title, $sep, $seplocation) use ($lead_data) {
                    return sprintf('Lead: %s %s', $lead_data->lead_nombre, $lead_data->lead_apellido) . ' ' . $sep . ' ' . get_bloginfo('name');
                }, 10, 3);
                
                // Engañar a Yoast para que crea que estamos en una página real
                if (class_exists('WPSEO_Frontend')) {
                    add_action('wpseo_head', function() use ($lead_data) {
                        echo '<!-- Lead Details Page: ' . esc_html($lead_data->lead_id) . ' -->';
                    }, 1);
                }
                
                // Establecer datos globales
                $GLOBALS['ltb_lead_data'] = $lead_data;
                
                // Cargar nuestro template con header y footer
                get_header();
                include LTB_LEADS_PLUGIN_DIR . 'templates/single-content.php';
                get_footer();
                exit;
            }
        }
    }
    
    return $template;
}

    public static function activate() {
        error_log('=== ROUTER ACTIVATE llamado ===');
        global $wp_rewrite;
        $wp_rewrite->flush_rules();
    }
}