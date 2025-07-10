<?php
if (!defined('ABSPATH')) {
    exit;
}

class LTB_Leads_Add {
    private $wpdb;

    public function __construct($skip_hooks = false) {
    global $wpdb;
    $this->wpdb = $wpdb;
    
    if (!$skip_hooks) {
        $this->init_hooks();
    }
}

    private function init_hooks() {
        add_action('wp_ajax_add_new_lead', array($this, 'handle_add_lead'));
        // Removed wp_ajax_nopriv_add_new_lead since this action requires admin permissions
    }

    public function handle_add_lead() {
        check_ajax_referer('ltb_leads_nonce', 'nonce');
        
        if (!ltb_user_can_manage_leads()) {
            wp_send_json_error('No tienes permisos para realizar esta acción');
            return;
        }
        
        // DEBUG: Log all POST data
        error_log('DEBUG handle_add_lead - All POST data: ' . print_r($_POST, true));
        
        $form_type = isset($_POST['form_type']) ? sanitize_text_field($_POST['form_type']) : '';
        
        if (!in_array($form_type, array('lead_only', 'lead_and_event'))) {
            wp_send_json_error('Tipo de formulario inválido');
            return;
        }
        
        // Datos obligatorios del lead
        $lead_data = array(
			'lead_razon_social' => isset($_POST['lead_razon_social']) ? sanitize_text_field($_POST['lead_razon_social']) : '',
            'lead_nombre' => isset($_POST['lead_nombre']) ? sanitize_text_field($_POST['lead_nombre']) : '',
            'lead_apellido' => isset($_POST['lead_apellido']) ? sanitize_text_field($_POST['lead_apellido']) : '',
            'lead_celular' => isset($_POST['lead_celular']) ? sanitize_text_field($_POST['lead_celular']) : '',
            'lead_e_mail' => isset($_POST['lead_e_mail']) ? sanitize_email($_POST['lead_e_mail']) : '',
            'cct_status' => 'publish',
            'cct_created' => current_time('mysql'),
            'cct_modified' => current_time('mysql')
        );
        
        // Validar datos obligatorios
        if (empty($lead_data['lead_nombre']) || empty($lead_data['lead_apellido']) || 
            empty($lead_data['lead_celular']) || empty($lead_data['lead_e_mail'])) {
            wp_send_json_error('Por favor completa todos los campos obligatorios del lead');
            return;
        }
        
        // Validar email
        if (!is_email($lead_data['lead_e_mail'])) {
            wp_send_json_error('El email no es válido');
            return;
        }
        
        // Check if user already exists with this email
        $existing_user_id = email_exists($lead_data['lead_e_mail']);
		
		if ($existing_user_id) {
        // Verificar si ya existe un lead con este usuario
        $existing_lead = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT _ID FROM {$this->wpdb->prefix}jet_cct_leads WHERE lead_e_mail = %s",
                $lead_data['lead_e_mail']
            )
        );
        
        if ($existing_lead) {
            // Si ya existe un lead, devuelve la información
            wp_send_json_error(array(
                'code' => 'existing_lead',
                'lead_id' => $existing_lead->_ID,
                'message' => 'Este correo ya está registrado'
            ));
            return;
        }
        
