<?php
if (!defined('ABSPATH')) {
    exit;
}

class LTB_Leads_Router {
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
            '^lead-details/([^/]+)/?$',
            'index.php?lead_details=1&lead_slug=$matches[1]',
            'top'
        );
        
        add_rewrite_rule(
            '^event-details/([^/]+)/?$',
            'index.php?event_details=1&event_slug=$matches[1]',
            'top'
        );
        
    }

    public function add_query_vars($vars) {
        $vars[] = 'lead_details';
        $vars[] = 'lead_slug';
        $vars[] = 'event_details';
        $vars[] = 'event_slug';
        return $vars;
    }

   public function maybe_load_template($template) {
    $request_uri = $_SERVER['REQUEST_URI'];
    
    // Manejar lead-details
    if (strpos($request_uri, '/lead-details/') === 0) {
        return $this->handle_lead_details($template);
    }
    
    // Manejar event-details
    if (strpos($request_uri, '/event-details/') === 0) {
        return $this->handle_event_details($template);
    }
    
    return $template;
   }
   
   private function handle_lead_details($template) {
    
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

private function handle_event_details($template) {
    // Verificar permisos antes de procesar cualquier cosa
    if (!ltb_user_can_manage_leads()) {
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
            
            // Necesitamos obtener los datos del evento
            global $wpdb;
            $evento_data = $wpdb->get_row($wpdb->prepare(
                "SELECT e.*, l.lead_nombre, l.lead_apellido, l.lead_celular, l.lead_e_mail, l.lead_razon_social
                 FROM {$wpdb->prefix}jet_cct_eventos e
                 LEFT JOIN {$wpdb->prefix}jet_cct_leads l ON e.lead_id = l._ID
                 WHERE e._ID = %d",
                $event_id
            ));
            
            if ($evento_data) {
                // Establecer el estado de WordPress
                global $wp_query;
                $wp_query->is_404 = false;
                $wp_query->is_singular = true;
                $wp_query->is_page = true;
                status_header(200);
                
                // Configurar títulos
                $event_title = sprintf('Evento: %s - %s %s', 
                    $evento_data->tipo_de_evento, 
                    $evento_data->lead_nombre, 
                    $evento_data->lead_apellido
                );
                
                add_filter('wpseo_title', function($title) use ($event_title) {
                    return $event_title;
                }, 10);
                
                add_filter('document_title_parts', function($title_parts) use ($event_title) {
                    $title_parts['title'] = $event_title;
                    return $title_parts;
                }, 10);
                
                // Establecer datos globales
                $GLOBALS['ltb_event_data'] = $evento_data;
                
                // Cargar template de evento
                get_header();
                
                // Verificar si existe template específico para eventos
                $event_template = LTB_LEADS_PLUGIN_DIR . 'templates/single-event.php';
                if (file_exists($event_template)) {
                    include $event_template;
                } else {
                    // Fallback: usar el template de lead pero con datos de evento
                    $GLOBALS['ltb_lead_data'] = (object) [
                        'lead_id' => $evento_data->lead_id,
                        'lead_nombre' => $evento_data->lead_nombre,
                        'lead_apellido' => $evento_data->lead_apellido,
                        'lead_celular' => $evento_data->lead_celular,
                        'lead_e_mail' => $evento_data->lead_e_mail,
                        'lead_razon_social' => $evento_data->lead_razon_social,
                        'eventos' => [$evento_data]
                    ];
                    include LTB_LEADS_PLUGIN_DIR . 'templates/single-content.php';
                }
                
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