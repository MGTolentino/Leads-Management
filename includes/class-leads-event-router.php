<?php
if (!defined('ABSPATH')) {
    exit;
}

class LTB_Leads_Event_Router {
    private static $instance = null;
    private $query_handler;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->query_handler = new LTB_Leads_Query();
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_filter('template_include', array($this, 'maybe_load_template'));
    }

    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^event-details/([^/]+)/?$',
            'index.php?event_details=1&event_slug=$matches[1]',
            'top'
        );
    }

    public function add_query_vars($vars) {
        $vars[] = 'event_details';
        $vars[] = 'event_slug';
        return $vars;
    }

    public function maybe_load_template($template) {
        if (strpos($_SERVER['REQUEST_URI'], '/event-details/') !== 0) {
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
        $event_slug = end($parts);
        
        if (!empty($event_slug)) {
            preg_match('/-(\d+)$/', $event_slug, $matches);
            if (!empty($matches[1])) {
                $event_id = $matches[1];
                
                // Obtener datos del evento
                global $wpdb;
                $event_data = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}jet_cct_eventos WHERE _ID = %d",
                    $event_id
                ));
                
                if ($event_data) {
                    // Obtener datos del lead asociado
                    $lead_data = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}jet_cct_leads WHERE _ID = %d",
                        $event_data->lead_id
                    ));
                    
                    // Obtener cotizaciones asociadas a este evento
                    $quotes = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}eq_quotes 
                        WHERE event_id = %d 
                        ORDER BY created_at DESC",
                        $event_id
                    ));
                    
                    // Establecer el estado de WordPress
                    global $wp_query;
                    $wp_query->is_404 = false;
                    $wp_query->is_singular = true;
                    $wp_query->is_page = true;
                    status_header(200);
                    
                    // Título para SEO
                    add_filter('document_title_parts', function($title_parts) use ($event_data, $lead_data) {
                        $title_parts['title'] = sprintf('Evento: %s - %s', 
                            $event_data->tipo_de_evento,
                            $lead_data->lead_nombre . ' ' . $lead_data->lead_apellido
                        );
                        return $title_parts;
                    }, 10);
                    
                    // Establecer datos globales
                    $GLOBALS['ltb_event_data'] = $event_data;
                    $GLOBALS['ltb_lead_data'] = $lead_data;
                    $GLOBALS['ltb_event_quotes'] = $quotes;
                    
                    // Cargar nuestro template con header y footer
                    get_header();
                    include LTB_LEADS_PLUGIN_DIR . 'templates/event-content.php';
                    get_footer();
                    exit;
                }
            }
        }
        
        return $template;
    }

    public static function activate() {
        global $wp_rewrite;
        $wp_rewrite->flush_rules();
    }
}

// Inicializar el router
add_action('plugins_loaded', array('LTB_Leads_Event_Router', 'get_instance'));