        // Asignar el ID de usuario existente al lead
        $lead_data['cct_author_id'] = $existing_user_id;
    } else {
        // Crear nuevo usuario
        $username = $lead_data['lead_e_mail'];
        $password = wp_generate_password(12, true, true);
        
        $user_id = wp_create_user($username, $password, $lead_data['lead_e_mail']);
        
        if (is_wp_error($user_id)) {
            error_log('Error al crear usuario: ' . $user_id->get_error_message());
            // Continuamos aunque haya error, solo que el lead no tendrá usuario asociado
        } else {
            // Actualizar datos del usuario
            wp_update_user(array(
                'ID' => $user_id,
                'first_name' => $lead_data['lead_nombre'],
                'last_name' => $lead_data['lead_apellido'],
                'role' => 'subscriber' // Usar un rol que exista por defecto
            ));
            
            // Asignar el ID de usuario al lead
            $lead_data['cct_author_id'] = $user_id;
        }
		}
	
        
        // Insertar lead
        $formats = array();
        foreach ($lead_data as $key => $value) {
            if ($key === 'cct_author_id') {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }
        
        $inserted = $this->wpdb->insert(
            $this->wpdb->prefix . 'jet_cct_leads',
            $lead_data,
            $formats
        );
        
        if (!$inserted) {
            wp_send_json_error('Error al guardar el lead: ' . $this->wpdb->last_error);
            return;
        }
        
        // Obtener ID del lead insertado
        $lead_id = $this->wpdb->insert_id;
        
// Si es formulario completo, agregar también el evento
if ($form_type === 'lead_and_event') {
    // DEBUG: Log event field validation
    error_log('DEBUG - Event validation started');
    error_log('DEBUG - fecha_de_evento exists in POST: ' . (isset($_POST['fecha_de_evento']) ? 'YES' : 'NO'));
    error_log('DEBUG - fecha_de_evento value: ' . (isset($_POST['fecha_de_evento']) ? $_POST['fecha_de_evento'] : 'NOT SET'));
    error_log('DEBUG - tipo_de_evento exists in POST: ' . (isset($_POST['tipo_de_evento']) ? 'YES' : 'NO'));
    error_log('DEBUG - tipo_de_evento value: ' . (isset($_POST['tipo_de_evento']) ? $_POST['tipo_de_evento'] : 'NOT SET'));
    
    // Validar campos requeridos del evento
    $evento_fecha = isset($_POST['fecha_de_evento']) ? sanitize_text_field($_POST['fecha_de_evento']) : '';
    $evento_tipo = isset($_POST['tipo_de_evento']) ? sanitize_text_field($_POST['tipo_de_evento']) : '';
    
    // DEBUG: Log sanitized values
    error_log('DEBUG - Sanitized evento_fecha: "' . $evento_fecha . '"');
    error_log('DEBUG - Sanitized evento_tipo: "' . $evento_tipo . '"');
    error_log('DEBUG - fecha empty: ' . (empty($evento_fecha) ? 'YES' : 'NO'));
    error_log('DEBUG - tipo empty: ' . (empty($evento_tipo) ? 'YES' : 'NO'));
    
    if (empty($evento_fecha) || empty($evento_tipo)) {
        // Si hay error en el evento, eliminar el lead para mantener consistencia
        $this->wpdb->delete($this->wpdb->prefix . 'jet_cct_leads', array('_ID' => $lead_id));
        error_log('DEBUG - Event validation FAILED - sending error response');
        wp_send_json_error('Por favor completa los campos obligatorios del evento');
        return;
    }
    
    error_log('DEBUG - Event validation PASSED');
    
    // Obtener y validar la fecha como timestamp
    $evento_fecha_original = isset($_POST['fecha_de_evento']) ? $_POST['fecha_de_evento'] : '';
    
    $evento_fecha = sanitize_text_field($evento_fecha_original);
    
    $fecha_timestamp = strtotime($evento_fecha);
    
    if ($fecha_timestamp === false) {
        // Si falla la conversión, usar timestamp actual como fallback
        $fecha_timestamp = time();
    }
    
    $evento_fecha = $fecha_timestamp;
    
    // Preparar datos del evento
            $evento_data = array(
                'lead_id' => $lead_id,
                'fecha_de_evento' => $evento_fecha,
                'tipo_de_evento' => $evento_tipo,
                'evento_asistentes' => isset($_POST['evento_asistentes']) ? sanitize_text_field($_POST['evento_asistentes']) : '',
                'evento_status' => isset($_POST['evento_status']) ? sanitize_text_field($_POST['evento_status']) : 'nuevo',
                'ubicacion_evento' => isset($_POST['ubicacion_evento']) ? sanitize_text_field($_POST['ubicacion_evento']) : '',
                'direccion_evento' => isset($_POST['direccion_evento']) ? sanitize_text_field($_POST['direccion_evento']) : '',
                'evento_servicio_de_interes' => isset($_POST['evento_servicio_de_interes']) ? esc_url_raw($_POST['evento_servicio_de_interes']) : '',
                'comentarios_evento' => isset($_POST['comentarios_evento']) ? sanitize_textarea_field($_POST['comentarios_evento']) : '',
                'cct_status' => 'publish',
                'cct_created' => current_time('mysql'),
                'cct_modified' => current_time('mysql')
            );
			
		// Procesar URL de servicio para extraer categoría y ubicación
if (!empty($evento_data['evento_servicio_de_interes'])) {
    // Extraer categoría
    $categoria = $this->extraer_categoria_desde_url($evento_data['evento_servicio_de_interes']);
    if ($categoria) {
        $evento_data['categoria_listing_post'] = $categoria;
    }
    
    // Extraer ubicación
    $ubicacion = $this->extraer_ubicacion_desde_url($evento_data['evento_servicio_de_interes']);
    if ($ubicacion) {
        $evento_data['ubicacion_evento'] = $ubicacion;
    }
}
            
            // Insertar evento
            $evento_formats = array();
            foreach ($evento_data as $key => $value) {
                if ($key === 'lead_id' || $key === 'fecha_de_evento') {
                    $evento_formats[] = '%d';
                } else {
                    $evento_formats[] = '%s';
                }
            }
            
            $evento_inserted = $this->wpdb->insert(
                $this->wpdb->prefix . 'jet_cct_eventos',
                $evento_data,
                $evento_formats
            );
            
            if (!$evento_inserted) {
                // Si hay error en el evento, eliminar el lead para mantener consistencia
                $this->wpdb->delete($this->wpdb->prefix . 'jet_cct_leads', array('_ID' => $lead_id));
                wp_send_json_error('Error al guardar el evento: ' . $this->wpdb->last_error);
                return;
            }
        }
        
        // Enviar respuesta exitosa
        wp_send_json_success(array(
            'lead_id' => $lead_id,
            'message' => $form_type === 'lead_only' ? 
                        'Lead guardado correctamente' : 
                        'Lead y evento guardados correctamente'
        ));
    }
	
	/**
 * Obtiene la categoría padre de un post
 */
private function get_post_parent_category($post_id) {
    // Intentar con la primera taxonomía
    $terms = get_the_terms($post_id, 'hp_listing_category');
    
    if (!$terms || is_wp_error($terms)) {
        // Si no encuentra con la primera taxonomía, intentar con la segunda
        $terms = get_the_terms($post_id, 'hp_listing_categoria');
    }
    
    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $parent_id = $term->parent;
            if ($parent_id) {
                // Si tiene padre, obtener el término padre
                $parent_term = get_term($parent_id, $term->taxonomy);
                if ($parent_term && !is_wp_error($parent_term)) {
                    return $parent_term->name;
                }
            } else {
                // Si no tiene padre, usar el término actual
                return $term->name;
            }
        }
    }
    
    return '';
}
	
	
	/**
 * Extrae la categoría de un post desde su URL o ID
 */
public function extraer_categoria_desde_url($url_or_id) {
    
    // Si es un ID numérico
    if (is_numeric($url_or_id)) {
        return $this->get_post_parent_category($url_or_id);
    }
    
    // Si es una URL
    $post_id = url_to_postid($url_or_id);
    
    if (!$post_id) {
        // Intentar extraer usando patrones de URL
        $patterns = [
            'contrata-el-servicio-de/',
            'book-service-for/'
        ];
        
        $post_slug = '';
        foreach ($patterns as $pattern) {
            if (strpos($url_or_id, $pattern) !== false) {
                $parts = explode($pattern, $url_or_id);
                if (isset($parts[1])) {
                    $post_slug = rtrim($parts[1], '/');
                    break;
                }
            }
        }
        
        if (!empty($post_slug)) {
            // Buscar el post por slug
            $args = array(
                'name'        => $post_slug,
                'post_type'   => 'hp_listing',
                'post_status' => 'publish',
                'numberposts' => 1
            );
            $posts = get_posts($args);
            
            if (!empty($posts)) {
                $post_id = $posts[0]->ID;
            }
        }
    }
    
    if (!$post_id) {
        error_log('No se pudo encontrar un post para la entrada: ' . $url_or_id);
        return '';
    }
    
    return $this->get_post_parent_category($post_id);
}

	
/**
     * Extrae la ubicación de un post desde su URL
     * Si hay múltiples ubicaciones, obtiene la ubicación padre
     */
    public function extraer_ubicacion_desde_url($url) {
        
        // Si la URL está vacía, retornar vacío
        if (empty($url)) {
            return '';
        }
        
        // Obtener ID del post
        $post_id = url_to_postid($url);
        
        if (!$post_id) {
            // Intentar extraer usando patrones
            $patterns = [
                'contrata-el-servicio-de/',
                'book-service-for/'
            ];
            
            $post_slug = '';
            foreach ($patterns as $pattern) {
                if (strpos($url, $pattern) !== false) {
                    $parts = explode($pattern, $url);
                    if (isset($parts[1])) {
                        $post_slug = rtrim($parts[1], '/');
                        break;
                    }
                }
            }
            
            if (!empty($post_slug)) {
                // Buscar el post por slug
                $args = array(
                    'name'        => $post_slug,
                    'post_type'   => array('post', 'hp_listing'),
                    'post_status' => 'publish',
                    'numberposts' => 1
                );
                $posts = get_posts($args);
                
                if (!empty($posts)) {
                    $post_id = $posts[0]->ID;
                }
            }
        }
        
        if (!$post_id) {
            error_log('No se pudo encontrar un post para la URL: ' . $url);
            return '';
        }
        
        // Obtener términos de la taxonomía hp_listing_ubicacion
        $location_terms = get_the_terms($post_id, 'hp_listing_ubicacion');
        
        if (empty($location_terms) || is_wp_error($location_terms)) {
            error_log('No se encontraron ubicaciones para el post ID: ' . $post_id);
            return '';
        }
        
        // Si hay una sola ubicación, devolverla
        if (count($location_terms) === 1) {
            return $location_terms[0]->name;
        }
        
        // Buscar ubicación padre
        $parent_location = '';
        $parent_locations = array();
        
        foreach ($location_terms as $term) {
            if ($term->parent === 0) {
                // Es un término padre
                $parent_locations[] = $term->name;
            } else {
                // Obtener el término padre
                $parent = get_term($term->parent, 'hp_listing_ubicacion');
                if (!is_wp_error($parent)) {
                    $parent_locations[] = $parent->name;
                }
            }
        }
        
        // Eliminar duplicados
        $parent_locations = array_unique($parent_locations);
        
        if (!empty($parent_locations)) {
            // Tomar el primer padre
            $parent_location = reset($parent_locations);
        } else {
            // Si no hay padres, tomar la primera ubicación
            $parent_location = $location_terms[0]->name;
        }
        
        return $parent_location;
    }

}